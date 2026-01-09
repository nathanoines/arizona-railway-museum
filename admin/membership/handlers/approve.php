<?php
/**
 * Admin Handler: Approve Membership Application
 *
 * This script handles the approval of pending membership applications.
 * It performs the following actions within a database transaction:
 *
 * 1. Activates/renews the membership in the members table
 * 2. Updates the application status to 'approved'
 * 3. Logs the activation/renewal in membership_renewals table
 *
 * Expected parameters:
 *   - application_id: ID from membership_applications table
 *   - member_id: ID from members table
 *   - term_months: Number of months for the membership term (default: 12)
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
if (empty($_POST['application_id'])) {
    $_SESSION['error_message'] = 'Missing required parameter: application_id.';
    header('Location: /admin/membership/index.php');
    exit;
}

$application_id = (int)$_POST['application_id'];

try {
    $pdo = getDbConnection();

    // Start transaction
    $pdo->beginTransaction();

    // Step 1: Verify the application exists and is pending, and get the linked member_id
    $check_sql = "SELECT id, name, email, membership_level, status, member_id
                  FROM membership_applications
                  WHERE id = :application_id";
    $check_stmt = $pdo->prepare($check_sql);
    $check_stmt->execute([':application_id' => $application_id]);
    $application = $check_stmt->fetch();

    if (!$application) {
        throw new Exception('Application not found.');
    }

    if ($application['status'] !== 'pending') {
        throw new Exception('Application is not pending (current status: ' . $application['status'] . ').');
    }

    $member_id = $application['member_id'];

    // If member_id is NULL (paper application), check if email matches an existing member
    if (!$member_id) {
        $email_check_sql = "SELECT id FROM members WHERE email = :email";
        $email_check_stmt = $pdo->prepare($email_check_sql);
        $email_check_stmt->execute([':email' => $application['email']]);
        $existing_member = $email_check_stmt->fetch();

        if ($existing_member) {
            // Link to existing member account
            $member_id = $existing_member['id'];

            // Update application to link to this member
            $link_sql = "UPDATE membership_applications SET member_id = :member_id WHERE id = :application_id";
            $link_stmt = $pdo->prepare($link_sql);
            $link_stmt->execute([':member_id' => $member_id, ':application_id' => $application_id]);
        }
        // If no matching member, we DON'T create one - the application stays unlinked
        // Member can register later and get auto-linked, or admin can manually link
    }

    // Map application membership_level to new membership_status and membership_term
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

    // Get membership details from mapping or use defaults
    if (isset($membership_mapping[$application['membership_level']])) {
        $mapping = $membership_mapping[$application['membership_level']];
        $new_status = $mapping['status'];
        $new_term = $mapping['term'];
        $term_months = $mapping['months'];
    } else {
        // Fallback for unknown membership levels
        $new_status = 'traditional_regular';
        $new_term = '1yr';
        $term_months = 12;
    }

    $previous_status = null;
    $previous_expires_at = null;
    $new_expires_at = null;

    // Only update member record if we have a linked member
    if ($member_id) {
        // Verify the member exists
        $member_check_sql = "SELECT id, membership_status, membership_expires_at FROM members WHERE id = :member_id";
        $member_check_stmt = $pdo->prepare($member_check_sql);
        $member_check_stmt->execute([':member_id' => $member_id]);
        $current_member = $member_check_stmt->fetch();

        if (!$current_member) {
            throw new Exception('Linked member account (ID ' . $member_id . ') not found.');
        }

        $previous_status = $current_member['membership_status'];
        $previous_expires_at = $current_member['membership_expires_at'];

        // Update the member record with activation/renewal
        $update_sql = "UPDATE members
                       SET
                           membership_status = :status,
                           membership_term = :term,
                           membership_activated_at =
                               IF(membership_activated_at IS NULL, NOW(), membership_activated_at),
                           membership_last_renewed_at = NOW(),
                           membership_expires_at = CASE
                               WHEN membership_expires_at IS NULL OR membership_expires_at < CURDATE()
                                   THEN DATE_ADD(CURDATE(), INTERVAL :months1 MONTH)
                               ELSE DATE_ADD(membership_expires_at, INTERVAL :months2 MONTH)
                           END
                       WHERE id = :id";

        $update_stmt = $pdo->prepare($update_sql);
        $update_stmt->execute([
            ':status' => $new_status,
            ':term' => $new_term,
            ':months1' => $term_months,
            ':months2' => $term_months,
            ':id' => $member_id
        ]);

        // Get the new expiry date for logging
        $new_expiry_sql = "SELECT membership_expires_at FROM members WHERE id = :id";
        $new_expiry_stmt = $pdo->prepare($new_expiry_sql);
        $new_expiry_stmt->execute([':id' => $member_id]);
        $new_expiry = $new_expiry_stmt->fetch();
        $new_expires_at = $new_expiry['membership_expires_at'];

        // Insert renewal log entry
        $log_note = 'Application #' . $application_id . ' approved - ' . $application['membership_level'];

        $log_sql = "INSERT INTO membership_renewals (
                        member_id,
                        application_id,
                        previous_status,
                        new_status,
                        previous_expires_at,
                        new_expires_at,
                        term_months,
                        note
                    ) VALUES (
                        :member_id,
                        :application_id,
                        :prev_status,
                        :new_status,
                        :prev_expires_at,
                        :new_expires_at,
                        :months,
                        :note
                    )";

        $log_stmt = $pdo->prepare($log_sql);
        $log_stmt->execute([
            ':member_id' => $member_id,
            ':application_id' => $application_id,
            ':prev_status' => $previous_status,
            ':new_status' => $new_status,
            ':prev_expires_at' => $previous_expires_at,
            ':new_expires_at' => $new_expires_at,
            ':months' => $term_months,
            ':note' => $log_note
        ]);
    }

    // Mark the application as approved
    $approve_sql = "UPDATE membership_applications SET status = 'approved' WHERE id = :application_id";
    $approve_stmt = $pdo->prepare($approve_sql);
    $approve_stmt->execute([':application_id' => $application_id]);

    // Log to activity log
    if ($member_id) {
        $activity_description = sprintf(
            "Approved application #%d for %s (%s) - linked to member #%d, %s status, expires %s",
            $application_id,
            $application['name'],
            $application['email'],
            $member_id,
            $new_status,
            date('M j, Y', strtotime($new_expires_at))
        );
    } else {
        $activity_description = sprintf(
            "Approved application #%d for %s (%s) - no member account linked (paper application)",
            $application_id,
            $application['name'],
            $application['email']
        );
    }

    $activity_sql = "INSERT INTO activity_logs (
                        user_id, action_type, entity_type, entity_id,
                        description, old_value, new_value, ip_address, user_agent
                     ) VALUES (
                        :user_id, :action_type, :entity_type, :entity_id,
                        :description, :old_value, :new_value, :ip_address, :user_agent
                     )";

    $activity_stmt = $pdo->prepare($activity_sql);
    $activity_stmt->execute([
        ':user_id' => $_SESSION['user_id'],
        ':action_type' => 'application_approved',
        ':entity_type' => 'membership_application',
        ':entity_id' => $application_id,
        ':description' => $activity_description,
        ':old_value' => json_encode([
            'application_status' => 'pending',
            'member_status' => $previous_status,
            'member_expires_at' => $previous_expires_at,
            'member_id' => $application['member_id']
        ]),
        ':new_value' => json_encode([
            'application_status' => 'approved',
            'member_status' => $member_id ? $new_status : null,
            'member_expires_at' => $new_expires_at,
            'member_id' => $member_id,
            'term_months' => $term_months
        ]),
        ':ip_address' => $_SERVER['REMOTE_ADDR'] ?? null,
        ':user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? null
    ]);

    // Commit transaction
    $pdo->commit();

    // Success message
    if ($member_id) {
        $_SESSION['success_message'] = sprintf(
            'Membership approved for %s (Application #%d). Member ID %d is now active until %s.',
            htmlspecialchars($application['name'], ENT_QUOTES, 'UTF-8'),
            $application_id,
            $member_id,
            date('M j, Y', strtotime($new_expires_at))
        );
    } else {
        $_SESSION['success_message'] = sprintf(
            'Application #%d approved for %s. No member account is linked yet - membership will be applied when they register or are manually linked.',
            $application_id,
            htmlspecialchars($application['name'], ENT_QUOTES, 'UTF-8')
        );
    }

    header('Location: /admin/membership/index.php');
    exit;

} catch (PDOException $e) {
    // Roll back transaction on database error
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    error_log('Membership approval error (DB): ' . $e->getMessage());
    $_SESSION['error_message'] = 'Database error occurred while approving membership. Please try again.';

    header('Location: /admin/membership/index.php');
    exit;

} catch (Exception $e) {
    // Roll back transaction on validation error
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    $_SESSION['error_message'] = 'Error: ' . $e->getMessage();

    header('Location: /admin/membership/index.php');
    exit;
}
