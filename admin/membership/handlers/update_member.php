<?php
/**
 * Admin Handler: Update Member Membership Status
 *
 * Updates member's membership status and expiration date
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
    header('Location: /admin/index.php');
    exit;
}

// Validate required parameters
if (empty($_POST['member_id']) || empty($_POST['first_name']) || empty($_POST['last_name']) ||
    empty($_POST['email']) || empty($_POST['user_role']) || empty($_POST['membership_status'])) {
    $_SESSION['error_message'] = 'Missing required parameters.';
    header('Location: /admin/membership/');
    exit;
}

$member_id = (int)$_POST['member_id'];
$first_name = trim($_POST['first_name']);
$last_name = trim($_POST['last_name']);
$email = trim($_POST['email']);
$role = $_POST['user_role'];
$membership_status = $_POST['membership_status'];
$membership_term = !empty($_POST['membership_term']) ? $_POST['membership_term'] : null;
$membership_expires_at = !empty($_POST['membership_expires_at']) ? $_POST['membership_expires_at'] : null;
$set_activated_at = isset($_POST['set_activated_at']);
$set_renewed_at = isset($_POST['set_renewed_at']);
$is_key_holder = isset($_POST['is_key_holder']) ? 1 : 0;
$member_titles = $_POST['member_titles'] ?? [];

// Validate email format
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $_SESSION['error_message'] = 'Invalid email address format.';
    header('Location: /admin/membership/edit/' . $member_id);
    exit;
}

// Validate role
$valid_roles = ['user', 'admin'];
if (!in_array($role, $valid_roles)) {
    $_SESSION['error_message'] = 'Invalid role.';
    header('Location: /admin/membership/edit/' . $member_id);
    exit;
}

// Validate membership status
$valid_statuses = [
    'inactive', 'founder', 'traditional_regular', 'traditional_family', 'traditional_senior',
    'docent_regular', 'docent_family', 'docent_senior', 'sustaining', 'corporate', 'life'
];
if (!in_array($membership_status, $valid_statuses)) {
    $_SESSION['error_message'] = 'Invalid membership status.';
    header('Location: /admin/membership/edit/' . $member_id);
    exit;
}

// Validate membership term
$valid_terms = ['1yr', '2yr', 'lifetime', null, ''];
if (!in_array($membership_term, $valid_terms, true)) {
    $_SESSION['error_message'] = 'Invalid membership term.';
    header('Location: /admin/membership/edit/' . $member_id);
    exit;
}

try {
    $pdo = getDbConnection();

    // Verify member exists and get current values for logging
    $check_sql = "SELECT id, first_name, last_name, email, user_role, is_key_holder, membership_status, membership_term,
                         membership_expires_at, membership_activated_at, membership_last_renewed_at
                  FROM members WHERE id = :id";
    $check_stmt = $pdo->prepare($check_sql);
    $check_stmt->execute([':id' => $member_id]);
    $member = $check_stmt->fetch();

    if (!$member) {
        throw new Exception('Member not found.');
    }

    // Store old values for activity log
    $old_values = [
        'first_name' => $member['first_name'],
        'last_name' => $member['last_name'],
        'email' => $member['email'],
        'user_role' => $member['user_role'],
        'is_key_holder' => (int)$member['is_key_holder'],
        'membership_status' => $member['membership_status'],
        'membership_term' => $member['membership_term'],
        'membership_expires_at' => $member['membership_expires_at']
    ];

    // Build update query
    $update_sql = "UPDATE members
                   SET first_name = :first_name,
                       last_name = :last_name,
                       email = :email,
                       user_role = :role,
                       is_key_holder = :is_key_holder,
                       membership_status = :status,
                       membership_term = :term,
                       membership_expires_at = :expires_at";

    $params = [
        ':first_name' => $first_name,
        ':last_name' => $last_name,
        ':email' => $email,
        ':role' => $role,
        ':is_key_holder' => $is_key_holder,
        ':status' => $membership_status,
        ':term' => $membership_term ?: null,
        ':expires_at' => $membership_expires_at,
        ':id' => $member_id
    ];

    if ($set_activated_at) {
        $update_sql .= ", membership_activated_at = IF(membership_activated_at IS NULL, NOW(), membership_activated_at)";
    }

    if ($set_renewed_at) {
        $update_sql .= ", membership_last_renewed_at = NOW()";
    }

    $update_sql .= " WHERE id = :id";

    $update_stmt = $pdo->prepare($update_sql);
    $update_stmt->execute($params);

    // Handle title assignments
    // Get current titles for comparison
    $currentTitlesStmt = $pdo->prepare("
        SELECT title_id FROM member_title_assignments WHERE member_id = :member_id
    ");
    $currentTitlesStmt->execute([':member_id' => $member_id]);
    $currentTitleIds = $currentTitlesStmt->fetchAll(PDO::FETCH_COLUMN);

    // Convert incoming titles to integers for comparison
    $newTitleIds = array_map('intval', $member_titles);

    // Find titles to add and remove
    $titlesToAdd = array_diff($newTitleIds, $currentTitleIds);
    $titlesToRemove = array_diff($currentTitleIds, $newTitleIds);

    // Remove old title assignments
    if (!empty($titlesToRemove)) {
        $removePlaceholders = implode(',', array_fill(0, count($titlesToRemove), '?'));
        $removeStmt = $pdo->prepare("
            DELETE FROM member_title_assignments
            WHERE member_id = ? AND title_id IN ({$removePlaceholders})
        ");
        $removeStmt->execute(array_merge([$member_id], $titlesToRemove));
    }

    // Add new title assignments
    if (!empty($titlesToAdd)) {
        $insertStmt = $pdo->prepare("
            INSERT INTO member_title_assignments (member_id, title_id, assigned_by_id)
            VALUES (:member_id, :title_id, :assigned_by)
        ");
        foreach ($titlesToAdd as $titleId) {
            $insertStmt->execute([
                ':member_id' => $member_id,
                ':title_id' => $titleId,
                ':assigned_by' => $_SESSION['user_id']
            ]);
        }
    }

    // Get title names for logging
    $titleChanges = [];
    if (!empty($titlesToAdd) || !empty($titlesToRemove)) {
        // Get all title names
        $allTitleIds = array_unique(array_merge($titlesToAdd, $titlesToRemove, $newTitleIds, $currentTitleIds));
        if (!empty($allTitleIds)) {
            $titlePlaceholders = implode(',', array_fill(0, count($allTitleIds), '?'));
            $titleNamesStmt = $pdo->prepare("SELECT id, title_name FROM member_titles WHERE id IN ({$titlePlaceholders})");
            $titleNamesStmt->execute(array_values($allTitleIds));
            $titleNames = $titleNamesStmt->fetchAll(PDO::FETCH_KEY_PAIR);

            if (!empty($titlesToAdd)) {
                $addedNames = array_map(fn($id) => $titleNames[$id] ?? "Title #{$id}", $titlesToAdd);
                $titleChanges[] = "titles added: " . implode(', ', $addedNames);
            }
            if (!empty($titlesToRemove)) {
                $removedNames = array_map(fn($id) => $titleNames[$id] ?? "Title #{$id}", $titlesToRemove);
                $titleChanges[] = "titles removed: " . implode(', ', $removedNames);
            }
        }
    }

    // Store new values for activity log
    $new_values = [
        'first_name' => $first_name,
        'last_name' => $last_name,
        'email' => $email,
        'user_role' => $role,
        'is_key_holder' => $is_key_holder,
        'membership_status' => $membership_status,
        'membership_term' => $membership_term,
        'membership_expires_at' => $membership_expires_at
    ];

    // Build change description
    $changes = [];
    if ($old_values['first_name'] !== $first_name || $old_values['last_name'] !== $last_name) {
        $changes[] = "name changed from '{$old_values['first_name']} {$old_values['last_name']}' to '{$first_name} {$last_name}'";
    }
    if ($old_values['email'] !== $email) {
        $changes[] = "email changed from '{$old_values['email']}' to '{$email}'";
    }
    if ($old_values['user_role'] !== $role) {
        $changes[] = "role changed from '{$old_values['user_role']}' to '{$role}'";
    }
    if ($old_values['is_key_holder'] !== $is_key_holder) {
        $changes[] = $is_key_holder ? "granted key holder access" : "revoked key holder access";
    }
    if ($old_values['membership_status'] !== $membership_status) {
        $changes[] = "status changed from '{$old_values['membership_status']}' to '{$membership_status}'";
    }
    if ($old_values['membership_term'] !== $membership_term) {
        $old_term = $old_values['membership_term'] ?: 'none';
        $new_term = $membership_term ?: 'none';
        $changes[] = "term changed from '{$old_term}' to '{$new_term}'";
    }
    if ($old_values['membership_expires_at'] !== $membership_expires_at) {
        $old_exp = $old_values['membership_expires_at'] ?: 'none';
        $new_exp = $membership_expires_at ?: 'none';
        $changes[] = "expiration changed from '{$old_exp}' to '{$new_exp}'";
    }
    if ($set_activated_at && !$member['membership_activated_at']) {
        $changes[] = "activation date set";
    }
    if ($set_renewed_at) {
        $changes[] = "renewal date updated";
    }

    // Add title changes to the change log
    $changes = array_merge($changes, $titleChanges);

    $change_description = !empty($changes) 
        ? "Updated member #{$member_id} ({$first_name} {$last_name}): " . implode(', ', $changes)
        : "Updated member #{$member_id} ({$first_name} {$last_name}) - no changes detected";
    
    // Log activity
    $log_sql = "INSERT INTO activity_logs (
                    user_id, action_type, entity_type, entity_id, 
                    description, old_value, new_value, ip_address, user_agent
                ) VALUES (
                    :user_id, :action_type, :entity_type, :entity_id,
                    :description, :old_value, :new_value, :ip_address, :user_agent
                )";
    
    $log_stmt = $pdo->prepare($log_sql);
    $log_stmt->execute([
        ':user_id' => $_SESSION['user_id'],
        ':action_type' => 'member_updated',
        ':entity_type' => 'member',
        ':entity_id' => $member_id,
        ':description' => $change_description,
        ':old_value' => json_encode($old_values),
        ':new_value' => json_encode($new_values),
        ':ip_address' => $_SERVER['REMOTE_ADDR'] ?? null,
        ':user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? null
    ]);

    // Success message
    $_SESSION['success_message'] = sprintf(
        'Membership status updated for %s %s (Member #%d).',
        htmlspecialchars($first_name, ENT_QUOTES, 'UTF-8'),
        htmlspecialchars($last_name, ENT_QUOTES, 'UTF-8'),
        $member_id
    );

    header('Location: /admin/membership/edit/' . $member_id);
    exit;

} catch (PDOException $e) {
    error_log('Update member error (DB): ' . $e->getMessage());
    $_SESSION['error_message'] = 'Database error occurred while updating member. Please try again.';

    header('Location: /admin/membership/edit/' . $member_id);
    exit;

} catch (Exception $e) {
    $_SESSION['error_message'] = 'Error: ' . $e->getMessage();

    header('Location: /admin/membership/edit/' . $member_id);
    exit;
}
