<?php
session_start();
require_once __DIR__ . '/../../../config/db.php';

// Simple admin check
if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'admin') {
    http_response_code(403);
    die('Access denied. You must be an administrator.');
}

// Validate ID
if (!isset($_GET['id']) || !ctype_digit($_GET['id'])) {
    http_response_code(400);
    die('Invalid event ID.');
}

$eventId = (int)$_GET['id'];
$pdo = getDbConnection();

// Check if event exists
$stmt = $pdo->prepare("SELECT id, title FROM events WHERE id = :id LIMIT 1");
$stmt->execute([':id' => $eventId]);
$event = $stmt->fetch();

if (!$event) {
    http_response_code(404);
    die('Event not found.');
}

// Delete the event
$stmt = $pdo->prepare("DELETE FROM events WHERE id = :id");
$stmt->execute([':id' => $eventId]);

// Log activity
try {
    $activity_sql = "INSERT INTO activity_logs (
                        user_id, action_type, entity_type, entity_id,
                        description, ip_address, user_agent
                     ) VALUES (
                        :user_id, :action_type, :entity_type, :entity_id,
                        :description, :ip_address, :user_agent
                     )";
    $activity_stmt = $pdo->prepare($activity_sql);
    $activity_stmt->execute([
        ':user_id' => $_SESSION['user_id'] ?? null,
        ':action_type' => 'event_deleted',
        ':entity_type' => 'event',
        ':entity_id' => $eventId,
        ':description' => "Deleted event: {$event['title']} (ID #{$eventId})",
        ':ip_address' => $_SERVER['REMOTE_ADDR'] ?? null,
        ':user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? null,
    ]);
} catch (PDOException $e) {
    error_log('Activity log error: ' . $e->getMessage());
}

// Redirect back to events management with success message
$_SESSION['delete_success'] = 'Event "' . $event['title'] . '" has been deleted successfully.';
header('Location: /admin/events/');
exit;
