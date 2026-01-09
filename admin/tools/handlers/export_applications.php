<?php
/**
 * Handler: Export Applications to CSV
 *
 * Downloads membership application data as a CSV file.
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
$allowed_filters = ['all', 'pending', 'approved', 'rejected'];

if (!in_array($filter, $allowed_filters)) {
    $filter = 'all';
}

try {
    $pdo = getDbConnection();

    // Build query based on filter
    $base_sql = "SELECT
        ma.id, ma.name, ma.email, ma.phone,
        ma.address, ma.city, ma.state, ma.zip,
        ma.membership_level, ma.sustaining_amount, ma.corporate_amount,
        ma.interests, ma.comments,
        ma.status, ma.member_id, ma.created_at,
        m.first_name as linked_first_name, m.last_name as linked_last_name
    FROM membership_applications ma
    LEFT JOIN members m ON ma.member_id = m.id";

    if ($filter === 'all') {
        $sql = $base_sql . " ORDER BY ma.created_at DESC";
    } else {
        $sql = $base_sql . " WHERE ma.status = :status ORDER BY ma.created_at DESC";
    }

    $stmt = $pdo->prepare($sql);
    if ($filter !== 'all') {
        $stmt->execute([':status' => $filter]);
    } else {
        $stmt->execute();
    }
    $applications = $stmt->fetchAll();

    // Generate filename
    $filename = 'applications_' . $filter . '_' . date('Y-m-d') . '.csv';

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
        'ID', 'Name', 'Email', 'Phone',
        'Address', 'City', 'State', 'ZIP',
        'Membership Level', 'Sustaining Amount', 'Corporate Amount',
        'Interests', 'Comments',
        'Status', 'Linked Member ID', 'Linked Member Name',
        'Submitted Date'
    ]);

    // Write data rows
    foreach ($applications as $app) {
        $linked_name = '';
        if ($app['member_id'] && ($app['linked_first_name'] || $app['linked_last_name'])) {
            $linked_name = trim($app['linked_first_name'] . ' ' . $app['linked_last_name']);
        }

        fputcsv($output, [
            $app['id'],
            $app['name'],
            $app['email'],
            $app['phone'],
            $app['address'],
            $app['city'],
            $app['state'],
            $app['zip'],
            $app['membership_level'],
            $app['sustaining_amount'],
            $app['corporate_amount'],
            $app['interests'],
            $app['comments'],
            $app['status'],
            $app['member_id'],
            $linked_name,
            $app['created_at']
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
        ':action_type' => 'export_applications',
        ':entity_type' => 'system',
        ':entity_id' => 0,
        ':description' => 'Exported ' . count($applications) . ' applications (' . $filter . ' filter) to CSV',
        ':ip_address' => $_SERVER['REMOTE_ADDR'] ?? null,
        ':user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? null
    ]);

    exit;

} catch (PDOException $e) {
    error_log('Export applications error: ' . $e->getMessage());
    $_SESSION['error_message'] = 'Database error occurred during export.';
    header('Location: /admin/tools/');
    exit;
}
