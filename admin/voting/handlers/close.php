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

if ($election['status'] === 'closed') {
    $_SESSION['error_message'] = 'Election is already closed.';
    header('Location: /admin/voting/');
    exit;
}

if ($election['status'] === 'draft') {
    $_SESSION['error_message'] = 'Cannot close a draft election. Delete it instead.';
    header('Location: /admin/voting/');
    exit;
}

try {
    // Get vote count for logging
    $voterCount = $pdo->prepare("SELECT COUNT(*) FROM election_voters WHERE election_id = :id");
    $voterCount->execute([':id' => $electionId]);
    $totalVoters = (int)$voterCount->fetchColumn();

    // Update election status to 'closed'
    $updateStmt = $pdo->prepare("UPDATE elections SET status = 'closed' WHERE id = :id");
    $updateStmt->execute([':id' => $electionId]);

    // Log activity
    $activity_sql = "INSERT INTO activity_logs (
                        user_id, action_type, entity_type, entity_id,
                        description, new_value, ip_address, user_agent
                     ) VALUES (
                        :user_id, :action_type, :entity_type, :entity_id,
                        :description, :new_value, :ip_address, :user_agent
                     )";
    $activity_stmt = $pdo->prepare($activity_sql);
    $activity_stmt->execute([
        ':user_id' => $_SESSION['user_id'],
        ':action_type' => 'election_closed',
        ':entity_type' => 'election',
        ':entity_id' => $electionId,
        ':description' => "Closed election: {$election['title']} with {$totalVoters} total voters",
        ':new_value' => json_encode(['total_voters' => $totalVoters]),
        ':ip_address' => $_SERVER['REMOTE_ADDR'] ?? null,
        ':user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? null
    ]);

    $_SESSION['success_message'] = "Election \"{$election['title']}\" has been closed. {$totalVoters} members voted.";

} catch (Exception $e) {
    error_log('Election close error: ' . $e->getMessage());
    $_SESSION['error_message'] = 'An error occurred while closing the election.';
}

header('Location: /admin/voting/');
exit;
