<?php
/**
 * Admin Handler: Reject Membership Application
 *
 * Marks an application as rejected
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
    header('Location: /admin/index.php');
    exit;
}

// Validate required parameters
if (empty($_POST['application_id'])) {
    $_SESSION['error_message'] = 'Missing required parameter: application_id.';
    header('Location: /admin/membership/index.php');
    exit;
}

$application_id = (int)$_POST['application_id'];

try {
    $pdo = getDbConnection();

    // Verify the application exists and is pending
    $check_sql = "SELECT id, name, status FROM membership_applications WHERE id = :application_id";
    $check_stmt = $pdo->prepare($check_sql);
    $check_stmt->execute([':application_id' => $application_id]);
    $application = $check_stmt->fetch();

    if (!$application) {
        throw new Exception('Application not found.');
    }

    if ($application['status'] !== 'pending') {
        throw new Exception('Application is not pending (current status: ' . $application['status'] . ').');
    }

    // Update the application status to rejected
    $reject_sql = "UPDATE membership_applications SET status = 'rejected' WHERE id = :application_id";
    $reject_stmt = $pdo->prepare($reject_sql);
    $reject_stmt->execute([':application_id' => $application_id]);

    // Log to activity log
    $activity_description = sprintf(
        "Rejected application #%d from %s",
        $application_id,
        $application['name']
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
        ':action_type' => 'application_rejected',
        ':entity_type' => 'membership_application',
        ':entity_id' => $application_id,
        ':description' => $activity_description,
        ':old_value' => json_encode(['status' => 'pending']),
        ':new_value' => json_encode(['status' => 'rejected']),
        ':ip_address' => $_SERVER['REMOTE_ADDR'] ?? null,
        ':user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? null
    ]);

    $_SESSION['success_message'] = sprintf(
        'Application #%d from %s has been rejected.',
        $application_id,
        htmlspecialchars($application['name'], ENT_QUOTES, 'UTF-8')
    );

} catch (PDOException $e) {
    error_log('Membership rejection error (DB): ' . $e->getMessage());
    $_SESSION['error_message'] = 'Database error occurred while rejecting application.';

} catch (Exception $e) {
    $_SESSION['error_message'] = 'Error: ' . $e->getMessage();
}

header('Location: /admin/membership/index.php');
exit;
