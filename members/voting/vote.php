<?php
session_start();
require_once __DIR__ . '/../../config/db.php';
$pdo = getDbConnection();

// Set timezone to MST (Arizona)
date_default_timezone_set('America/Phoenix');

// Require login
if (!isset($_SESSION['user_id'])) {
    header('Location: /login.php');
    exit;
}

$userId = (int)$_SESSION['user_id'];
$electionId = (int)($_GET['id'] ?? 0);

if ($electionId < 1) {
    $_SESSION['error_message'] = 'Invalid election.';
    header('Location: /members/voting/');
    exit;
}

// Check if user is an active member
$memberStmt = $pdo->prepare("
    SELECT id, first_name, last_name, membership_status, membership_expires_at
    FROM members
    WHERE id = :id
");
$memberStmt->execute([':id' => $userId]);
$member = $memberStmt->fetch();

$isActiveMember = (
    $member &&
    $member['membership_status'] !== 'inactive' &&
    $member['membership_expires_at'] &&
    strtotime($member['membership_expires_at']) > strtotime('today')
);

if (!$isActiveMember) {
    $_SESSION['error_message'] = 'You must be an active member to vote.';
    header('Location: /members/voting/');
    exit;
}

// Fetch election
$now = date('Y-m-d H:i:s');
$electionStmt = $pdo->prepare("
    SELECT *
    FROM elections
    WHERE id = :id
    AND status = 'open'
    AND opens_at <= :now_open
    AND closes_at >= :now_close
");
$electionStmt->execute([':id' => $electionId, ':now_open' => $now, ':now_close' => $now]);
$election = $electionStmt->fetch();

if (!$election) {
    $_SESSION['error_message'] = 'Election not found or is not currently open for voting.';
    header('Location: /members/voting/');
    exit;
}

// Check if user has already voted
$votedStmt = $pdo->prepare("
    SELECT id FROM election_voters
    WHERE election_id = :election_id AND member_id = :member_id
");
$votedStmt->execute([':election_id' => $electionId, ':member_id' => $userId]);
if ($votedStmt->fetch()) {
    $_SESSION['error_message'] = 'You have already voted in this election.';
    header('Location: /members/voting/');
    exit;
}

// Fetch candidates
$candidatesStmt = $pdo->prepare("
    SELECT id, candidate_name, candidate_description, display_order
    FROM election_candidates
    WHERE election_id = :election_id
    ORDER BY display_order ASC
");
$candidatesStmt->execute([':election_id' => $electionId]);
$candidates = $candidatesStmt->fetchAll();

$errors = [];

// Handle vote submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $selectedCandidates = $_POST['candidates'] ?? [];
    $writeInName = trim($_POST['write_in_name'] ?? '');
    $hasWriteIn = ($writeInName !== '');

    // Count total selections (candidates + write-in if provided)
    $totalSelections = count($selectedCandidates) + ($hasWriteIn ? 1 : 0);

    // Validate selection
    if ($totalSelections === 0) {
        $errors[] = 'Please select at least one candidate or enter a write-in.';
    } elseif ($totalSelections > $election['max_selections']) {
        $errors[] = "You can only vote for up to {$election['max_selections']} candidate(s) total (including write-in).";
    }

    // Validate that selected candidates are valid
    $validCandidateIds = array_column($candidates, 'id');
    foreach ($selectedCandidates as $candidateId) {
        if (!in_array((int)$candidateId, $validCandidateIds)) {
            $errors[] = 'Invalid candidate selection.';
            break;
        }
    }

    // Validate write-in name if provided
    if ($hasWriteIn && strlen($writeInName) > 255) {
        $errors[] = 'Write-in candidate name is too long (max 255 characters).';
    }

    if (empty($errors)) {
        $pdo->beginTransaction();

        try {
            // Record that this member voted (for preventing double voting)
            $voterSql = "INSERT INTO election_voters
                            (election_id, member_id, voted_at, ip_address, user_agent)
                         VALUES
                            (:election_id, :member_id, NOW(), :ip_address, :user_agent)";
            $voterStmt = $pdo->prepare($voterSql);
            $voterStmt->execute([
                ':election_id' => $electionId,
                ':member_id' => $userId,
                ':ip_address' => $_SERVER['REMOTE_ADDR'] ?? null,
                ':user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? null
            ]);

            // Record the anonymous votes for listed candidates (not linked to voter)
            if (!empty($selectedCandidates)) {
                $voteSql = "INSERT INTO election_votes (election_id, candidate_id, voted_at)
                            VALUES (:election_id, :candidate_id, NOW())";
                $voteStmt = $pdo->prepare($voteSql);

                foreach ($selectedCandidates as $candidateId) {
                    $voteStmt->execute([
                        ':election_id' => $electionId,
                        ':candidate_id' => (int)$candidateId
                    ]);
                }
            }

            // Record write-in vote if provided
            if ($hasWriteIn) {
                $writeInSql = "INSERT INTO election_write_ins (election_id, write_in_name, voted_at)
                               VALUES (:election_id, :write_in_name, NOW())";
                $writeInStmt = $pdo->prepare($writeInSql);
                $writeInStmt->execute([
                    ':election_id' => $electionId,
                    ':write_in_name' => $writeInName
                ]);
            }

            // Log activity (generic, not revealing vote choices)
            $activity_sql = "INSERT INTO activity_logs (
                                user_id, action_type, entity_type, entity_id,
                                description, ip_address, user_agent
                             ) VALUES (
                                :user_id, :action_type, :entity_type, :entity_id,
                                :description, :ip_address, :user_agent
                             )";
            $activity_stmt = $pdo->prepare($activity_sql);
            $activity_stmt->execute([
                ':user_id' => $userId,
                ':action_type' => 'vote_cast',
                ':entity_type' => 'election',
                ':entity_id' => $electionId,
                ':description' => "Voted in election: {$election['title']}",
                ':ip_address' => $_SERVER['REMOTE_ADDR'] ?? null,
                ':user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? null
            ]);

            $pdo->commit();

            $_SESSION['success_message'] = "Your vote has been recorded. Thank you for participating in \"{$election['title']}\"!";
            header('Location: /members/voting/');
            exit;

        } catch (Exception $e) {
            $pdo->rollBack();
            error_log('Vote submission error: ' . $e->getMessage());
            $errors[] = 'An error occurred while recording your vote. Please try again.';
        }
    }
}

$page_title = 'Cast Your Vote | Arizona Railway Museum';
require_once __DIR__ . '/../../assets/header.php';
?>

</div></div><!-- Close grid-container and page-content for full-width hero -->

<section class="arm-hero" style="margin-top: -4.5rem; padding: 2.5rem 0 2rem;">
    <div class="grid-container">
        <div class="grid-x grid-margin-x align-middle">
            <div class="small-12 cell">
                <h1 style="margin-bottom: 0.25rem;"><?php echo htmlspecialchars($election['title'], ENT_QUOTES, 'UTF-8'); ?></h1>
                <p class="lead" style="margin-bottom: 0;">
                    Vote for up to <strong><?php echo (int)$election['max_selections']; ?></strong> candidate<?php echo $election['max_selections'] > 1 ? 's' : ''; ?>
                </p>
            </div>
        </div>
    </div>
</section>

<div class="page-content">
<div class="grid-container" style="padding-top: 2rem; margin-bottom: 2rem;">

<div class="grid-x grid-margin-x">
    <div class="small-12 medium-8 cell">
        <?php if (!empty($errors)): ?>
            <div class="callout alert">
                <h5>There were some problems:</h5>
                <ul>
                    <?php foreach ($errors as $error): ?>
                        <li><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <?php if ($election['description']): ?>
            <div class="callout secondary" style="border-radius: 8px; margin-bottom: 1.5rem;">
                <?php echo nl2br(htmlspecialchars($election['description'], ENT_QUOTES, 'UTF-8')); ?>
            </div>
        <?php endif; ?>

        <form method="post" action="" id="vote-form">
            <div class="card arm-card">
                <div class="card-section">
                    <h3>Select Your Candidate<?php echo $election['max_selections'] > 1 ? 's' : ''; ?></h3>

                    <?php foreach ($candidates as $candidate): ?>
                        <label class="candidate-option" style="display: block; padding: 1rem; margin-bottom: 0.5rem; background: #f8f9fa; border-radius: 8px; cursor: pointer; border: 2px solid transparent; transition: all 0.2s;">
                            <div style="display: flex; align-items: flex-start;">
                                <input type="<?php echo $election['max_selections'] > 1 ? 'checkbox' : 'radio'; ?>"
                                       name="candidates[]"
                                       value="<?php echo (int)$candidate['id']; ?>"
                                       style="margin-top: 0.25rem; margin-right: 1rem;">
                                <div>
                                    <strong style="font-size: 1.1rem;"><?php echo htmlspecialchars($candidate['candidate_name'], ENT_QUOTES, 'UTF-8'); ?></strong>
                                    <?php if ($candidate['candidate_description']): ?>
                                        <p style="margin: 0.25rem 0 0 0; color: #666; font-size: 0.9rem;">
                                            <?php echo htmlspecialchars($candidate['candidate_description'], ENT_QUOTES, 'UTF-8'); ?>
                                        </p>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </label>
                    <?php endforeach; ?>

                    <!-- Write-in Candidate -->
                    <div class="write-in-section" style="margin-top: 1.5rem; padding-top: 1rem; border-top: 2px dashed #ccc;">
                        <label style="display: block; margin-bottom: 0.5rem;">
                            <strong>Write-in Candidate</strong>
                            <span style="color: #666; font-size: 0.9rem;">(optional)</span>
                        </label>
                        <input type="text" name="write_in_name" id="write-in-name"
                               placeholder="Enter a write-in candidate name"
                               maxlength="255"
                               value="<?php echo htmlspecialchars($_POST['write_in_name'] ?? '', ENT_QUOTES, 'UTF-8'); ?>"
                               style="border-radius: 8px;">
                        <p class="help-text" style="margin-top: 0.25rem; color: #666; font-size: 0.85rem;">
                            A write-in counts as one of your selections.
                        </p>
                    </div>

                    <div id="selection-count" style="margin-top: 1rem; padding: 0.75rem; background: #e3f2fd; border-radius: 8px; text-align: center;">
                        <span id="count-text">Select up to <?php echo (int)$election['max_selections']; ?> candidate<?php echo $election['max_selections'] > 1 ? 's' : ''; ?></span>
                    </div>
                </div>
            </div>

            <div class="callout warning" style="margin-top: 1.5rem; border-radius: 8px;">
                <p style="margin: 0;"><strong>Important:</strong> Once you submit your vote, it cannot be changed.
                Your vote is anonymous - we record that you participated, but not your specific choices.</p>
            </div>

            <div class="grid-x grid-margin-x" style="margin-top: 1.5rem;">
                <div class="small-12 medium-6 cell">
                    <a href="/members/voting/" class="button secondary expanded" style="border-radius: 8px;">Cancel</a>
                </div>
                <div class="small-12 medium-6 cell">
                    <button type="submit" id="submit-btn" class="button primary expanded" style="border-radius: 8px;">
                        Submit My Vote
                    </button>
                </div>
            </div>
        </form>
    </div>

    <div class="small-12 medium-4 cell">
        <div class="callout" style="border-radius: 8px; background: #f0f0f0;">
            <h5>Voting Information</h5>
            <table style="width: 100%; font-size: 0.875rem;">
                <tr>
                    <td style="padding: 0.25rem 0; color: #666;">Voting As:</td>
                    <td style="padding: 0.25rem 0; text-align: right;">
                        <?php echo htmlspecialchars($member['first_name'] . ' ' . $member['last_name'], ENT_QUOTES, 'UTF-8'); ?>
                    </td>
                </tr>
                <tr>
                    <td style="padding: 0.25rem 0; color: #666;">Voting Closes:</td>
                    <td style="padding: 0.25rem 0; text-align: right;">
                        <?php echo date('M j, Y', strtotime($election['closes_at'])); ?><br>
                        <small><?php echo date('g:i A', strtotime($election['closes_at'])); ?></small>
                    </td>
                </tr>
                <tr>
                    <td style="padding: 0.25rem 0; color: #666;">Max Selections:</td>
                    <td style="padding: 0.25rem 0; text-align: right;"><?php echo (int)$election['max_selections']; ?></td>
                </tr>
            </table>
        </div>
    </div>
</div>

<style>
.candidate-option:hover {
    background: #e3f2fd !important;
}
.candidate-option.selected {
    border-color: #1779ba !important;
    background: #e3f2fd !important;
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const form = document.getElementById('vote-form');
    const checkboxes = form.querySelectorAll('input[type="checkbox"], input[type="radio"]');
    const countText = document.getElementById('count-text');
    const submitBtn = document.getElementById('submit-btn');
    const writeInInput = document.getElementById('write-in-name');
    const maxSelections = <?php echo (int)$election['max_selections']; ?>;

    function updateCount() {
        const checked = form.querySelectorAll('input[name="candidates[]"]:checked');
        const hasWriteIn = writeInInput.value.trim() !== '';
        const count = checked.length + (hasWriteIn ? 1 : 0);

        // Update visual selection state for checkboxes
        checkboxes.forEach(cb => {
            const label = cb.closest('.candidate-option');
            if (label) {
                if (cb.checked) {
                    label.classList.add('selected');
                } else {
                    label.classList.remove('selected');
                }
            }
        });

        // Update count text
        if (count === 0) {
            countText.textContent = `Select up to ${maxSelections} candidate${maxSelections > 1 ? 's' : ''}`;
            countText.parentElement.style.background = '#e3f2fd';
        } else if (count <= maxSelections) {
            const writeInNote = hasWriteIn ? ' (includes write-in)' : '';
            countText.textContent = `${count} of ${maxSelections} selected${writeInNote}`;
            countText.parentElement.style.background = '#c8e6c9';
        } else {
            countText.textContent = `Too many selected (${count}/${maxSelections})`;
            countText.parentElement.style.background = '#ffcdd2';
        }

        // Disable unchecked checkboxes if at max (only for checkboxes)
        if (maxSelections > 1) {
            checkboxes.forEach(cb => {
                const label = cb.closest('.candidate-option');
                if (label) {
                    if (!cb.checked && count >= maxSelections) {
                        cb.disabled = true;
                        label.style.opacity = '0.5';
                    } else {
                        cb.disabled = false;
                        label.style.opacity = '1';
                    }
                }
            });
        }

        // Disable write-in if at max and no write-in entered
        if (count >= maxSelections && !hasWriteIn) {
            writeInInput.disabled = true;
            writeInInput.style.opacity = '0.5';
        } else {
            writeInInput.disabled = false;
            writeInInput.style.opacity = '1';
        }
    }

    checkboxes.forEach(cb => {
        cb.addEventListener('change', updateCount);
    });

    writeInInput.addEventListener('input', updateCount);

    // Initialize on page load
    updateCount();

    // Confirm before submit
    form.addEventListener('submit', function(e) {
        const checked = form.querySelectorAll('input[name="candidates[]"]:checked').length;
        const hasWriteIn = writeInInput.value.trim() !== '';
        const totalSelections = checked + (hasWriteIn ? 1 : 0);

        if (totalSelections === 0) {
            e.preventDefault();
            alert('Please select at least one candidate or enter a write-in.');
            return;
        }
        if (totalSelections > maxSelections) {
            e.preventDefault();
            alert(`You can only vote for up to ${maxSelections} candidate(s) total.`);
            return;
        }
        if (!confirm('Are you sure you want to submit your vote? This cannot be changed.')) {
            e.preventDefault();
        }
    });
});
</script>

<?php require_once __DIR__ . '/../../assets/footer.php'; ?>
