<?php
/**
 * Handler: Export Members to CSV
 *
 * Downloads member data as a CSV file.
 * Restricted to super admins only.
 */

declare(strict_types=1);

session_start();
require_once __DIR__ . '/../../../config/db.php';

// Check if user is logged in as super admin
if (!isset($_SESSION['user_id'])) {
    header('Location: /login.php');
    exit;
}

$userRole = $_SESSION['user_role'] ?? 'user';
$isSuperAdmin = $_SESSION['is_super_admin'] ?? false;

if ($userRole !== 'admin' || !$isSuperAdmin) {
    $_SESSION['error_message'] = 'Access denied. Super admin privileges required.';
    header('Location: /admin/index.php');
    exit;
}

// Only process POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /admin/tools/');
    exit;
}

$filter = $_POST['filter'] ?? 'all';
$allowed_filters = ['all', 'active', 'expiring', 'inactive'];

if (!in_array($filter, $allowed_filters)) {
    $filter = 'all';
}

try {
    $pdo = getDbConnection();

    // Build query based on filter
    $base_sql = "SELECT
        id, first_name, last_name, email, phone,
        address, city, state, zip,
        membership_status, membership_term,
        membership_activated_at, membership_expires_at,
        user_role, created_at
    FROM members";

    switch ($filter) {
        case 'active':
            $sql = $base_sql . " WHERE membership_status != 'inactive' AND membership_expires_at > CURDATE() ORDER BY last_name, first_name";
            break;
        case 'expiring':
            $sql = $base_sql . " WHERE membership_expires_at BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 30 DAY) ORDER BY membership_expires_at ASC";
            break;
        case 'inactive':
            $sql = $base_sql . " WHERE membership_status = 'inactive' OR membership_expires_at IS NULL OR membership_expires_at <= CURDATE() ORDER BY last_name, first_name";
            break;
        default:
            $sql = $base_sql . " ORDER BY last_name, first_name";
    }

    $stmt = $pdo->query($sql);
    $members = $stmt->fetchAll();

    // Generate filename
    $filename = 'members_' . $filter . '_' . date('Y-m-d') . '.csv';

    // Set headers for CSV download
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Pragma: no-cache');
    header('Expires: 0');

    // Open output stream
    $output = fopen('php://output', 'w');

    // Add BOM for Excel UTF-8 compatibility
    fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));

    // Write header row
    fputcsv($output, [
        'ID', 'First Name', 'Last Name', 'Email', 'Phone',
        'Address', 'City', 'State', 'ZIP',
        'Membership Status', 'Membership Term',
        'Activated Date', 'Expires Date',
        'User Role', 'Registered Date'
    ]);

    // Write data rows
    foreach ($members as $member) {
        fputcsv($output, [
            $member['id'],
            $member['first_name'],
            $member['last_name'],
            $member['email'],
            $member['phone'],
            $member['address'],
            $member['city'],
            $member['state'],
            $member['zip'],
            $member['membership_status'],
            $member['membership_term'],
            $member['membership_activated_at'],
            $member['membership_expires_at'],
            $member['user_role'],
            $member['created_at']
        ]);
    }

    fclose($output);

    // Log the export
    $log_pdo = getDbConnection();
    $log_sql = "INSERT INTO activity_logs (
                    user_id, action_type, entity_type, entity_id,
                    description, ip_address, user_agent
                ) VALUES (
                    :user_id, :action_type, :entity_type, :entity_id,
                    :description, :ip_address, :user_agent
                )";

    $log_stmt = $log_pdo->prepare($log_sql);
    $log_stmt->execute([
        ':user_id' => $_SESSION['user_id'],
        ':action_type' => 'export_members',
        ':entity_type' => 'system',
        ':entity_id' => 0,
        ':description' => 'Exported ' . count($members) . ' members (' . $filter . ' filter) to CSV',
        ':ip_address' => $_SERVER['REMOTE_ADDR'] ?? null,
        ':user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? null
    ]);

    exit;

} catch (PDOException $e) {
    error_log('Export members error: ' . $e->getMessage());
    $_SESSION['error_message'] = 'Database error occurred during export.';
    header('Location: /admin/tools/');
    exit;
}
