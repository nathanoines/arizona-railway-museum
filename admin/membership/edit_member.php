<?php
/**
 * Admin Interface: Edit Member Membership Status
 *
 * Allows admins to manually update member's membership status and expiration date
 */

declare(strict_types=1);

session_start();
require_once __DIR__ . '/../../config/db.php';

// Check if user is logged in as admin
if (!isset($_SESSION['user_id'])) {
    header('Location: /login.php');
    exit;
}

$userRole = $_SESSION['user_role'] ?? 'user';
if ($userRole !== 'admin') {
    header('Location: /members/');
    exit;
}

// Validate member ID
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    $_SESSION['error_message'] = 'Invalid member ID.';
    header('Location: /admin/membership/');
    exit;
}

$member_id = (int)$_GET['id'];

try {
    $pdo = getDbConnection();

    // Fetch the member
    $sql = "SELECT * FROM members WHERE id = :id LIMIT 1";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([':id' => $member_id]);
    $member = $stmt->fetch();

    if (!$member) {
        $_SESSION['error_message'] = 'Member not found.';
        header('Location: /admin/membership/');
        exit;
    }

    // Fetch available titles
    $titlesStmt = $pdo->query("SELECT id, title_name FROM member_titles WHERE is_active = 1 ORDER BY display_order ASC, title_name ASC");
    $availableTitles = $titlesStmt->fetchAll();

    // Fetch member's current titles
    $memberTitlesStmt = $pdo->prepare("SELECT title_id FROM member_title_assignments WHERE member_id = :member_id");
    $memberTitlesStmt->execute([':member_id' => $member_id]);
    $memberTitleIds = $memberTitlesStmt->fetchAll(PDO::FETCH_COLUMN);

} catch (PDOException $e) {
    error_log('Error loading member: ' . $e->getMessage());
    $_SESSION['error_message'] = 'Database error occurred.';
    header('Location: /admin/membership/');
    exit;
}

$page_title = "Edit Member: " . $member['first_name'] . " " . $member['last_name'] . " | Arizona Railway Museum";
require_once __DIR__ . '/../../assets/header.php';
?>

</div></div><!-- Close grid-container and page-content for full-width hero -->

<section class="arm-hero" style="margin-top: -4.5rem; padding: 2.5rem 0 2rem;">
    <div class="grid-container">
        <div class="grid-x grid-margin-x align-middle">
            <div class="small-12 cell">
                <h1 style="margin-bottom: 0.25rem;">Edit Member: <?php echo htmlspecialchars($member['first_name'] . ' ' . $member['last_name'], ENT_QUOTES, 'UTF-8'); ?></h1>
                <p class="lead" style="margin-bottom: 0;">Update member information and membership details.</p>
            </div>
        </div>
    </div>
</section>

<div class="page-content">
<div class="grid-container" style="padding-top: 2rem; margin-bottom: 2rem;">

