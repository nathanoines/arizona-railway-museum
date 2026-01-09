<?php
/**
 * Membership Application Submission Handler
 * Inserts application data into membership_applications table
 * with status='pending' for admin review
 */

declare(strict_types=1);

session_start();
require_once __DIR__ . '/../config/db.php';

$page_title = "Application Submitted | Arizona Railway Museum";

// Only process POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /membership/apply.php');
    exit;
}

// Get user_id if logged in (null for guest applications)
$user_id = $_SESSION['user_id'] ?? null;

// Validate required fields
$required_fields = ['name', 'address', 'city', 'state', 'zip', 'email', 'membership_level'];
$errors = [];

foreach ($required_fields as $field) {
    if (empty($_POST[$field])) {
        $errors[] = ucfirst(str_replace('_', ' ', $field)) . ' is required.';
    }
}

if (!empty($errors)) {
    $_SESSION['form_errors'] = $errors;
    $_SESSION['form_data'] = $_POST;
    header('Location: /membership/apply.php');
    exit;
}

// Sanitize and prepare data
$name = trim($_POST['name']);
$address = trim($_POST['address']);
$city = trim($_POST['city']);
$state = strtoupper(trim($_POST['state']));
$zip = trim($_POST['zip']);
// Strip all non-digits from phone number before storing
$phone = !empty($_POST['phone']) ? preg_replace('/\D/', '', trim($_POST['phone'])) : null;
$email = trim($_POST['email']);
$membership_level = trim($_POST['membership_level']);

// Handle optional amount fields
$sustaining_amount = null;
$corporate_amount = null;

if ($membership_level === 'sustaining' && !empty($_POST['sustaining_amount'])) {
    $sustaining_amount = (float)$_POST['sustaining_amount'];
    // Validate range
    if ($sustaining_amount < 50 || $sustaining_amount > 500) {
        $_SESSION['form_errors'] = ['Sustaining membership amount must be between $50 and $500.'];
        $_SESSION['form_data'] = $_POST;
        header('Location: /membership/apply.php');
        exit;
    }
}

if ($membership_level === 'corporate' && !empty($_POST['corporate_amount'])) {
    $corporate_amount = (float)$_POST['corporate_amount'];
    // Validate minimum
    if ($corporate_amount < 500) {
        $_SESSION['form_errors'] = ['Corporate sponsorship amount must be $500 or more.'];
        $_SESSION['form_data'] = $_POST;
        header('Location: /membership/apply.php');
        exit;
    }
}

// Handle interests array
$interests_array = isset($_POST['interests']) && is_array($_POST['interests'])
    ? $_POST['interests']
    : [];
$interests = !empty($interests_array) ? implode(', ', $interests_array) : null;

// Handle comments
$comments = !empty($_POST['comments']) ? trim($_POST['comments']) : null;

// Validate email format
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $_SESSION['form_errors'] = ['Please provide a valid email address.'];
    $_SESSION['form_data'] = $_POST;
    header('Location: /membership/apply.php');
    exit;
}

try {
    $pdo = getDbConnection();
    
    // Start transaction
    $pdo->beginTransaction();

    // Check if email already exists in members table
    $check_member_sql = "SELECT id FROM members WHERE email = :email LIMIT 1";
    $check_member_stmt = $pdo->prepare($check_member_sql);
    $check_member_stmt->execute([':email' => $email]);

    if ($check_member_stmt->fetch()) {
        $pdo->rollBack();
        $_SESSION['form_errors'] = ['This email address is already associated with a member account. Please log in to manage your membership.'];
        $_SESSION['form_data'] = $_POST;
        header('Location: /membership/apply.php');
        exit;
    }

    // Check if there's already an application with this email (any status)
    $check_app_sql = "SELECT id, status FROM membership_applications WHERE email = :email LIMIT 1";
    $check_app_stmt = $pdo->prepare($check_app_sql);
    $check_app_stmt->execute([':email' => $email]);
    $existing_app = $check_app_stmt->fetch();

    if ($existing_app) {
        $pdo->rollBack();
        if ($existing_app['status'] === 'pending') {
            $_SESSION['form_errors'] = ['There is already a pending membership application with this email address. Please wait for admin review.'];
        } else {
            $_SESSION['form_errors'] = ['An application has already been submitted with this email address. Please contact us if you need assistance.'];
        }
        $_SESSION['form_data'] = $_POST;
        header('Location: /membership/apply.php');
        exit;
    }

    // Insert into membership_applications table (member_id is null for guest applications)
    $sql = "INSERT INTO membership_applications (
                name,
                address,
                city,
                state,
                zip,
                phone,
                email,
                membership_level,
                sustaining_amount,
                corporate_amount,
                interests,
                comments,
                status,
                member_id
            ) VALUES (
                :name,
                :address,
                :city,
                :state,
                :zip,
                :phone,
                :email,
                :membership_level,
                :sustaining_amount,
                :corporate_amount,
                :interests,
                :comments,
                'pending',
                :member_id
            )";

    $stmt = $pdo->prepare($sql);

    $stmt->execute([
        ':name' => $name,
        ':address' => $address,
        ':city' => $city,
        ':state' => $state,
        ':zip' => $zip,
        ':phone' => $phone,
        ':email' => $email,
        ':membership_level' => $membership_level,
        ':sustaining_amount' => $sustaining_amount,
        ':corporate_amount' => $corporate_amount,
        ':interests' => $interests,
        ':comments' => $comments,
        ':member_id' => $user_id,
    ]);

    $application_id = $pdo->lastInsertId();

    // Commit the transaction
    $pdo->commit();

    // Clear any form data from session
    unset($_SESSION['form_data']);
    unset($_SESSION['form_errors']);

    // Store success message
    $_SESSION['application_submitted'] = true;
    $_SESSION['applicant_name'] = $name;
    $_SESSION['applicant_email'] = $email;

} catch (PDOException $e) {
    // Roll back transaction on error
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    // Log error (in production, use proper logging)
    error_log('Membership application error: ' . $e->getMessage());

    $_SESSION['form_errors'] = ['An error occurred while submitting your application. Please try again.'];
    $_SESSION['form_data'] = $_POST;
    header('Location: /membership/apply.php');
    exit;
}

// Redirect to success page
header('Location: /membership/success.php');
exit;
