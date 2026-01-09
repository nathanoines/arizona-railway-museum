<?php
session_start();
require_once __DIR__ . '/../../config/db.php';
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
$stmt = $pdo->prepare("
    SELECT e.*, m.first_name as creator_first_name, m.last_name as creator_last_name
    FROM elections e
    LEFT JOIN members m ON e.created_by_id = m.id
    WHERE e.id = :id
");
$stmt->execute([':id' => $electionId]);
$election = $stmt->fetch();

if (!$election) {
    $_SESSION['error_message'] = 'Election not found.';
    header('Location: /admin/voting/');
    exit;
}

// Fetch candidates with vote counts
$candidateStmt = $pdo->prepare("
    SELECT
        c.id,
        c.candidate_name,
        c.candidate_description,
        c.display_order,
        COUNT(v.id) as vote_count
    FROM election_candidates c
    LEFT JOIN election_votes v ON c.id = v.candidate_id
    WHERE c.election_id = :election_id
    GROUP BY c.id
    ORDER BY vote_count DESC, c.display_order ASC
");
$candidateStmt->execute([':election_id' => $electionId]);
$candidates = $candidateStmt->fetchAll();

// Total unique voters
$voterStmt = $pdo->prepare("SELECT COUNT(*) as total FROM election_voters WHERE election_id = :id");
$voterStmt->execute([':id' => $electionId]);
$totalVoters = (int)$voterStmt->fetch()['total'];

// Fetch write-in votes (grouped by name, case-insensitive)
$writeInStmt = $pdo->prepare("
    SELECT MIN(write_in_name) as write_in_name, COUNT(*) as vote_count
    FROM election_write_ins
    WHERE election_id = :election_id
    GROUP BY LOWER(write_in_name)
    ORDER BY vote_count DESC, MIN(write_in_name) ASC
");
$writeInStmt->execute([':election_id' => $electionId]);
$writeIns = $writeInStmt->fetchAll();

// Total write-in votes
$totalWriteInVotes = array_sum(array_column($writeIns, 'vote_count'));

// Total votes cast (candidates + write-ins)
$totalCandidateVotes = array_sum(array_column($candidates, 'vote_count'));
$totalVotes = $totalCandidateVotes + $totalWriteInVotes;

// Get eligible voters count (active members)
$eligibleStmt = $pdo->query("
    SELECT COUNT(*) as total
    FROM members
    WHERE membership_status != 'inactive'
    AND membership_expires_at > CURDATE()
");
$eligibleVoters = (int)$eligibleStmt->fetch()['total'];

// Calculate turnout
$turnout = $eligibleVoters > 0 ? round(($totalVoters / $eligibleVoters) * 100, 1) : 0;

// Determine election status
$now = new DateTime('now', new DateTimeZone('America/Phoenix'));
$opensAt = new DateTime($election['opens_at'], new DateTimeZone('America/Phoenix'));
$closesAt = new DateTime($election['closes_at'], new DateTimeZone('America/Phoenix'));

$isActive = ($election['status'] === 'open' && $now >= $opensAt && $now <= $closesAt);
$isClosed = ($election['status'] === 'closed' || $now > $closesAt);
$isPending = ($election['status'] === 'open' && $now < $opensAt);

// Get recent voters (last 10, without revealing how they voted)
$recentVotersStmt = $pdo->prepare("
    SELECT ev.voted_at, m.first_name, m.last_name
    FROM election_voters ev
    JOIN members m ON ev.member_id = m.id
    WHERE ev.election_id = :election_id
    ORDER BY ev.voted_at DESC
    LIMIT 10
");
$recentVotersStmt->execute([':election_id' => $electionId]);
$recentVoters = $recentVotersStmt->fetchAll();

$page_title = 'Election Results | Arizona Railway Museum';
require_once __DIR__ . '/../../assets/header.php';
?>

</div></div><!-- Close grid-container and page-content for full-width hero -->

<section class="arm-hero" style="margin-top: -4.5rem; padding: 2.5rem 0 2rem;">
    <div class="grid-container">
        <div class="grid-x grid-margin-x align-middle">
            <div class="small-12 cell">
                <h1 style="margin-bottom: 0.25rem;"><?php echo htmlspecialchars($election['title'], ENT_QUOTES, 'UTF-8'); ?></h1>
                <p class="lead" style="margin-bottom: 0;">
                    <?php if ($isActive): ?>
                        <span class="label success">VOTING OPEN</span>
                    <?php elseif ($isClosed): ?>
                        <span class="label secondary">CLOSED</span>
                    <?php elseif ($isPending): ?>
                        <span class="label warning">PENDING</span>
                    <?php else: ?>
                        <span class="label secondary">DRAFT</span>
                    <?php endif; ?>
                    &nbsp;Election Results
                </p>
            </div>
        </div>
    </div>
</section>

<div class="page-content">
<div class="grid-container" style="padding-top: 2rem; margin-bottom: 2rem;">

<!-- Summary Stats -->
<div class="grid-x grid-margin-x">
    <div class="small-6 medium-3 cell">
        <div class="card arm-card text-center" style="padding: 1rem;">
            <h2 style="margin: 0; color: #1779ba;"><?php echo $totalVoters; ?></h2>
            <p style="margin: 0; color: #666;">Voters</p>
        </div>
    </div>
    <div class="small-6 medium-3 cell">
        <div class="card arm-card text-center" style="padding: 1rem;">
            <h2 style="margin: 0; color: #1779ba;"><?php echo $totalVotes; ?></h2>
            <p style="margin: 0; color: #666;">Total Votes</p>
        </div>
    </div>
    <div class="small-6 medium-3 cell">
        <div class="card arm-card text-center" style="padding: 1rem;">
            <h2 style="margin: 0; color: #1779ba;"><?php echo $eligibleVoters; ?></h2>
            <p style="margin: 0; color: #666;">Eligible Members</p>
        </div>
    </div>
    <div class="small-6 medium-3 cell">
        <div class="card arm-card text-center" style="padding: 1rem;">
            <h2 style="margin: 0; color: <?php echo $turnout >= 50 ? '#3adb76' : ($turnout >= 25 ? '#ffae00' : '#cc4b37'); ?>;"><?php echo $turnout; ?>%</h2>
            <p style="margin: 0; color: #666;">Turnout</p>
        </div>
    </div>
</div>

<div class="grid-x grid-margin-x" style="margin-top: 2rem;">
    <!-- Results -->
    <div class="small-12 medium-8 cell">
        <div class="card arm-card">
            <div class="card-section">
                <h3>Results by Candidate</h3>

                <?php if (empty($candidates)): ?>
                    <div class="callout secondary">
                        <p>No candidates in this election.</p>
                    </div>
                <?php else: ?>
                    <?php
                    $maxVotes = max(array_column($candidates, 'vote_count'));
                    $rank = 0;
                    $lastVotes = -1;
                    foreach ($candidates as $index => $candidate):
                        if ($candidate['vote_count'] != $lastVotes) {
                            $rank = $index + 1;
                            $lastVotes = $candidate['vote_count'];
                        }
                        $percentage = $totalVotes > 0 ? round(($candidate['vote_count'] / $totalVotes) * 100, 1) : 0;
                        $barWidth = $maxVotes > 0 ? ($candidate['vote_count'] / $maxVotes) * 100 : 0;
                        $isWinner = ($rank <= $election['max_selections'] && $isClosed && $candidate['vote_count'] > 0);
                    ?>
                        <div style="margin-bottom: 1.5rem; <?php echo $isWinner ? 'background: #e8f5e9; padding: 1rem; border-radius: 8px; border-left: 4px solid #3adb76;' : ''; ?>">
                            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.5rem;">
                                <div>
                                    <span style="color: #666; font-size: 0.875rem;">#<?php echo $rank; ?></span>
                                    <strong style="margin-left: 0.5rem; <?php echo $isWinner ? 'color: #1b5e20;' : ''; ?>">
                                        <?php echo htmlspecialchars($candidate['candidate_name'], ENT_QUOTES, 'UTF-8'); ?>
                                    </strong>
                                    <?php if ($isWinner): ?>
                                        <span class="label success" style="margin-left: 0.5rem;">ELECTED</span>
                                    <?php endif; ?>
                                </div>
                                <div style="text-align: right;">
                                    <strong><?php echo (int)$candidate['vote_count']; ?></strong>
                                    <span style="color: #666; font-size: 0.875rem;">(<?php echo $percentage; ?>%)</span>
                                </div>
                            </div>
                            <?php if ($candidate['candidate_description']): ?>
                                <p style="margin: 0 0 0.5rem 1.5rem; color: #666; font-size: 0.875rem;">
                                    <?php echo htmlspecialchars($candidate['candidate_description'], ENT_QUOTES, 'UTF-8'); ?>
                                </p>
                            <?php endif; ?>
                            <div style="background: #e0e0e0; height: 8px; border-radius: 4px; overflow: hidden;">
                                <div style="background: <?php echo $isWinner ? '#3adb76' : '#1779ba'; ?>; width: <?php echo $barWidth; ?>%; height: 100%;"></div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>

                <?php if (!empty($writeIns)): ?>
                    <!-- Write-in Votes Section -->
                    <div style="margin-top: 2rem; padding-top: 1.5rem; border-top: 2px dashed #ccc;">
                        <h4 style="color: #666;">Write-in Candidates</h4>
                        <p style="font-size: 0.875rem; color: #888; margin-bottom: 1rem;">
                            <?php echo $totalWriteInVotes; ?> write-in vote<?php echo $totalWriteInVotes !== 1 ? 's' : ''; ?> cast
                        </p>
                        <?php
                        $writeInMax = !empty($writeIns) ? max(array_column($writeIns, 'vote_count')) : 0;
                        foreach ($writeIns as $writeIn):
                            $writeInPercentage = $totalVotes > 0 ? round(($writeIn['vote_count'] / $totalVotes) * 100, 1) : 0;
                            $writeInBarWidth = $writeInMax > 0 ? ($writeIn['vote_count'] / $writeInMax) * 100 : 0;
                        ?>
                            <div style="margin-bottom: 1rem; padding: 0.75rem; background: #f5f5f5; border-radius: 8px; border-left: 3px solid #9e9e9e;">
                                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.25rem;">
                                    <div>
                                        <span class="label secondary" style="font-size: 0.7rem; margin-right: 0.5rem;">WRITE-IN</span>
                                        <strong style="color: #555;"><?php echo htmlspecialchars($writeIn['write_in_name'], ENT_QUOTES, 'UTF-8'); ?></strong>
                                    </div>
                                    <div style="text-align: right;">
                                        <strong><?php echo (int)$writeIn['vote_count']; ?></strong>
                                        <span style="color: #666; font-size: 0.875rem;">(<?php echo $writeInPercentage; ?>%)</span>
                                    </div>
                                </div>
                                <div style="background: #e0e0e0; height: 6px; border-radius: 3px; overflow: hidden;">
                                    <div style="background: #9e9e9e; width: <?php echo $writeInBarWidth; ?>%; height: 100%;"></div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Sidebar -->
    <div class="small-12 medium-4 cell">
        <!-- Election Details -->
        <div class="card arm-card">
            <div class="card-section">
                <h4>Election Details</h4>
                <?php if ($election['description']): ?>
                    <p><?php echo nl2br(htmlspecialchars($election['description'], ENT_QUOTES, 'UTF-8')); ?></p>
                <?php endif; ?>

                <table style="width: 100%; font-size: 0.875rem;">
                    <tr>
                        <td style="padding: 0.25rem 0; color: #666;">Max Selections:</td>
                        <td style="padding: 0.25rem 0; text-align: right;"><?php echo $election['max_selections']; ?></td>
                    </tr>
                    <tr>
                        <td style="padding: 0.25rem 0; color: #666;">Opened:</td>
                        <td style="padding: 0.25rem 0; text-align: right;"><?php echo date('M j, Y g:i A', strtotime($election['opens_at'])); ?></td>
                    </tr>
                    <tr>
                        <td style="padding: 0.25rem 0; color: #666;">Closes:</td>
                        <td style="padding: 0.25rem 0; text-align: right;"><?php echo date('M j, Y g:i A', strtotime($election['closes_at'])); ?></td>
                    </tr>
                    <tr>
                        <td style="padding: 0.25rem 0; color: #666;">Created By:</td>
                        <td style="padding: 0.25rem 0; text-align: right;">
                            <?php echo htmlspecialchars($election['creator_first_name'] . ' ' . $election['creator_last_name'], ENT_QUOTES, 'UTF-8'); ?>
                        </td>
                    </tr>
                </table>

                <?php if ($isActive): ?>
                    <hr style="margin: 1rem 0;">
                    <a href="/admin/voting/handlers/close.php?id=<?php echo $electionId; ?>"
                       class="button alert small expanded" style="border-radius: 8px;"
                       onclick="return confirm('Are you sure you want to close this election early? No more votes will be accepted.');">
                        Close Election Early
                    </a>
                <?php endif; ?>
            </div>
        </div>

        <!-- Recent Voters -->
        <?php if (!empty($recentVoters)): ?>
            <div class="card arm-card" style="margin-top: 1rem;">
                <div class="card-section">
                    <h4>Recent Voters</h4>
                    <p style="font-size: 0.875rem; color: #666; margin-bottom: 1rem;">
                        Shows who voted (not their choices)
                    </p>
                    <ul style="margin: 0; padding: 0; list-style: none; font-size: 0.875rem;">
                        <?php foreach ($recentVoters as $voter): ?>
                            <li style="padding: 0.25rem 0; border-bottom: 1px solid #eee;">
                                <?php echo htmlspecialchars($voter['first_name'] . ' ' . $voter['last_name'], ENT_QUOTES, 'UTF-8'); ?>
                                <span style="color: #999; float: right;">
                                    <?php echo date('M j g:i A', strtotime($voter['voted_at'])); ?>
                                </span>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            </div>
        <?php endif; ?>

        <div style="margin-top: 1rem;">
            <a href="/admin/voting/" class="button secondary expanded" style="border-radius: 8px;">Back to Voting Management</a>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../../assets/footer.php'; ?>
