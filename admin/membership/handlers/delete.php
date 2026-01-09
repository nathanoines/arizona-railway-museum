<?php
/**
 * Admin Interface: Delete Membership Application
 *
 * Permanently deletes a membership application from the database
 */

declare(strict_types=1);

session_start();
require_once __DIR__ . '/../../../config/db.php';

// Check if user is logged in as admin
if (!isset($_SESSION['user_id'])) {
    header('Location: /login.php');
    exit;
}

$userRole = $_SESSION['user_role'] ?? 'user';
if ($userRole !== 'admin') {
    header('Location: /members/');
    exit;
}

// Only accept POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /admin/membership/');
    exit;
}

// Validate application_id
if (empty($_POST['application_id'])) {
    $_SESSION['error_message'] = 'Missing required parameter: application_id.';
    header('Location: /admin/membership/');
    exit;
}

$application_id = (int)$_POST['application_id'];

try {
    $pdo = getDbConnection();
    
    // Get full application details before deletion (for audit trail)
    $check_sql = "SELECT * FROM membership_applications WHERE id = :application_id";
    $check_stmt = $pdo->prepare($check_sql);
    $check_stmt->execute([':application_id' => $application_id]);
    $application = $check_stmt->fetch();
    
    if (!$application) {
        $_SESSION['error_message'] = 'Application not found.';
        header('Location: /admin/membership/');
        exit;
    }
    
    // Log to activity log BEFORE deletion (critical for audit trail)
    $activity_description = sprintf(
        "DELETED application #%d - %s (%s) - Status: %s, Level: %s",
        $application_id,
        $application['name'],
        $application['email'],
        $application['status'],
        $application['membership_level']
    );
    
    $activity_sql = "INSERT INTO activity_logs (
                        user_id, action_type, entity_type, entity_id,
                        description, old_value, new_value, ip_address, user_agent
                     ) VALUES (
                        :user_id, :action_type, :entity_type, :entity_id,
                        :description, :old_value, :new_value, :ip_address, :user_agent
                     )";
    
    $activity_stmt = $pdo->prepare($activity_sql);
    $activity_stmt->execute([
        ':user_id' => $_SESSION['user_id'],
        ':action_type' => 'application_deleted',
        ':entity_type' => 'membership_application',
        ':entity_id' => $application_id,
        ':description' => $activity_description,
        ':old_value' => json_encode([
            'id' => $application['id'],
            'name' => $application['name'],
            'email' => $application['email'],
            'phone' => $application['phone'],
            'address' => $application['address'],
            'city' => $application['city'],
            'state' => $application['state'],
            'zip' => $application['zip'],
            'membership_level' => $application['membership_level'],
            'status' => $application['status'],
            'member_id' => $application['member_id'],
            'created_at' => $application['created_at']
        ]),
        ':new_value' => json_encode(['deleted' => true]),
        ':ip_address' => $_SERVER['REMOTE_ADDR'] ?? null,
        ':user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? null
    ]);
    
    // Delete the application
    $delete_sql = "DELETE FROM membership_applications WHERE id = :application_id";
    $delete_stmt = $pdo->prepare($delete_sql);
    $delete_stmt->execute([':application_id' => $application_id]);
    
    $_SESSION['success_message'] = sprintf(
        'Application #%d (%s - %s) has been permanently deleted.',
        $application_id,
        htmlspecialchars($application['name'], ENT_QUOTES, 'UTF-8'),
        htmlspecialchars($application['email'], ENT_QUOTES, 'UTF-8')
    );
    
    header('Location: /admin/membership/');
    exit;
    
} catch (PDOException $e) {
    error_log('Application deletion error: ' . $e->getMessage());
    $_SESSION['error_message'] = 'Database error occurred while deleting application. Please try again.';
    header('Location: /admin/membership/');
    exit;
}
