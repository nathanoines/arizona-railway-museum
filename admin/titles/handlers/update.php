<?php
/**
 * Handler: Update Title
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

$title_id = (int)($_POST['title_id'] ?? 0);
$title_name = trim($_POST['title_name'] ?? '');
$display_order = (int)($_POST['display_order'] ?? 0);
$is_active = isset($_POST['is_active']) ? 1 : 0;

if ($title_id < 1) {
    $_SESSION['error_message'] = 'Invalid title ID.';
    header('Location: /admin/titles/');
    exit;
}

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

    // Get current title for logging
    $currentStmt = $pdo->prepare("SELECT * FROM member_titles WHERE id = :id");
    $currentStmt->execute([':id' => $title_id]);
    $currentTitle = $currentStmt->fetch();

    if (!$currentTitle) {
        $_SESSION['error_message'] = 'Title not found.';
        header('Location: /admin/titles/');
        exit;
    }

    // Check for duplicate title name (excluding current)
    $checkStmt = $pdo->prepare("SELECT id FROM member_titles WHERE title_name = :name AND id != :id");
    $checkStmt->execute([':name' => $title_name, ':id' => $title_id]);
    if ($checkStmt->fetch()) {
        $_SESSION['error_message'] = 'A title with this name already exists.';
        header('Location: /admin/titles/');
        exit;
    }

    // Update title
    $stmt = $pdo->prepare("
        UPDATE member_titles
        SET title_name = :name, display_order = :display_order, is_active = :is_active
        WHERE id = :id
    ");
    $stmt->execute([
        ':name' => $title_name,
        ':display_order' => $display_order,
        ':is_active' => $is_active,
        ':id' => $title_id
    ]);

    // Build change description
    $changes = [];
    if ($currentTitle['title_name'] !== $title_name) {
        $changes[] = "name changed from '{$currentTitle['title_name']}' to '{$title_name}'";
    }
    if ((int)$currentTitle['display_order'] !== $display_order) {
        $changes[] = "order changed from {$currentTitle['display_order']} to {$display_order}";
    }
    if ((int)$currentTitle['is_active'] !== $is_active) {
        $changes[] = $is_active ? 'activated' : 'deactivated';
    }

    $changeDesc = !empty($changes)
        ? "Updated title #{$title_id}: " . implode(', ', $changes)
        : "Updated title #{$title_id} (no changes)";

    // Log activity
    $logStmt = $pdo->prepare("
        INSERT INTO activity_logs (user_id, action_type, entity_type, entity_id, description, old_value, new_value, ip_address, user_agent)
        VALUES (:user_id, 'title_updated', 'title', :entity_id, :description, :old_value, :new_value, :ip_address, :user_agent)
    ");
    $logStmt->execute([
        ':user_id' => $_SESSION['user_id'],
        ':entity_id' => $title_id,
        ':description' => $changeDesc,
        ':old_value' => json_encode(['title_name' => $currentTitle['title_name'], 'display_order' => $currentTitle['display_order'], 'is_active' => $currentTitle['is_active']]),
        ':new_value' => json_encode(['title_name' => $title_name, 'display_order' => $display_order, 'is_active' => $is_active]),
        ':ip_address' => $_SERVER['REMOTE_ADDR'] ?? null,
        ':user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? null
    ]);

    $_SESSION['success_message'] = "Title \"{$title_name}\" updated successfully.";

} catch (PDOException $e) {
    error_log('Update title error: ' . $e->getMessage());
    $_SESSION['error_message'] = 'Database error occurred. Please try again.';
}

header('Location: /admin/titles/');
exit;
