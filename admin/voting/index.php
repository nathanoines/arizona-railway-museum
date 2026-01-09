<?php
session_start();
require_once __DIR__ . '/../../config/db.php';
$pdo = getDbConnection();

// Admin gate
if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'admin') {
    header('Location: /login.php');
    exit;
}

// Check for session messages
$successMessage = $_SESSION['success_message'] ?? null;
$errorMessage = $_SESSION['error_message'] ?? null;
unset($_SESSION['success_message'], $_SESSION['error_message']);

// Fetch all elections with vote counts
$stmt = $pdo->query("
    SELECT
        e.id,
        e.title,
        e.description,
        e.max_selections,
        e.opens_at,
        e.closes_at,
        e.status,
        e.created_at,
        m.first_name as creator_first_name,
        m.last_name as creator_last_name,
        (SELECT COUNT(*) FROM election_candidates WHERE election_id = e.id) as candidate_count,
        (SELECT COUNT(*) FROM election_voters WHERE election_id = e.id) as voter_count
    FROM elections e
    LEFT JOIN members m ON e.created_by_id = m.id
    ORDER BY e.created_at DESC
");
$elections = $stmt->fetchAll();

// Separate by status
$draftElections = [];
$activeElections = [];
$closedElections = [];

$now = new DateTime('now', new DateTimeZone('America/Phoenix'));

foreach ($elections as $election) {
    // Auto-determine effective status based on dates
    $opensAt = new DateTime($election['opens_at'], new DateTimeZone('America/Phoenix'));
    $closesAt = new DateTime($election['closes_at'], new DateTimeZone('America/Phoenix'));

    if ($election['status'] === 'draft') {
        $draftElections[] = $election;
    } elseif ($election['status'] === 'closed' || $now > $closesAt) {
        $closedElections[] = $election;
    } else {
        $activeElections[] = $election;
    }
}

$page_title = 'Voting Management | Arizona Railway Museum';
require_once __DIR__ . '/../../assets/header.php';
?>

</div></div><!-- Close grid-container and page-content for full-width hero -->

<section class="arm-hero" style="margin-top: -4.5rem; padding: 2.5rem 0 2rem;">
    <div class="grid-container">
        <div class="grid-x grid-margin-x align-middle">
            <div class="small-12 cell">
                <h1 style="margin-bottom: 0.25rem;">Voting Management</h1>
                <p class="lead" style="margin-bottom: 0;">Create and manage board member elections and ballots.</p>
            </div>
        </div>
    </div>
</section>

<div class="page-content">
<div class="grid-container" style="padding-top: 2rem; margin-bottom: 2rem;">

<?php if ($successMessage): ?>
    <div class="grid-x grid-margin-x">
        <div class="small-12 cell">
            <div class="callout success">
                <?php echo htmlspecialchars($successMessage, ENT_QUOTES, 'UTF-8'); ?>
            </div>
        </div>
    </div>
<?php endif; ?>

<?php if ($errorMessage): ?>
    <div class="grid-x grid-margin-x">
        <div class="small-12 cell">
            <div class="callout alert">
                <?php echo htmlspecialchars($errorMessage, ENT_QUOTES, 'UTF-8'); ?>
            </div>
        </div>
    </div>
<?php endif; ?>

<!-- Active Elections -->
<div class="grid-x grid-margin-x">
    <div class="small-12 cell">
        <div class="card arm-card">
            <div class="card-section">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem;">
                    <h3 style="margin: 0;">Active Elections</h3>
                    <a href="/admin/voting/add.php" class="button primary small" style="border-radius: 8px; margin: 0;">Create Election</a>
                </div>

                <?php if (empty($activeElections)): ?>
                    <div class="callout secondary">
                        <p>No active elections. Elections will appear here when they are open for voting.</p>
                    </div>
                <?php else: ?>
                    <div class="arm-card-table-wrapper">
                        <table class="hover" style="border-radius: 8px; overflow: hidden; border: 1px solid #e0e0e0;">
                            <thead>
                            <tr>
                                <th style="padding: 0.75rem;">Title</th>
                                <th style="padding: 0.75rem;">Voting Period</th>
                                <th class="text-center" style="padding: 0.75rem;">Candidates</th>
                                <th class="text-center" style="padding: 0.75rem;">Votes Cast</th>
                                <th class="text-center" style="padding: 0.75rem;">Status</th>
                                <th class="text-center" style="padding: 0.75rem;">Actions</th>
                            </tr>
                            </thead>
                            <tbody>
                            <?php foreach ($activeElections as $election): ?>
                                <tr>
                                    <td style="padding: 0.75rem;">
                                        <strong style="color: #1779ba;"><?php echo htmlspecialchars($election['title'], ENT_QUOTES, 'UTF-8'); ?></strong>
                                        <?php if ($election['max_selections'] > 1): ?>
                                            <br><small style="color: #666;">Vote for up to <?php echo (int)$election['max_selections']; ?></small>
                                        <?php endif; ?>
                                    </td>
                                    <td style="padding: 0.75rem;">
                                        <?php echo date('M j, Y g:i A', strtotime($election['opens_at'])); ?><br>
                                        <small>to <?php echo date('M j, Y g:i A', strtotime($election['closes_at'])); ?></small>
                                    </td>
                                    <td class="text-center" style="padding: 0.75rem;"><?php echo (int)$election['candidate_count']; ?></td>
                                    <td class="text-center" style="padding: 0.75rem;"><?php echo (int)$election['voter_count']; ?></td>
                                    <?php
                                    $electionOpensAt = new DateTime($election['opens_at'], new DateTimeZone('America/Phoenix'));
                                    $isVotingOpen = ($now >= $electionOpensAt);
                                    ?>
                                    <td class="text-center" style="padding: 0.75rem;">
                                        <?php if ($isVotingOpen): ?>
                                            <span class="label success">OPEN</span>
                                        <?php else: ?>
                                            <span class="label warning">SCHEDULED</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-center" style="padding: 0.75rem;">
                                        <a href="/admin/voting/results.php?id=<?php echo (int)$election['id']; ?>" style="color: #1779ba; text-decoration: none; font-weight: 500;">View Results</a>
                                        <span style="color: #ccc; margin: 0 0.25rem;">|</span>
                                        <a href="/admin/voting/handlers/close.php?id=<?php echo (int)$election['id']; ?>"
                                           style="color: #cc4b37; text-decoration: none; font-weight: 500;"
                                           onclick="return confirm('Are you sure you want to close this election early? No more votes will be accepted.');">Close</a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Draft Elections -->
<div class="grid-x grid-margin-x" style="margin-top: 2rem;">
    <div class="small-12 cell">
        <div class="card arm-card">
            <div class="card-section">
                <h3>Draft Elections</h3>

                <?php if (empty($draftElections)): ?>
                    <div class="callout secondary">
                        <p>No draft elections. Create a new election to get started.</p>
                    </div>
                <?php else: ?>
                    <div class="arm-card-table-wrapper">
                        <table class="hover" style="border-radius: 8px; overflow: hidden; border: 1px solid #e0e0e0;">
                            <thead>
                            <tr>
                                <th style="padding: 0.75rem;">Title</th>
                                <th style="padding: 0.75rem;">Planned Voting Period</th>
                                <th class="text-center" style="padding: 0.75rem;">Candidates</th>
                                <th style="padding: 0.75rem;">Created By</th>
                                <th class="text-center" style="padding: 0.75rem;">Status</th>
                                <th class="text-center" style="padding: 0.75rem;">Actions</th>
                            </tr>
                            </thead>
                            <tbody>
                            <?php foreach ($draftElections as $election): ?>
                                <tr>
                                    <td style="padding: 0.75rem;">
                                        <strong><?php echo htmlspecialchars($election['title'], ENT_QUOTES, 'UTF-8'); ?></strong>
                                        <?php if ($election['max_selections'] > 1): ?>
                                            <br><small style="color: #666;">Vote for up to <?php echo (int)$election['max_selections']; ?></small>
                                        <?php endif; ?>
                                    </td>
                                    <td style="padding: 0.75rem;">
                                        <?php echo date('M j, Y g:i A', strtotime($election['opens_at'])); ?><br>
                                        <small>to <?php echo date('M j, Y g:i A', strtotime($election['closes_at'])); ?></small>
                                    </td>
                                    <td class="text-center" style="padding: 0.75rem;"><?php echo (int)$election['candidate_count']; ?></td>
                                    <td style="padding: 0.75rem;">
                                        <?php echo htmlspecialchars($election['creator_first_name'] . ' ' . $election['creator_last_name'], ENT_QUOTES, 'UTF-8'); ?>
                                    </td>
                                    <td class="text-center" style="padding: 0.75rem;">
                                        <span class="label secondary">DRAFT</span>
                                    </td>
                                    <td class="text-center" style="padding: 0.75rem;">
                                        <a href="/admin/voting/edit.php?id=<?php echo (int)$election['id']; ?>" style="color: #1779ba; text-decoration: none; font-weight: 500;">Edit</a>
                                        <span style="color: #ccc; margin: 0 0.25rem;">|</span>
                                        <a href="/admin/voting/handlers/publish.php?id=<?php echo (int)$election['id']; ?>"
                                           style="color: #3adb76; text-decoration: none; font-weight: 500;"
                                           onclick="return confirm('Publish this election? It will be open for voting during the scheduled period.');">Publish</a>
                                        <span style="color: #ccc; margin: 0 0.25rem;">|</span>
                                        <a href="/admin/voting/handlers/delete.php?id=<?php echo (int)$election['id']; ?>"
                                           style="color: #cc4b37; text-decoration: none; font-weight: 500;"
                                           onclick="return confirm('Are you sure you want to delete this election? This cannot be undone.');">Delete</a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Closed Elections -->
<div class="grid-x grid-margin-x" style="margin-top: 2rem;">
    <div class="small-12 cell">
        <div class="card arm-card">
            <div class="card-section">
                <h3>Closed Elections</h3>

                <?php if (empty($closedElections)): ?>
                    <div class="callout secondary">
                        <p>No closed elections yet.</p>
                    </div>
                <?php else: ?>
                    <div class="arm-card-table-wrapper">
                        <table class="hover" style="border-radius: 8px; overflow: hidden; border: 1px solid #e0e0e0;">
                            <thead>
                            <tr>
                                <th style="padding: 0.75rem;">Title</th>
                                <th style="padding: 0.75rem;">Voting Period</th>
                                <th class="text-center" style="padding: 0.75rem;">Total Votes</th>
                                <th class="text-center" style="padding: 0.75rem;">Actions</th>
                            </tr>
                            </thead>
                            <tbody>
                            <?php foreach ($closedElections as $election): ?>
                                <tr style="opacity: 0.8;">
                                    <td style="padding: 0.75rem;">
                                        <strong><?php echo htmlspecialchars($election['title'], ENT_QUOTES, 'UTF-8'); ?></strong>
                                    </td>
                                    <td style="padding: 0.75rem;">
                                        <?php echo date('M j, Y', strtotime($election['opens_at'])); ?> -
                                        <?php echo date('M j, Y', strtotime($election['closes_at'])); ?>
                                    </td>
                                    <td class="text-center" style="padding: 0.75rem;"><?php echo (int)$election['voter_count']; ?> voters</td>
                                    <td class="text-center" style="padding: 0.75rem;">
                                        <a href="/admin/voting/results.php?id=<?php echo (int)$election['id']; ?>" style="color: #1779ba; text-decoration: none; font-weight: 500;">View Results</a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../../assets/footer.php'; ?>
