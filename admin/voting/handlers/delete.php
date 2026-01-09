<?php
session_start();
require_once __DIR__ . '/../../../config/db.php';
$pdo = getDbConnection();

// Admin gate
if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'admin') {
    header('Location: /login.php');
    exit;
}

$electionId = (int)($_GET['id'] ?? 0);
if ($electionId < 1) {
    $_SESSION['error_message'] = 'Invalid election ID.';
    header('Location: /admin/voting/');
    exit;
}

// Fetch election
$stmt = $pdo->prepare("SELECT * FROM elections WHERE id = :id");
$stmt->execute([':id' => $electionId]);
$election = $stmt->fetch();

if (!$election) {
    $_SESSION['error_message'] = 'Election not found.';
    header('Location: /admin/voting/');
    exit;
}

// Only allow deleting draft elections (no votes cast)
if ($election['status'] !== 'draft') {
    $_SESSION['error_message'] = 'Only draft elections can be deleted. Use "Close" for active elections.';
    header('Location: /admin/voting/');
    exit;
}

try {
    // Delete election (candidates will cascade delete due to FK)
    $deleteStmt = $pdo->prepare("DELETE FROM elections WHERE id = :id");
    $deleteStmt->execute([':id' => $electionId]);

    // Log activity
    $activity_sql = "INSERT INTO activity_logs (
                        user_id, action_type, entity_type, entity_id,
                        description, old_value, ip_address, user_agent
                     ) VALUES (
                        :user_id, :action_type, :entity_type, :entity_id,
                        :description, :old_value, :ip_address, :user_agent
                     )";
    $activity_stmt = $pdo->prepare($activity_sql);
    $activity_stmt->execute([
        ':user_id' => $_SESSION['user_id'],
        ':action_type' => 'election_deleted',
        ':entity_type' => 'election',
        ':entity_id' => $electionId,
        ':description' => "Deleted draft election: {$election['title']}",
        ':old_value' => json_encode([
            'title' => $election['title'],
            'opens_at' => $election['opens_at'],
            'closes_at' => $election['closes_at']
        ]),
        ':ip_address' => $_SERVER['REMOTE_ADDR'] ?? null,
        ':user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? null
    ]);

    $_SESSION['success_message'] = "Election \"{$election['title']}\" has been deleted.";

} catch (Exception $e) {
    error_log('Election delete error: ' . $e->getMessage());
    $_SESSION['error_message'] = 'An error occurred while deleting the election.';
}

header('Location: /admin/voting/');
exit;
