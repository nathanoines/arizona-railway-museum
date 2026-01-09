<?php
session_start();

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

$pdo = getDbConnection();

// If already logged in, redirect to members page BEFORE any output
if (isset($_SESSION['user_id'])) {
    header('Location: /members/index.php');
    exit;
}

// Process login form BEFORE any output
$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email    = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($email === '' || $password === '') {
        $errors[] = 'Email and password are both required.';
    } else {
        $stmt = $pdo->prepare('SELECT id, email, password_hash, first_name, last_name, user_role, is_key_holder, is_super_admin, membership_status
                               FROM members
                               WHERE email = :email
                               LIMIT 1');
        $stmt->execute([':email' => $email]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password_hash'])) {
            // Successful login
            $_SESSION['user_id']           = (int)$user['id'];
            $_SESSION['user_email']        = $user['email'];
            $_SESSION['user_first_name']   = $user['first_name'];
            $_SESSION['user_last_name']    = $user['last_name'];
            $_SESSION['user_role']         = $user['user_role'];          // 'admin' or 'user'
            $_SESSION['is_key_holder']     = (bool)$user['is_key_holder']; // can view equipment PINs
            $_SESSION['is_super_admin']    = (bool)$user['is_super_admin']; // can access database tools
            $_SESSION['membership_status'] = $user['membership_status'];  // 'inactive', 'monthly', 'lifetime'

            // Regenerate session ID for security
            session_regenerate_id(true);
            
            header('Location: /members/index.php');
            exit;
        } else {
            $errors[] = 'Invalid email or password.';
        }
    }
}

// NOW include header.php (only reached if no redirect occurred)
require_once __DIR__ . '/assets/header.php';
?>

<div class="grid-x grid-margin-x">
    <div class="small-12 cell">
        <h1>Member Login</h1>
        <p class="lead">Log in to access your member area and exclusive features</p>
    </div>
</div>

<div class="grid-x grid-margin-x">
    <div class="small-12 medium-7 large-8 cell">
        <?php if (!empty($errors)): ?>
            <div class="callout alert">
                <h5>Login Failed</h5>
                <ul style="margin-bottom: 0;">
                    <?php foreach ($errors as $error): ?>
                        <li><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <div class="card arm-card">
            <div class="card-section">
                <h3>Sign In to Your Account</h3>
                <form method="post" action="login.php">
                    <label>Email Address *
                        <input type="email" name="email" required placeholder="your@email.com"
                               value="<?php echo htmlspecialchars($email ?? '', ENT_QUOTES, 'UTF-8'); ?>">
                    </label>

                    <label>Password *
                        <input type="password" name="password" required placeholder="Enter your password">
                    </label>

                    <button type="submit" class="button primary large expanded" style="border-radius: 8px;">Log In</button>
                </form>
                
                <div style="margin-top: 1.5rem; padding-top: 1.5rem; border-top: 1px solid #e0e0e0; text-align: center;">
                    <p style="margin: 0;">Don't have an account? <a href="register.php" style="font-weight: 600;">Register here</a></p>
                </div>
            </div>
        </div>
    </div>
    
    <div class="small-12 medium-5 large-4 cell">
        <div class="card arm-card" style="background: #e3f2fd;">
            <div class="card-section">
                <h4>Member Benefits</h4>
                <ul style="font-size: 0.9rem; margin-bottom: 0;">
                    <li>Access member-only events</li>
                    <li>Receive quarterly newsletter</li>
                    <li>Vote at annual meetings</li>
                    <li>Gift shop discounts</li>
                    <li>Support preservation efforts</li>
                </ul>
            </div>
        </div>
        
        <div class="card arm-card" style="background: #fff3cd; margin-top: 1rem;">
            <div class="card-section">
                <h4>Not a Member Yet?</h4>
                <p style="font-size: 0.9rem; margin-bottom: 1rem;">Join the Arizona Railway Museum and help preserve railroad history.</p>
                <a href="/membership" class="button secondary small expanded" style="border-radius: 8px;">Learn About Membership</a>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/assets/footer.php'; ?>
