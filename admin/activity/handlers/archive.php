<?php
/**
 * Archive Activity Log Entry
 *
 * Soft-deletes an activity log entry by setting archived_at timestamp.
 * Super admin only.
 */

declare(strict_types=1);

session_start();
require_once __DIR__ . '/../../../config/db.php';

// Set timezone to MST (Arizona)
date_default_timezone_set('America/Phoenix');

// Check if user is logged in as admin
if (!isset($_SESSION['user_id'])) {
    header('Location: /login.php');
    exit;
}

$userRole = $_SESSION['user_role'] ?? 'user';
if ($userRole !== 'admin') {
    header('Location: /members/index.php');
    exit;
}

// Check if user is super admin
$isSuperAdmin = $_SESSION['is_super_admin'] ?? false;
if (!$isSuperAdmin) {
    $_SESSION['error_message'] = 'Access denied. Super admin privileges required.';
    header('Location: /admin/activity/');
    exit;
}

// Validate request
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /admin/activity/');
    exit;
}

$id = isset($_POST['id']) && ctype_digit($_POST['id']) ? (int)$_POST['id'] : 0;

if ($id <= 0) {
    $_SESSION['error_message'] = 'Invalid activity log ID.';
    header('Location: /admin/activity/');
    exit;
}

$pdo = getDbConnection();

try {
    // Get the activity entry first (for logging purposes)
    $stmt = $pdo->prepare("SELECT * FROM activity_logs WHERE id = :id");
    $stmt->execute([':id' => $id]);
    $activity = $stmt->fetch();

    if (!$activity) {
        $_SESSION['error_message'] = 'Activity log entry not found.';
        header('Location: /admin/activity/');
        exit;
    }

    if ($activity['archived_at'] !== null) {
        $_SESSION['error_message'] = 'Activity log entry is already archived.';
        header('Location: /admin/activity/');
        exit;
    }

    // Archive the entry
    $stmt = $pdo->prepare("UPDATE activity_logs SET archived_at = NOW() WHERE id = :id");
    $stmt->execute([':id' => $id]);

    // Log this archive action
    $log_stmt = $pdo->prepare("
        INSERT INTO activity_logs (user_id, action_type, entity_type, entity_id, description, ip_address, user_agent)
        VALUES (:user_id, 'archived', 'activity_log', :entity_id, :description, :ip_address, :user_agent)
    ");
    $log_stmt->execute([
        ':user_id' => $_SESSION['user_id'],
        ':entity_id' => $id,
        ':description' => 'Archived activity log entry #' . $id . ' (' . ($activity['action_type'] ?? 'unknown') . ')',
        ':ip_address' => $_SERVER['REMOTE_ADDR'] ?? null,
        ':user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? null
    ]);

    $_SESSION['success_message'] = 'Activity log entry archived successfully.';

} catch (PDOException $e) {
    error_log('Archive activity error: ' . $e->getMessage());
    $_SESSION['error_message'] = 'An error occurred while archiving the entry.';
}

header('Location: /admin/activity/');
exit;
