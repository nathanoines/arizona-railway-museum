<?php
/**
 * Member Account Settings
 *
 * Allows members to update their email address and password
 */

session_start();

// Require login
if (!isset($_SESSION['user_id'])) {
    header('Location: /login.php');
    exit;
}

require_once __DIR__ . '/../config/db.php';

$userId = (int)$_SESSION['user_id'];
$errors = [];
$success = null;

try {
    $pdo = getDbConnection();

    // Get current member data
    $stmt = $pdo->prepare("SELECT id, email, first_name, last_name, password_hash FROM members WHERE id = :id LIMIT 1");
    $stmt->execute([':id' => $userId]);
    $member = $stmt->fetch();

    if (!$member) {
        // Invalid session - force logout
        session_destroy();
        header('Location: /login.php');
        exit;
    }
} catch (PDOException $e) {
    error_log('Error fetching member data: ' . $e->getMessage());
    $errors[] = 'Unable to load account data. Please try again.';
    $member = null;
}

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $member) {
    $action = $_POST['action'] ?? '';

    if ($action === 'update_email') {
        $newEmail = trim($_POST['new_email'] ?? '');
        $currentPassword = $_POST['current_password_email'] ?? '';

        if ($newEmail === '') {
            $errors[] = 'Email address is required.';
        } elseif (!filter_var($newEmail, FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Please enter a valid email address.';
        } elseif ($currentPassword === '') {
            $errors[] = 'Current password is required to change email.';
        } elseif (!password_verify($currentPassword, $member['password_hash'])) {
            $errors[] = 'Current password is incorrect.';
        } else {
            // Check if email is already in use by another member
            $checkStmt = $pdo->prepare("SELECT id FROM members WHERE email = :email AND id != :id");
            $checkStmt->execute([':email' => $newEmail, ':id' => $userId]);
            if ($checkStmt->fetch()) {
                $errors[] = 'This email address is already in use by another account.';
            } else {
                // Update email
                $updateStmt = $pdo->prepare("UPDATE members SET email = :email WHERE id = :id");
                $updateStmt->execute([':email' => $newEmail, ':id' => $userId]);

                // Update session
                $_SESSION['user_email'] = $newEmail;
                $member['email'] = $newEmail;

                $success = 'Email address updated successfully.';
            }
        }
    } elseif ($action === 'update_password') {
        $currentPassword = $_POST['current_password'] ?? '';
        $newPassword = $_POST['new_password'] ?? '';
        $confirmPassword = $_POST['confirm_password'] ?? '';

        if ($currentPassword === '') {
            $errors[] = 'Current password is required.';
        } elseif (!password_verify($currentPassword, $member['password_hash'])) {
            $errors[] = 'Current password is incorrect.';
        } elseif ($newPassword === '') {
            $errors[] = 'New password is required.';
        } elseif (strlen($newPassword) < 8) {
            $errors[] = 'New password must be at least 8 characters long.';
        } elseif ($newPassword !== $confirmPassword) {
            $errors[] = 'New passwords do not match.';
        } else {
            // Update password
            $newHash = password_hash($newPassword, PASSWORD_DEFAULT);
            $updateStmt = $pdo->prepare("UPDATE members SET password_hash = :hash WHERE id = :id");
            $updateStmt->execute([':hash' => $newHash, ':id' => $userId]);

            // Update local reference for any further checks
            $member['password_hash'] = $newHash;

            $success = 'Password updated successfully.';
        }
    } elseif ($action === 'update_name') {
        $firstName = trim($_POST['first_name'] ?? '');
        $lastName = trim($_POST['last_name'] ?? '');

        if ($firstName === '') {
            $errors[] = 'First name is required.';
        } elseif ($lastName === '') {
            $errors[] = 'Last name is required.';
        } else {
            // Update name
            $updateStmt = $pdo->prepare("UPDATE members SET first_name = :first, last_name = :last WHERE id = :id");
            $updateStmt->execute([':first' => $firstName, ':last' => $lastName, ':id' => $userId]);

            // Update session
            $_SESSION['user_first_name'] = $firstName;
            $_SESSION['user_last_name'] = $lastName;
            $member['first_name'] = $firstName;
            $member['last_name'] = $lastName;

            $success = 'Name updated successfully.';
        }
    }
}

$page_title = "Account Settings | Arizona Railway Museum";
require_once __DIR__ . '/../assets/header.php';
?>

<div class="grid-x grid-margin-x">
    <div class="small-12 cell">
        <div style="margin-bottom: 1rem;">
            <a href="/members/" class="button secondary small" style="border-radius: 8px;">&larr; Back to Member Dashboard</a>
        </div>

        <h1>Account Settings</h1>

        <?php if (!empty($errors)): ?>
            <div class="callout alert">
                <h5>There were some problems:</h5>
                <ul style="margin-bottom: 0;">
                    <?php foreach ($errors as $error): ?>
                        <li><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php elseif ($success): ?>
            <div class="callout success">
                <?php echo htmlspecialchars($success, ENT_QUOTES, 'UTF-8'); ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php if ($member): ?>
<div class="grid-x grid-margin-x">
    <div class="small-12 medium-8 cell">
        <!-- Update Name -->
        <div class="card arm-card">
            <div class="card-section">
                <h3 style="margin-top: 0;">Update Name</h3>
                <form method="post" action="">
                    <input type="hidden" name="action" value="update_name">

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

                    <button type="submit" class="button primary" style="border-radius: 8px;">Update Name</button>
                </form>
            </div>
        </div>

        <!-- Update Email -->
        <div class="card arm-card" style="margin-top: 1.5rem;">
            <div class="card-section">
                <h3 style="margin-top: 0;">Update Email Address</h3>
                <form method="post" action="">
                    <input type="hidden" name="action" value="update_email">

                    <p style="margin-bottom: 1rem;">
                        <strong>Current Email:</strong> <?php echo htmlspecialchars($member['email'], ENT_QUOTES, 'UTF-8'); ?>
                    </p>

                    <label>New Email Address *
                        <input type="email" name="new_email" required
                               placeholder="your.new@email.com">
                    </label>

                    <label>Current Password *
                        <input type="password" name="current_password_email" required
                               placeholder="Enter your current password to confirm">
                        <p class="help-text">Required to verify your identity</p>
                    </label>

                    <button type="submit" class="button primary" style="border-radius: 8px;">Update Email</button>
                </form>
            </div>
        </div>

        <!-- Update Password -->
        <div class="card arm-card" style="margin-top: 1.5rem;">
            <div class="card-section">
                <h3 style="margin-top: 0;">Change Password</h3>
                <form method="post" action="">
                    <input type="hidden" name="action" value="update_password">

                    <label>Current Password *
                        <input type="password" name="current_password" required
                               placeholder="Enter your current password">
                    </label>

                    <label>New Password *
                        <input type="password" name="new_password" required
                               placeholder="Enter new password (min 8 characters)">
                        <p class="help-text">Must be at least 8 characters long</p>
                    </label>

                    <label>Confirm New Password *
                        <input type="password" name="confirm_password" required
                               placeholder="Re-enter new password">
                    </label>

                    <button type="submit" class="button primary" style="border-radius: 8px;">Change Password</button>
                </form>
            </div>
        </div>
    </div>

    <div class="small-12 medium-4 cell">
        <div class="card arm-card" style="background: #e7f4f9;">
            <div class="card-section">
                <h5 style="margin-top: 0;">Account Security</h5>
                <p style="font-size: 0.9rem; margin: 0;">
                    Keep your account secure by using a strong, unique password.
                    We recommend a mix of letters, numbers, and special characters.
                </p>
            </div>
        </div>

        <div class="card arm-card" style="margin-top: 1rem; background: #fff3cd;">
            <div class="card-section">
                <h5 style="margin-top: 0;">Need Help?</h5>
                <p style="font-size: 0.9rem; margin: 0;">
                    If you're having trouble with your account, please contact the museum
                    at <a href="mailto:info@azrailroadmuseum.org">info@azrailroadmuseum.org</a>
                </p>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<?php require_once __DIR__ . '/../assets/footer.php'; ?>
