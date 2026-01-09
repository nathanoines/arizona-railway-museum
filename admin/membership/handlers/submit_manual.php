<?php
/**
 * Admin Interface: Process Manual Paper Application Entry
 *
 * Handles submission of manually entered paper applications
 */

declare(strict_types=1);

session_start();
require_once __DIR__ . '/../../../config/db.php';

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

// Only accept POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: add.php');
    exit;
}

// Initialize validation errors array
$errors = [];

// Validate required fields
$name = trim($_POST['name'] ?? '');
if (empty($name)) {
    $errors[] = "Name is required.";
}

$email = trim($_POST['email'] ?? '');
if (empty($email)) {
    $errors[] = "Email is required.";
} elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $errors[] = "Please provide a valid email address.";
}

$phone = !empty($_POST['phone']) ? preg_replace('/\D/', '', trim($_POST['phone'])) : null;

$address = trim($_POST['address'] ?? '');
if (empty($address)) {
    $errors[] = "Address is required.";
}

$city = trim($_POST['city'] ?? '');
if (empty($city)) {
    $errors[] = "City is required.";
}

$state = trim($_POST['state'] ?? '');
if (empty($state)) {
    $errors[] = "State is required.";
}

$zip = trim($_POST['zip'] ?? '');
if (empty($zip)) {
    $errors[] = "ZIP code is required.";
}

$membership_level = $_POST['membership_level'] ?? '';
if (empty($membership_level)) {
    $errors[] = "Please select a membership level.";
}

// Validate sustaining amount if sustaining membership
$sustaining_amount = null;
if ($membership_level === 'sustaining') {
    $sustaining_amount = isset($_POST['sustaining_amount']) ? (int)$_POST['sustaining_amount'] : null;
    if ($sustaining_amount === null || $sustaining_amount < 50 || $sustaining_amount > 500) {
        $errors[] = "Sustaining membership amount must be between $50 and $500.";
    }
}

// Validate corporate amount if corporate membership
$corporate_amount = null;
if ($membership_level === 'corporate') {
    $corporate_amount = isset($_POST['corporate_amount']) ? (int)$_POST['corporate_amount'] : null;
    if ($corporate_amount === null || $corporate_amount < 500) {
        $errors[] = "Corporate sponsorship amount must be $500 minimum.";
    }
}

// Process interests (optional)
$interests_array = isset($_POST['interests']) && is_array($_POST['interests']) ? $_POST['interests'] : [];
$valid_interests = ['restoration', 'curatorial', 'events', 'maintenance', 'fundraising', 'gift_shop'];
$interests_array = array_filter($interests_array, fn($i) => in_array($i, $valid_interests));
$interests = !empty($interests_array) ? implode(', ', $interests_array) : null;

// Comments (optional)
$comments = !empty($_POST['comments']) ? trim($_POST['comments']) : null;

// If there are validation errors, store form data in session and redirect back
if (!empty($errors)) {
    $_SESSION['form_errors'] = $errors;
    $_SESSION['form_data'] = $_POST; // Store all POST data for repopulation
    header('Location: add.php');
    exit;
}

// Get database connection
$pdo = getDbConnection();

// Insert the application into the database (member_id is NULL for manual entries)
try {
    $stmt = $pdo->prepare("
        INSERT INTO membership_applications 
        (name, email, phone, address, city, state, zip, membership_level, 
         sustaining_amount, corporate_amount, interests, comments, status, member_id, created_at, updated_at)
        VALUES 
        (:name, :email, :phone, :address, :city, :state, :zip, :membership_level, 
         :sustaining_amount, :corporate_amount, :interests, :comments, 'pending', NULL, NOW(), NOW())
    ");
    
    $stmt->execute([
        ':name' => $name,
        ':email' => $email,
        ':phone' => $phone,
        ':address' => $address,
        ':city' => $city,
        ':state' => $state,
        ':zip' => $zip,
        ':membership_level' => $membership_level,
        ':sustaining_amount' => $sustaining_amount,
        ':corporate_amount' => $corporate_amount,
        ':interests' => $interests,
        ':comments' => $comments
    ]);
    
    $application_id = $pdo->lastInsertId();
    
    // Set success message
    $_SESSION['success_message'] = "Paper application for {$name} has been successfully added to pending applications (ID: {$application_id}).";
    
    // Redirect to applications list
    header('Location: /admin/membership/pending');
    exit;
    
} catch (PDOException $e) {
    // Log error for debugging
    error_log("Error inserting manual application: " . $e->getMessage());
    
    // Check for duplicate entry (if there's a unique constraint on email or similar)
    if ($e->getCode() == 23000) {
        $_SESSION['form_errors'] = ["This email address may already have a pending application."];
    } else {
        $_SESSION['form_errors'] = ["An unexpected error occurred. Please try again."];
    }
    
    $_SESSION['form_data'] = $_POST;
    header('Location: add.php');
    exit;
}
