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

if ($election['status'] !== 'draft') {
    $_SESSION['error_message'] = 'Only draft elections can be published.';
    header('Location: /admin/voting/');
    exit;
}

// Check that election has at least 2 candidates
$candidateCount = $pdo->prepare("SELECT COUNT(*) FROM election_candidates WHERE election_id = :id");
$candidateCount->execute([':id' => $electionId]);
$count = (int)$candidateCount->fetchColumn();

if ($count < 2) {
    $_SESSION['error_message'] = 'Election must have at least 2 candidates before publishing.';
    header('Location: /admin/voting/');
    exit;
}

try {
    // Update election status to 'open'
    $updateStmt = $pdo->prepare("UPDATE elections SET status = 'open' WHERE id = :id");
    $updateStmt->execute([':id' => $electionId]);

    // Log activity
    $activity_sql = "INSERT INTO activity_logs (
                        user_id, action_type, entity_type, entity_id,
                        description, ip_address, user_agent
                     ) VALUES (
                        :user_id, :action_type, :entity_type, :entity_id,
                        :description, :ip_address, :user_agent
                     )";
    $activity_stmt = $pdo->prepare($activity_sql);
    $activity_stmt->execute([
        ':user_id' => $_SESSION['user_id'],
        ':action_type' => 'election_published',
        ':entity_type' => 'election',
        ':entity_id' => $electionId,
        ':description' => "Published election: {$election['title']}",
        ':ip_address' => $_SERVER['REMOTE_ADDR'] ?? null,
        ':user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? null
    ]);

    $_SESSION['success_message'] = "Election \"{$election['title']}\" has been published. Voting will be open from " .
        date('M j, Y g:i A', strtotime($election['opens_at'])) . " to " .
        date('M j, Y g:i A', strtotime($election['closes_at'])) . ".";

} catch (Exception $e) {
    error_log('Election publish error: ' . $e->getMessage());
    $_SESSION['error_message'] = 'An error occurred while publishing the election.';
}

header('Location: /admin/voting/');
exit;
