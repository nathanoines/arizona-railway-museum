<?php
/**
 * Handler: Bulk Archive Old Activity Logs
 *
 * Soft-deletes activity log entries older than specified days by setting archived_at.
 * Restricted to super admins only.
 */

declare(strict_types=1);

session_start();
require_once __DIR__ . '/../../../config/db.php';

// Set timezone to MST (Arizona)
date_default_timezone_set('America/Phoenix');

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

// Validate days parameter
$allowed_days = [90, 180, 365];
$days = (int)($_POST['days'] ?? 90);

if (!in_array($days, $allowed_days)) {
    $_SESSION['error_message'] = 'Invalid time period specified.';
    header('Location: /admin/tools/');
    exit;
}

try {
    $pdo = getDbConnection();

    // Count records to be archived first (only non-archived entries)
    $count_sql = "SELECT COUNT(*) as cnt FROM activity_logs
                  WHERE archived_at IS NULL
                  AND created_at < DATE_SUB(NOW(), INTERVAL :days DAY)";
    $count_stmt = $pdo->prepare($count_sql);
    $count_stmt->execute([':days' => $days]);
    $count = (int)$count_stmt->fetch()['cnt'];

    if ($count === 0) {
        $_SESSION['success_message'] = 'No active activity logs older than ' . $days . ' days found.';
        header('Location: /admin/tools/');
        exit;
    }

    // Archive the records (soft delete)
    $archive_sql = "UPDATE activity_logs
                    SET archived_at = NOW()
                    WHERE archived_at IS NULL
                    AND created_at < DATE_SUB(NOW(), INTERVAL :days DAY)";
    $archive_stmt = $pdo->prepare($archive_sql);
    $archive_stmt->execute([':days' => $days]);

    $archived = $archive_stmt->rowCount();

    // Log this archive action
    $log_sql = "INSERT INTO activity_logs (
                    user_id, action_type, entity_type, entity_id,
                    description, ip_address, user_agent
                ) VALUES (
                    :user_id, :action_type, :entity_type, :entity_id,
                    :description, :ip_address, :user_agent
                )";

    $log_stmt = $pdo->prepare($log_sql);
    $log_stmt->execute([
        ':user_id' => $_SESSION['user_id'],
        ':action_type' => 'bulk_archived',
        ':entity_type' => 'activity_log',
        ':entity_id' => 0,
        ':description' => 'Bulk archived ' . $archived . ' activity log entries older than ' . $days . ' days',
        ':ip_address' => $_SERVER['REMOTE_ADDR'] ?? null,
        ':user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? null
    ]);

    $_SESSION['success_message'] = 'Successfully archived ' . $archived . ' activity log(s) older than ' . $days . ' days.';

} catch (PDOException $e) {
    error_log('Bulk archive activity logs error: ' . $e->getMessage());
    $_SESSION['error_message'] = 'Database error occurred during archiving. Please try again.';
}

header('Location: /admin/tools/');
exit;
