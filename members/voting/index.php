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

// Check if user is an active member
$memberStmt = $pdo->prepare("
    SELECT id, membership_status, membership_expires_at
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

// Fetch all open elections
$now = date('Y-m-d H:i:s');
$electionsStmt = $pdo->prepare("
    SELECT
        e.id,
        e.title,
        e.description,
        e.max_selections,
        e.opens_at,
        e.closes_at,
        (SELECT COUNT(*) FROM election_candidates WHERE election_id = e.id) as candidate_count,
        (SELECT COUNT(*) FROM election_voters WHERE election_id = e.id AND member_id = :member_id) as has_voted
    FROM elections e
    WHERE e.status = 'open'
    AND e.opens_at <= :now_open
    AND e.closes_at >= :now_close
    ORDER BY e.closes_at ASC
");
$electionsStmt->execute([':member_id' => $userId, ':now_open' => $now, ':now_close' => $now]);
$elections = $electionsStmt->fetchAll();

// Check for session messages
$successMessage = $_SESSION['success_message'] ?? null;
$errorMessage = $_SESSION['error_message'] ?? null;
unset($_SESSION['success_message'], $_SESSION['error_message']);

$page_title = 'Member Voting | Arizona Railway Museum';
require_once __DIR__ . '/../../assets/header.php';
?>

</div></div><!-- Close grid-container and page-content for full-width hero -->

<section class="arm-hero" style="margin-top: -4.5rem; padding: 2.5rem 0 2rem;">
    <div class="grid-container">
        <div class="grid-x grid-margin-x align-middle">
            <div class="small-12 cell">
                <h1 style="margin-bottom: 0.25rem;">Member Voting</h1>
                <p class="lead" style="margin-bottom: 0;">Participate in board elections and member ballots.</p>
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

<?php if (!$isActiveMember): ?>
    <div class="grid-x grid-margin-x">
        <div class="small-12 cell">
            <div class="callout warning">
                <h4>Active Membership Required</h4>
                <p>You must be an active member with a valid, non-expired membership to vote in elections.</p>
                <?php if ($member['membership_status'] === 'inactive'): ?>
                    <p><a href="/membership/apply.php" class="button primary" style="border-radius: 8px;">Apply for Membership</a></p>
                <?php elseif ($member['membership_expires_at'] && strtotime($member['membership_expires_at']) <= strtotime('today')): ?>
                    <p>Your membership expired on <?php echo date('M j, Y', strtotime($member['membership_expires_at'])); ?>. Please renew your membership to vote.</p>
                <?php endif; ?>
            </div>
        </div>
    </div>
<?php elseif (empty($elections)): ?>
    <div class="grid-x grid-margin-x">
        <div class="small-12 cell">
            <div class="callout secondary">
                <h4>No Active Elections</h4>
                <p>There are currently no open elections. Check back later when voting is open.</p>
            </div>
        </div>
    </div>
<?php else: ?>
    <div class="grid-x grid-margin-x">
        <div class="small-12 cell">
            <p style="color: #666; margin-bottom: 1.5rem;">
                You have <strong><?php echo count($elections); ?></strong> active election<?php echo count($elections) !== 1 ? 's' : ''; ?> to participate in.
                Your votes are anonymous - we record that you voted, but not your specific choices.
            </p>
        </div>
    </div>

    <?php foreach ($elections as $election): ?>
        <div class="grid-x grid-margin-x" style="margin-bottom: 1.5rem;">
            <div class="small-12 cell">
                <div class="card arm-card" style="<?php echo $election['has_voted'] ? 'border-left: 4px solid #3adb76;' : 'border-left: 4px solid #1779ba;'; ?>">
                    <div class="card-section">
                        <div class="grid-x grid-margin-x">
                            <div class="small-12 medium-8 cell">
                                <h3 style="margin-bottom: 0.5rem;">
                                    <?php echo htmlspecialchars($election['title'], ENT_QUOTES, 'UTF-8'); ?>
                                </h3>
                                <?php if ($election['description']): ?>
                                    <p style="color: #666; margin-bottom: 0.5rem;">
                                        <?php echo htmlspecialchars($election['description'], ENT_QUOTES, 'UTF-8'); ?>
                                    </p>
                                <?php endif; ?>
                                <p style="margin-bottom: 0; font-size: 0.875rem; color: #666;">
                                    <strong><?php echo (int)$election['candidate_count']; ?></strong> candidates
                                    &bull;
                                    Vote for up to <strong><?php echo (int)$election['max_selections']; ?></strong>
                                    &bull;
                                    Closes <?php echo date('M j, Y \a\t g:i A', strtotime($election['closes_at'])); ?>
                                </p>
                            </div>
                            <div class="small-12 medium-4 cell text-right" style="display: flex; align-items: center; justify-content: flex-end;">
                                <?php if ($election['has_voted']): ?>
                                    <span class="label success" style="padding: 0.5rem 1rem; font-size: 1rem;">
                                        Vote Submitted
                                    </span>
                                <?php else: ?>
                                    <a href="/members/voting/vote.php?id=<?php echo (int)$election['id']; ?>"
                                       class="button primary" style="border-radius: 8px; margin: 0;">
                                        Cast Your Vote
                                    </a>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    <?php endforeach; ?>
<?php endif; ?>

<div class="grid-x grid-margin-x" style="margin-top: 2rem;">
    <div class="small-12 cell">
        <a href="/members/" class="button secondary" style="border-radius: 8px;">Back to Member Dashboard</a>
    </div>
</div>

<?php require_once __DIR__ . '/../../assets/footer.php'; ?>
