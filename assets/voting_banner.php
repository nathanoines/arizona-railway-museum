<?php
/**
 * Voting Notification Banner
 *
 * Shows a notification banner for active members who haven't voted
 * in currently open elections. Include this file after the header-wrapper closes.
 *
 * Requires: $isLoggedIn to be set (from header.php)
 */

if (!isset($isLoggedIn) || !$isLoggedIn) {
    return;
}

// Set timezone to MST (Arizona)
date_default_timezone_set('America/Phoenix');

try {
    if (!isset($pdo)) {
        require_once __DIR__ . '/../config/db.php';
        $pdo = getDbConnection();
    }

    $votingBannerUserId = (int)$_SESSION['user_id'];

    // Check if user is an active member
    $memberCheck = $pdo->prepare("
        SELECT id FROM members
        WHERE id = :id
        AND membership_status != 'inactive'
        AND membership_expires_at > CURDATE()
    ");
    $memberCheck->execute([':id' => $votingBannerUserId]);
    $isActiveMemberForVoting = (bool)$memberCheck->fetch();

    if (!$isActiveMemberForVoting) {
        return;
    }

    // Check for open elections the user hasn't voted in yet
    $nowForVoting = date('Y-m-d H:i:s');
    $electionCheck = $pdo->prepare("
        SELECT e.id, e.title, e.closes_at
        FROM elections e
        WHERE e.status = 'open'
        AND e.opens_at <= :now_open
        AND e.closes_at >= :now_close
        AND NOT EXISTS (
            SELECT 1 FROM election_voters ev
            WHERE ev.election_id = e.id AND ev.member_id = :member_id
        )
        ORDER BY e.closes_at ASC
        LIMIT 1
    ");
    $electionCheck->execute([':now_open' => $nowForVoting, ':now_close' => $nowForVoting, ':member_id' => $votingBannerUserId]);
    $pendingElection = $electionCheck->fetch();

    if (!$pendingElection) {
        return;
    }

    $closesAt = strtotime($pendingElection['closes_at']);
    $daysLeft = max(0, ceil(($closesAt - time()) / 86400));
?>
<div class="voting-banner" style="background: linear-gradient(135deg, #1779ba 0%, #0c4a6e 100%); color: #fff; padding: 0.75rem 0; text-align: center;">
    <div class="grid-container">
        <div style="display: flex; align-items: center; justify-content: center; flex-wrap: wrap; gap: 0.5rem 1rem;">
            <span style="font-weight: 500;">
                Voting is open: <strong><?php echo htmlspecialchars($pendingElection['title'], ENT_QUOTES, 'UTF-8'); ?></strong>
                <?php if ($daysLeft <= 3): ?>
                    <span style="background: #ffae00; color: #000; padding: 0.1rem 0.5rem; border-radius: 4px; font-size: 0.8rem; margin-left: 0.5rem;">
                        <?php echo $daysLeft === 0 ? 'Closes today!' : "Closes in {$daysLeft} day" . ($daysLeft > 1 ? 's' : ''); ?>
                    </span>
                <?php endif; ?>
            </span>
            <a href="/members/voting/" style="background: #fff; color: #1779ba; padding: 0.25rem 1rem; border-radius: 50px; text-decoration: none; font-weight: 600; font-size: 0.9rem;">
                Vote Now
            </a>
        </div>
    </div>
</div>
<?php
} catch (Exception $e) {
    // Silently fail - don't break the page if voting check fails
    error_log('Voting banner error: ' . $e->getMessage());
}
