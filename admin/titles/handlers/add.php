<?php
/**
 * Handler: Add New Title
 */

session_start();
require_once __DIR__ . '/../../../config/db.php';

// Admin gate
if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'admin') {
    header('Location: /login.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /admin/titles/');
    exit;
}

$title_name = trim($_POST['title_name'] ?? '');
$display_order = (int)($_POST['display_order'] ?? 0);

if ($title_name === '') {
    $_SESSION['error_message'] = 'Title name is required.';
    header('Location: /admin/titles/');
    exit;
}

if (strlen($title_name) > 100) {
    $_SESSION['error_message'] = 'Title name must be 100 characters or less.';
    header('Location: /admin/titles/');
    exit;
}

try {
    $pdo = getDbConnection();

    // Check for duplicate title name
    $checkStmt = $pdo->prepare("SELECT id FROM member_titles WHERE title_name = :name");
    $checkStmt->execute([':name' => $title_name]);
    if ($checkStmt->fetch()) {
        $_SESSION['error_message'] = 'A title with this name already exists.';
        header('Location: /admin/titles/');
        exit;
    }

    // Insert new title
    $stmt = $pdo->prepare("
        INSERT INTO member_titles (title_name, display_order, is_active)
        VALUES (:name, :display_order, 1)
    ");
    $stmt->execute([
        ':name' => $title_name,
        ':display_order' => $display_order
    ]);

    $newTitleId = $pdo->lastInsertId();

    // Log activity
    $logStmt = $pdo->prepare("
        INSERT INTO activity_logs (user_id, action_type, entity_type, entity_id, description, ip_address, user_agent)
        VALUES (:user_id, 'title_created', 'title', :entity_id, :description, :ip_address, :user_agent)
    ");
    $logStmt->execute([
        ':user_id' => $_SESSION['user_id'],
        ':entity_id' => $newTitleId,
        ':description' => "Created new title: {$title_name}",
        ':ip_address' => $_SERVER['REMOTE_ADDR'] ?? null,
        ':user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? null
    ]);

    $_SESSION['success_message'] = "Title \"{$title_name}\" created successfully.";

} catch (PDOException $e) {
    error_log('Add title error: ' . $e->getMessage());
    $_SESSION['error_message'] = 'Database error occurred. Please try again.';
}

header('Location: /admin/titles/');
exit;