<div class="grid-x grid-margin-x">
    <div class="small-12 cell">
        <?php if (isset($_SESSION['success_message'])): ?>
            <div class="callout success">
                <?php
                echo htmlspecialchars($_SESSION['success_message'], ENT_QUOTES, 'UTF-8');
                unset($_SESSION['success_message']);
                ?>
            </div>
        <?php endif; ?>

        <?php if (isset($_SESSION['error_message'])): ?>
            <div class="callout alert">
                <?php
                echo htmlspecialchars($_SESSION['error_message'], ENT_QUOTES, 'UTF-8');
                unset($_SESSION['error_message']);
                ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<div class="grid-x grid-margin-x">
    <div class="small-12 medium-8 cell">
        <div class="card arm-card">
            <div class="card-section">
                <h3 style="margin-top: 0;">Edit Member Information</h3>
                
                <form method="post" action="../handlers/update_member.php">
                    <input type="hidden" name="member_id" value="<?php echo $member['id']; ?>">

                    <fieldset>
                        <legend>Personal Information</legend>
                        
                        <p style="margin-bottom: 1rem;">
                            <strong>Member ID:</strong> #<?php echo $member['id']; ?>
                        </p>

                        <div class="grid-x grid-margin-x">
                            <div class="small-12 medium-6 cell">
                                <label>First Name *
                                    <input type="text" name="first_name" required
                                           value="<?php echo htmlspecialchars($member['first_name'], ENT_QUOTES, 'UTF-8'); ?>">
                                </label>
                            </div>
                            <div class="small-12 medium-6 cell">
                                <label>Last Name *
                                    <input type="text" name="last_name" required
                                           value="<?php echo htmlspecialchars($member['last_name'], ENT_QUOTES, 'UTF-8'); ?>">
                                </label>
                            </div>
                        </div>

                        <label>Email *
                            <input type="email" name="email" required
                                   value="<?php echo htmlspecialchars($member['email'], ENT_QUOTES, 'UTF-8'); ?>">
                        </label>

                        <label>Role *
                            <select name="user_role" required>
                                <option value="user" <?php echo $member['user_role'] === 'user' ? 'selected' : ''; ?>>User</option>
                                <option value="admin" <?php echo $member['user_role'] === 'admin' ? 'selected' : ''; ?>>Admin</option>
                            </select>
                        </label>

                        <label style="margin-top: 1rem;">
                            <input type="checkbox" name="is_key_holder" value="1"
                                <?php echo !empty($member['is_key_holder']) ? 'checked' : ''; ?>>
                            Key Holder
                            <small style="display: block; color: #666; margin-top: 0.25rem;">
                                Can view equipment padlock PINs
                            </small>
                        </label>

                        <?php if (!empty($availableTitles)): ?>
                        <label>Organizational Titles
                            <small style="display: block; margin-bottom: 0.5rem; color: #666;">Hold Ctrl/Cmd to select multiple titles</small>
                            <select name="member_titles[]" multiple style="height: auto; min-height: 120px;">
                                <?php foreach ($availableTitles as $title): ?>
                                    <option value="<?php echo (int)$title['id']; ?>"
                                        <?php echo in_array($title['id'], $memberTitleIds) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($title['title_name'], ENT_QUOTES, 'UTF-8'); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <small>Select titles like President, Vice President, etc. <a href="/admin/titles/">Manage titles</a></small>
                        </label>
                        <?php endif; ?>
                    </fieldset>

                    <fieldset style="margin-top: 2rem;">
                        <legend>Membership Status</legend>

                        <label>Membership Type *
                            <select name="membership_status" required>
                                <option value="inactive" <?php echo $member['membership_status'] === 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                                <optgroup label="Founder">
                                    <option value="founder" <?php echo $member['membership_status'] === 'founder' ? 'selected' : ''; ?>>Founder</option>
                                </optgroup>
                                <optgroup label="Traditional Memberships">
                                    <option value="traditional_regular" <?php echo $member['membership_status'] === 'traditional_regular' ? 'selected' : ''; ?>>Traditional Regular</option>
                                    <option value="traditional_family" <?php echo $member['membership_status'] === 'traditional_family' ? 'selected' : ''; ?>>Traditional Family</option>
                                    <option value="traditional_senior" <?php echo $member['membership_status'] === 'traditional_senior' ? 'selected' : ''; ?>>Traditional Senior (62+)</option>
                                </optgroup>
                                <optgroup label="Active Docent Memberships">
                                    <option value="docent_regular" <?php echo $member['membership_status'] === 'docent_regular' ? 'selected' : ''; ?>>Active Docent Regular</option>
                                    <option value="docent_family" <?php echo $member['membership_status'] === 'docent_family' ? 'selected' : ''; ?>>Active Docent Family</option>
                                    <option value="docent_senior" <?php echo $member['membership_status'] === 'docent_senior' ? 'selected' : ''; ?>>Active Docent Senior (62+)</option>
                                </optgroup>
                                <optgroup label="Special Memberships">
                                    <option value="sustaining" <?php echo $member['membership_status'] === 'sustaining' ? 'selected' : ''; ?>>Sustaining</option>
                                    <option value="corporate" <?php echo $member['membership_status'] === 'corporate' ? 'selected' : ''; ?>>Corporate Sponsor</option>
                                    <option value="life" <?php echo $member['membership_status'] === 'life' ? 'selected' : ''; ?>>Life</option>
                                </optgroup>
                            </select>
                        </label>

                        <label>Membership Term *
                            <select name="membership_term" required>
                                <option value="" <?php echo empty($member['membership_term']) ? 'selected' : ''; ?>>None (Inactive)</option>
                                <option value="1yr" <?php echo ($member['membership_term'] ?? '') === '1yr' ? 'selected' : ''; ?>>1 Year</option>
                                <option value="2yr" <?php echo ($member['membership_term'] ?? '') === '2yr' ? 'selected' : ''; ?>>2 Year</option>
                                <option value="lifetime" <?php echo ($member['membership_term'] ?? '') === 'lifetime' ? 'selected' : ''; ?>>Lifetime</option>
                            </select>
                            <small>Select "None" for inactive members, "Lifetime" for life memberships</small>
                        </label>

                        <div style="padding: 1rem; background: #f8f9fa; border-radius: 5px; margin-bottom: 1rem;">
                            <p style="margin: 0; font-size: 0.9rem;">
                                <strong>Current Expiration Date:</strong><br>
                                <?php 
                                if ($member['membership_expires_at']) {
                                    echo date('F j, Y', strtotime($member['membership_expires_at']));
                                } else {
                                    echo '<em style="color: #666;">No expiration set</em>';
                                }
                                ?>
                            </p>
                        </div>

                        <label>Update Membership Expires At
                            <input type="date" name="membership_expires_at" 
                                   value="<?php echo $member['membership_expires_at'] ? date('Y-m-d', strtotime($member['membership_expires_at'])) : ''; ?>">
                            <small>Leave blank for lifetime memberships or to clear expiration</small>
                        </label>

                        <label>
                            <input type="checkbox" name="set_activated_at" id="set_activated_at">
                            Set membership activated date to now (if not already set)
                        </label>

                        <label>
                            <input type="checkbox" name="set_renewed_at" id="set_renewed_at">
                            Set last renewed date to now
                        </label>
                    </fieldset>

                    <div style="margin-top: 1.5rem;">
                        <button type="submit" class="button primary large" style="border-radius: 8px;">Save All Changes</button>
                        <a href="index.php" class="button secondary large" style="border-radius: 8px;">Cancel</a>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="small-12 medium-4 cell">
        <div class="card arm-card" style="background: #fff3cd;">
            <div class="card-section">
                <h5 style="margin-top: 0;">⚠️ Important</h5>
                <p style="font-size: 0.9rem; margin: 0;">
                    Manually changing membership status here will not create a renewal log entry. 
                    Use this only for administrative corrections.
                </p>
            </div>
        </div>

        <div class="card arm-card" style="margin-top: 1rem;">
            <div class="card-section">
                <h5 style="margin-top: 0;">Membership Dates</h5>
                <p style="font-size: 0.9rem;">
                    <strong>Activated:</strong><br>
                    <?php echo $member['membership_activated_at'] ? date('M j, Y', strtotime($member['membership_activated_at'])) : 'Never'; ?>
                </p>
                <p style="font-size: 0.9rem;">
                    <strong>Last Renewed:</strong><br>
                    <?php echo $member['membership_last_renewed_at'] ? date('M j, Y', strtotime($member['membership_last_renewed_at'])) : 'Never'; ?>
                </p>
                <p style="font-size: 0.9rem;">
                    <strong>Current Expires:</strong><br>
                    <?php echo $member['membership_expires_at'] ? date('M j, Y', strtotime($member['membership_expires_at'])) : 'No expiration'; ?>
                </p>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../../assets/footer.php'; ?>
