<?php
/**
 * Handler: Cleanup Old Rejected Applications
 *
 * Deletes rejected membership applications older than specified days.
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

    // Count records to be deleted first
    $count_sql = "SELECT COUNT(*) as cnt FROM membership_applications
                  WHERE status = 'rejected'
                  AND created_at < DATE_SUB(NOW(), INTERVAL :days DAY)";
    $count_stmt = $pdo->prepare($count_sql);
    $count_stmt->execute([':days' => $days]);
    $count = (int)$count_stmt->fetch()['cnt'];

    if ($count === 0) {
        $_SESSION['success_message'] = 'No rejected applications older than ' . $days . ' days found.';
        header('Location: /admin/tools/');
        exit;
    }

    // Delete the records
    $delete_sql = "DELETE FROM membership_applications
                   WHERE status = 'rejected'
                   AND created_at < DATE_SUB(NOW(), INTERVAL :days DAY)";
    $delete_stmt = $pdo->prepare($delete_sql);
    $delete_stmt->execute([':days' => $days]);

    $deleted = $delete_stmt->rowCount();

    // Log the action
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
        ':action_type' => 'cleanup_applications',
        ':entity_type' => 'system',
        ':entity_id' => 0,
        ':description' => 'Deleted ' . $deleted . ' rejected applications older than ' . $days . ' days',
        ':ip_address' => $_SERVER['REMOTE_ADDR'] ?? null,
        ':user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? null
    ]);

    $_SESSION['success_message'] = 'Successfully deleted ' . $deleted . ' rejected application(s) older than ' . $days . ' days.';

} catch (PDOException $e) {
    error_log('Cleanup applications error: ' . $e->getMessage());
    $_SESSION['error_message'] = 'Database error occurred during cleanup. Please try again.';
}

header('Location: /admin/tools/');
exit;
