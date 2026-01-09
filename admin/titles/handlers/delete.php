<?php
/**
 * Handler: Delete Title
 */

session_start();
require_once __DIR__ . '/../../../config/db.php';

// Admin gate
if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'admin') {
    header('Location: /login.php');
    exit;
}

$title_id = (int)($_GET['id'] ?? 0);

if ($title_id < 1) {
    $_SESSION['error_message'] = 'Invalid title ID.';
    header('Location: /admin/titles/');
    exit;
}

try {
    $pdo = getDbConnection();

    // Get title info and check for assignments
    $stmt = $pdo->prepare("
        SELECT t.*, COUNT(mta.id) as assignment_count
        FROM member_titles t
        LEFT JOIN member_title_assignments mta ON t.id = mta.title_id
        WHERE t.id = :id
        GROUP BY t.id
    ");
    $stmt->execute([':id' => $title_id]);
    $title = $stmt->fetch();

    if (!$title) {
        $_SESSION['error_message'] = 'Title not found.';
        header('Location: /admin/titles/');
        exit;
    }

    if ($title['assignment_count'] > 0) {
        $_SESSION['error_message'] = "Cannot delete \"{$title['title_name']}\" because it is assigned to {$title['assignment_count']} member(s). Remove the title from all members first.";
        header('Location: /admin/titles/');
        exit;
    }

    // Delete the title
    $deleteStmt = $pdo->prepare("DELETE FROM member_titles WHERE id = :id");
    $deleteStmt->execute([':id' => $title_id]);

    // Log activity
    $logStmt = $pdo->prepare("
        INSERT INTO activity_logs (user_id, action_type, entity_type, entity_id, description, old_value, ip_address, user_agent)
        VALUES (:user_id, 'title_deleted', 'title', :entity_id, :description, :old_value, :ip_address, :user_agent)
    ");
    $logStmt->execute([
        ':user_id' => $_SESSION['user_id'],
        ':entity_id' => $title_id,
        ':description' => "Deleted title: {$title['title_name']}",
        ':old_value' => json_encode(['title_name' => $title['title_name'], 'display_order' => $title['display_order']]),
        ':ip_address' => $_SERVER['REMOTE_ADDR'] ?? null,
        ':user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? null
    ]);

    $_SESSION['success_message'] = "Title \"{$title['title_name']}\" deleted successfully.";

} catch (PDOException $e) {
    error_log('Delete title error: ' . $e->getMessage());
    $_SESSION['error_message'] = 'Database error occurred. Please try again.';
}

header('Location: /admin/titles/');
exit;
