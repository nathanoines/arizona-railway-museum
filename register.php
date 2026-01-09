<?php
require_once __DIR__ . '/config/db.php';

// Enforce HTTPS in production only
if (APP_ENV === 'production') {
    $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
               || (isset($_SERVER['SERVER_PORT']) && $_SERVER['SERVER_PORT'] == 443);
    if (!$isHttps) {
        header('Location: https://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI']);
        exit;
    }
}

require_once __DIR__ . '/assets/header.php';
$pdo = getDbConnection();

$errors = [];
$success = null;

// Redirect already-logged-in users (optional)
if (isset($_SESSION['user_id'])) {
     header('Location: members/index.php');
     exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $first_name       = trim($_POST['first_name'] ?? '');
    $last_name        = trim($_POST['last_name'] ?? '');
    $email            = trim($_POST['email'] ?? '');
    $password         = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';

    // Membership will ALWAYS start as inactive
    $membership_status = 'inactive';

    // Basic validation
    if ($email === '') {
        $errors[] = 'Email is required.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Please enter a valid email address.';
    }

    if ($password === '' || $confirm_password === '') {
        $errors[] = 'Password and confirmation are required.';
    } elseif ($password !== $confirm_password) {
        $errors[] = 'Passwords do not match.';
    } elseif (strlen($password) < 8) {
        $errors[] = 'Password must be at least 8 characters long.';
    }

    // Check if email already exists
    if (empty($errors)) {
        $stmt = $pdo->prepare('SELECT id FROM members WHERE email = :email LIMIT 1');
        $stmt->execute([':email' => $email]);
        if ($stmt->fetch()) {
            $errors[] = 'An account with that email already exists.';
        }
    }

    if (empty($errors)) {
        $password_hash = password_hash($password, PASSWORD_DEFAULT);

        // Start transaction for registration + auto-linking
        $pdo->beginTransaction();

        try {
            $stmt = $pdo->prepare("
                INSERT INTO members (email, password_hash, first_name, last_name, user_role, membership_status)
                VALUES (:email, :password_hash, :first_name, :last_name, 'user', :membership_status)
            ");

            $stmt->execute([
                ':email'             => $email,
                ':password_hash'     => $password_hash,
                ':first_name'        => $first_name !== '' ? $first_name : null,
                ':last_name'         => $last_name !== '' ? $last_name : null,
                ':membership_status' => $membership_status,
            ]);

            $new_member_id = $pdo->lastInsertId();

            // Auto-link: Check for approved applications with matching email that aren't linked
            $app_sql = "SELECT id, membership_level
                        FROM membership_applications
                        WHERE email = :email
                          AND status = 'approved'
                          AND member_id IS NULL
                        ORDER BY created_at DESC
                        LIMIT 1";
            $app_stmt = $pdo->prepare($app_sql);
            $app_stmt->execute([':email' => $email]);
            $application = $app_stmt->fetch();

            $membership_linked = false;

            if ($application) {
                // Link the application to this new member
                $link_sql = "UPDATE membership_applications SET member_id = :member_id WHERE id = :app_id";
                $link_stmt = $pdo->prepare($link_sql);
                $link_stmt->execute([':member_id' => $new_member_id, ':app_id' => $application['id']]);

                // Apply membership status from the application
                $membership_mapping = [
                    'traditional_regular_1yr' => ['status' => 'traditional_regular', 'term' => '1yr', 'months' => 12],
                    'traditional_regular_2yr' => ['status' => 'traditional_regular', 'term' => '2yr', 'months' => 24],
                    'traditional_family_1yr' => ['status' => 'traditional_family', 'term' => '1yr', 'months' => 12],
                    'traditional_family_2yr' => ['status' => 'traditional_family', 'term' => '2yr', 'months' => 24],
                    'traditional_senior_1yr' => ['status' => 'traditional_senior', 'term' => '1yr', 'months' => 12],
                    'traditional_senior_2yr' => ['status' => 'traditional_senior', 'term' => '2yr', 'months' => 24],
                    'docent_regular_1yr' => ['status' => 'docent_regular', 'term' => '1yr', 'months' => 12],
                    'docent_regular_2yr' => ['status' => 'docent_regular', 'term' => '2yr', 'months' => 24],
                    'docent_family_1yr' => ['status' => 'docent_family', 'term' => '1yr', 'months' => 12],
                    'docent_family_2yr' => ['status' => 'docent_family', 'term' => '2yr', 'months' => 24],
                    'docent_senior_1yr' => ['status' => 'docent_senior', 'term' => '1yr', 'months' => 12],
                    'docent_senior_2yr' => ['status' => 'docent_senior', 'term' => '2yr', 'months' => 24],
                    'life' => ['status' => 'life', 'term' => 'lifetime', 'months' => 1200],
                    'sustaining' => ['status' => 'sustaining', 'term' => '1yr', 'months' => 12],
                    'corporate' => ['status' => 'corporate', 'term' => '1yr', 'months' => 12],
                    'founder' => ['status' => 'founder', 'term' => 'lifetime', 'months' => 1200],
                ];

                if (isset($membership_mapping[$application['membership_level']])) {
                    $mapping = $membership_mapping[$application['membership_level']];
                    $new_status = $mapping['status'];
                    $new_term = $mapping['term'];
                    $term_months = $mapping['months'];
                } else {
                    $new_status = 'traditional_regular';
                    $new_term = '1yr';
                    $term_months = 12;
                }

                // Update member with membership details
                $update_sql = "UPDATE members
                               SET membership_status = :status,
                                   membership_term = :term,
                                   membership_activated_at = NOW(),
                                   membership_last_renewed_at = NOW(),
                                   membership_expires_at = DATE_ADD(CURDATE(), INTERVAL :months MONTH)
                               WHERE id = :id";
                $update_stmt = $pdo->prepare($update_sql);
                $update_stmt->execute([
                    ':status' => $new_status,
                    ':term' => $new_term,
                    ':months' => $term_months,
                    ':id' => $new_member_id
                ]);

                // Log to membership_renewals
                $renewal_sql = "INSERT INTO membership_renewals (
                                    member_id, application_id, previous_status, new_status,
                                    previous_expires_at, new_expires_at, term_months, note
                                ) VALUES (
                                    :member_id, :app_id, 'inactive', :new_status,
                                    NULL, DATE_ADD(CURDATE(), INTERVAL :months MONTH), :term_months,
                                    :note
                                )";
                $renewal_stmt = $pdo->prepare($renewal_sql);
                $renewal_stmt->execute([
                    ':member_id' => $new_member_id,
                    ':app_id' => $application['id'],
                    ':new_status' => $new_status,
                    ':months' => $term_months,
                    ':term_months' => $term_months,
                    ':note' => 'Auto-linked on registration - Application #' . $application['id']
                ]);

                $membership_linked = true;
            }

            $pdo->commit();

            if ($membership_linked) {
                $success = 'Registration successful! Your membership has been automatically activated. You can now log in.';
            } else {
                $success = 'Registration successful! You can now log in.';
            }
            // Clear form values after success
            $first_name = $last_name = $email = '';

        } catch (Exception $e) {
            $pdo->rollBack();
            error_log('Registration error: ' . $e->getMessage());
            $errors[] = 'An error occurred during registration. Please try again.';
        }
    }
}
?>

    <div class="grid-x grid-margin-x align-center">
        <div class="small-12 medium-8 large-6 cell">
            <h1>Member Registration</h1>
            <p class="subheader">
                Create an account to manage your membership and access member features.
                Your membership status will start as inactive and can be updated later.
            </p>

            <?php if (!empty($errors)): ?>
                <div class="callout alert">
                    <h5>There were some problems:</h5>
                    <<ul>
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

            <div class="callout">
                <form method="post" action="register.php">
                    <div class="grid-x grid-margin-x">
                        <div class="small-12 medium-6 cell">
                            <label>First Name
                                <input type="text" name="first_name"
                                       value="<?php echo htmlspecialchars($first_name ?? '', ENT_QUOTES, 'UTF-8'); ?>">
                            </label>
                        </div>
                        <div class="small-12 medium-6 cell">
                            <label>Last Name
                                <input type="text" name="last_name"
                                       value="<?php echo htmlspecialchars($last_name ?? '', ENT_QUOTES, 'UTF-8'); ?>">
                            </label>
                        </div>
                    </div>

                    <label>Email *
                        <input type="email" name="email" required
                               value="<?php echo htmlspecialchars($email ?? '', ENT_QUOTES, 'UTF-8'); ?>">
                    </label>

                    <label>Password *
                        <input type="password" name="password" required>
                    </label>

                    <label>Confirm Password *
                        <input type="password" name="confirm_password" required>
                    </label>

                    <button type="submit" class="button primary expanded" style="border-radius: 8px;">Register</button>
                </form>
            </div>

            <p>Already have an account? <a href="login.php">Log in here</a>.</p>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/assets/footer.php'; ?>
