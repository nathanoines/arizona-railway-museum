<?php
/**
 * Handler: Toggle User Role Flags
 *
 * Toggles is_super_admin or is_key_holder for a user.
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

// Validate parameters
$user_id = (int)($_POST['user_id'] ?? 0);
$role = $_POST['role'] ?? '';
$current = (int)($_POST['current'] ?? 0);

if ($user_id <= 0) {
    $_SESSION['error_message'] = 'Invalid user ID.';
    header('Location: /admin/tools/');
    exit;
}

// Validate role type
$allowed_roles = ['super_admin', 'key_holder'];
if (!in_array($role, $allowed_roles)) {
    $_SESSION['error_message'] = 'Invalid role type.';
    header('Location: /admin/tools/');
    exit;
}

// Prevent users from modifying their own super admin status
if ($role === 'super_admin' && $user_id === (int)$_SESSION['user_id']) {
    $_SESSION['error_message'] = 'You cannot modify your own super admin status.';
    header('Location: /admin/tools/');
    exit;
}

// Map role to column name
$column = $role === 'super_admin' ? 'is_super_admin' : 'is_key_holder';
$new_value = $current ? 0 : 1;

try {
    $pdo = getDbConnection();

    // Verify user exists and is an admin
    $check_sql = "SELECT id, first_name, last_name, email, user_role FROM members WHERE id = :id";
    $check_stmt = $pdo->prepare($check_sql);
    $check_stmt->execute([':id' => $user_id]);
    $user = $check_stmt->fetch();

    if (!$user) {
        $_SESSION['error_message'] = 'User not found.';
        header('Location: /admin/tools/');
        exit;
    }

    if ($user['user_role'] !== 'admin') {
        $_SESSION['error_message'] = 'Can only modify roles for admin users.';
        header('Location: /admin/tools/');
        exit;
    }

    // Update the role
    $update_sql = "UPDATE members SET {$column} = :value WHERE id = :id";
    $update_stmt = $pdo->prepare($update_sql);
    $update_stmt->execute([':value' => $new_value, ':id' => $user_id]);

    // Log the action
    $role_display = str_replace('_', ' ', $role);
    $action_display = $new_value ? 'granted' : 'revoked';
    $user_name = trim($user['first_name'] . ' ' . $user['last_name']) ?: $user['email'];

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
        ':action_type' => 'role_changed',
        ':entity_type' => 'member',
        ':entity_id' => $user_id,
        ':description' => ucfirst($role_display) . ' ' . $action_display . ' for ' . $user_name . ' (' . $user['email'] . ')',
        ':ip_address' => $_SERVER['REMOTE_ADDR'] ?? null,
        ':user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? null
    ]);

    $_SESSION['success_message'] = ucfirst($role_display) . ' ' . $action_display . ' for ' . htmlspecialchars($user_name, ENT_QUOTES, 'UTF-8') . '.';

} catch (PDOException $e) {
    error_log('Toggle role error: ' . $e->getMessage());
    $_SESSION['error_message'] = 'Database error occurred. Please try again.';
}

header('Location: /admin/tools/');
exit;
