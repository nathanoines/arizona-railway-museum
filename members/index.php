<?php
session_start();

// Require login
if (!isset($_SESSION['user_id'])) {
    header('Location: /login.php');
    exit;
}

$page_title = "ARM Members";
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../assets/header.php';

// Pull session data (with safe defaults)
$userId           = (int)($_SESSION['user_id'] ?? 0);
$userEmail        = $_SESSION['user_email']        ?? '';
$userFirstName    = $_SESSION['user_first_name']   ?? '';
$userLastName     = $_SESSION['user_last_name']    ?? '';
$userRole         = $_SESSION['user_role']         ?? 'user';      // 'admin' or 'user'

// Get current membership status from database (not session, as it may have been updated)
try {
    $pdo = getDbConnection();
    $stmt = $pdo->prepare("SELECT membership_status, membership_expires_at FROM members WHERE id = :id LIMIT 1");
    $stmt->execute([':id' => $userId]);
    $memberData = $stmt->fetch();
    
    $membershipStatus = $memberData['membership_status'] ?? 'inactive';
    $membershipExpiresAt = $memberData['membership_expires_at'] ?? null;
    
    // Check if user has a pending membership application
    $hasPendingApp = false;
    $pendingStmt = $pdo->prepare("SELECT id FROM membership_applications WHERE member_id = :member_id AND status = 'pending' LIMIT 1");
    $pendingStmt->execute([':member_id' => $userId]);
    if ($pendingStmt->fetch()) {
        $hasPendingApp = true;
        // Override status to show pending if they have a pending application and status is inactive
        if ($membershipStatus === 'inactive') {
            $membershipStatus = 'pending';
        }
    }
    
    // Update session with current status
    $_SESSION['membership_status'] = $membershipStatus;
} catch (PDOException $e) {
    error_log('Error fetching membership status: ' . $e->getMessage());
    $membershipStatus = $_SESSION['membership_status'] ?? 'inactive';
    $membershipExpiresAt = null;
    $hasPendingApp = false;
}

$isAdmin = ($userRole === 'admin');

// Nicely formatted membership text
$membershipLabelMap = [
    'inactive' => 'Inactive / None',
    'founder' => 'Founder Member',
    'traditional_regular' => 'Traditional Regular Member',
    'traditional_family' => 'Traditional Family Member',
    'traditional_senior' => 'Traditional Senior Member',
    'docent_regular' => 'Active Docent Regular',
    'docent_family' => 'Active Docent Family',
    'docent_senior' => 'Active Docent Senior',
    'sustaining' => 'Sustaining Member',
    'corporate' => 'Corporate Sponsor',
    'life' => 'Life Member',
    'pending' => 'Application Pending Review',
    // Legacy values (in case data migration hasn't run yet)
    'monthly' => 'Active Member',
    'lifetime' => 'Life Member',
];

$membershipLabel = $membershipLabelMap[$membershipStatus] ?? ucfirst(str_replace('_', ' ', $membershipStatus));

// Simple display name fallback
$displayName = trim($userFirstName . ' ' . $userLastName);
if ($displayName === '') {
    $displayName = $userEmail;
}
?>

    <div class="grid-x grid-margin-x">
        <div class="small-12 cell">
            <h1>Welcome, <?php echo htmlspecialchars($displayName, ENT_QUOTES, 'UTF-8'); ?></h1>
            <p class="subheader">
                This is your member dashboard for the Arizona Railway Museum.
            </p>
        </div>
    </div>

    <div class="grid-x grid-margin-x">
        <div class="small-12 medium-6 large-4 cell">
            <div class="card arm-card" style="background: #d7ecfa;">
                <div class="card-section">
                    <h3 style="color: #0c4b78;">Your Account</h3>
                    <p><strong>Email:</strong> <?php echo htmlspecialchars($userEmail, ENT_QUOTES, 'UTF-8'); ?></p>
                    <p><strong>Membership:</strong> <?php echo htmlspecialchars($membershipLabel, ENT_QUOTES, 'UTF-8'); ?></p>
                    <?php if ($membershipExpiresAt && $membershipStatus !== 'inactive' && $membershipStatus !== 'pending'): ?>
                        <p><strong>Expires:</strong> <?php echo date('M j, Y', strtotime($membershipExpiresAt)); ?></p>
                    <?php endif; ?>
                    <p><strong>Role:</strong> <?php echo htmlspecialchars(ucfirst($userRole), ENT_QUOTES, 'UTF-8'); ?></p>
                </div>
            </div>
        </div>
        
        <div class="small-12 medium-6 large-4 cell">
            <div class="card arm-card">
                <div class="card-section">
                    <h3>Quick Links</h3>
                    <ul style="list-style: none; margin: 0; padding: 0;">
                        <li style="padding: 0.5rem 0; border-bottom: 1px solid #e0e0e0;">
                            <a href="/members/account.php" style="display: flex; align-items: center; text-decoration: none; color: #1779ba;">
                                <span style="margin-right: 0.5rem;">⚙️</span>
                                <span>Account Settings</span>
                            </a>
                        </li>
                        <?php if ($membershipStatus === 'inactive'): ?>
                        <li style="padding: 0.5rem 0; border-bottom: 1px solid #e0e0e0;">
                            <a href="/membership/apply.php" style="display: flex; align-items: center; text-decoration: none; color: #1779ba;">
                                <span style="margin-right: 0.5rem;">📝</span>
                                <span>Apply for Membership</span>
                            </a>
                        </li>
                        <?php endif; ?>
                        <li style="padding: 0.5rem 0; border-bottom: 1px solid #e0e0e0;">
                            <a href="/equipment/" style="display: flex; align-items: center; text-decoration: none; color: #1779ba;">
                                <span style="margin-right: 0.5rem;">🚂</span>
                                <span>Equipment Roster</span>
                            </a>
                        </li>
                        <li style="padding: 0.5rem 0; border-bottom: 1px solid #e0e0e0;">
                            <a href="/events/" style="display: flex; align-items: center; text-decoration: none; color: #1779ba;">
                                <span style="margin-right: 0.5rem;">📅</span>
                                <span>Upcoming Events</span>
                            </a>
                        </li>
<li style="padding: 0.5rem 0; border-bottom: 1px solid #e0e0e0;">                            <a href="/members/voting/" style="display: flex; align-items: center; text-decoration: none; color: #1779ba;">                                <span style="margin-right: 0.5rem;">🗳️</span>                                <span>Member Voting</span>                            </a>                        </li>
                        <?php if ($isAdmin): ?>
                        <li style="padding: 0.5rem 0;">
                            <a href="/admin/" style="display: flex; align-items: center; text-decoration: none; color: #cc4b00; font-weight: 600;">
                                <span style="margin-right: 0.5rem;">🔧</span>
                                <span>Admin Dashboard</span>
                            </a>
                        </li>
                        <?php else: ?>
                        <li style="padding: 0.5rem 0;">
                            <a href="/logout.php" style="display: flex; align-items: center; text-decoration: none; color: #666;">
                                <span style="margin-right: 0.5rem;">🚪</span>
                                <span>Log Out</span>
                            </a>
                        </li>
                        <?php endif; ?>
                    </ul>
                </div>
            </div>
        </div>

        <div class="small-12 medium-12 large-4 cell">
            <div class="card arm-card">
                <div class="card-section">
                    <h3>Member Area News</h3>
                    <p>
                        In the future, this area can include member-only updates, event registrations,
                        renewal options, and more.
                    </p>
                    <p>
                        For now, you can browse the public site and enjoy learning more about the
                        Arizona Railway Museum.
                    </p>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../assets/footer.php'; ?>
