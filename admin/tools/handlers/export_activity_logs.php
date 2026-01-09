<?php
/**
 * Handler: Export Activity Logs to CSV
 *
 * Downloads activity log data as a CSV file.
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

$days = $_POST['days'] ?? '30';
$allowed_days = ['30', '90', '180', 'all'];

if (!in_array($days, $allowed_days)) {
    $days = '30';
}

try {
    $pdo = getDbConnection();

    // Build query based on filter
    $base_sql = "SELECT
        al.id, al.action_type, al.entity_type, al.entity_id,
        al.description, al.old_value, al.new_value,
        al.ip_address, al.created_at,
        m.first_name, m.last_name, m.email as user_email
    FROM activity_logs al
    LEFT JOIN members m ON al.user_id = m.id";

    if ($days === 'all') {
        $sql = $base_sql . " ORDER BY al.created_at DESC";
        $stmt = $pdo->query($sql);
    } else {
        $sql = $base_sql . " WHERE al.created_at >= DATE_SUB(NOW(), INTERVAL :days DAY) ORDER BY al.created_at DESC";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([':days' => (int)$days]);
    }

    $logs = $stmt->fetchAll();

    // Generate filename
    $filename = 'activity_logs_' . ($days === 'all' ? 'all' : $days . '_days') . '_' . date('Y-m-d') . '.csv';

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
        'ID', 'Date/Time', 'User', 'User Email',
        'Action Type', 'Entity Type', 'Entity ID',
        'Description', 'IP Address'
    ]);

    // Write data rows
    foreach ($logs as $log) {
        $user_name = trim(($log['first_name'] ?? '') . ' ' . ($log['last_name'] ?? ''));
        if (empty($user_name)) {
            $user_name = 'System';
        }

        fputcsv($output, [
            $log['id'],
            $log['created_at'],
            $user_name,
            $log['user_email'] ?? '',
            $log['action_type'],
            $log['entity_type'],
            $log['entity_id'],
            $log['description'],
            $log['ip_address']
        ]);
    }

    fclose($output);

    // Log the export (using a new connection since we're outputting)
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
        ':action_type' => 'export_activity_logs',
        ':entity_type' => 'system',
        ':entity_id' => 0,
        ':description' => 'Exported ' . count($logs) . ' activity logs (' . ($days === 'all' ? 'all time' : 'last ' . $days . ' days') . ') to CSV',
        ':ip_address' => $_SERVER['REMOTE_ADDR'] ?? null,
        ':user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? null
    ]);

    exit;

} catch (PDOException $e) {
    error_log('Export activity logs error: ' . $e->getMessage());
    $_SESSION['error_message'] = 'Database error occurred during export.';
    header('Location: /admin/tools/');
    exit;
}
