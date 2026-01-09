<?php
/**
 * Admin Handler: Link Application to Member
 *
 * Manually links a membership application to an existing member account.
 * If the application is already approved, also applies the membership
 * status/term/expiration to the member.
 *
 * Expected parameters:
 *   - application_id: ID from membership_applications table
 *   - member_id: ID from members table to link to
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
if (empty($_POST['application_id']) || empty($_POST['member_id'])) {
    $_SESSION['error_message'] = 'Missing required parameters.';
    header('Location: /admin/membership/index.php');
    exit;
}

$application_id = (int)$_POST['application_id'];
$member_id = (int)$_POST['member_id'];

try {
    $pdo = getDbConnection();
    $pdo->beginTransaction();

    // Step 1: Verify the application exists and is not already linked
    $app_sql = "SELECT id, name, email, membership_level, status, member_id
                FROM membership_applications
                WHERE id = :application_id";
    $app_stmt = $pdo->prepare($app_sql);
    $app_stmt->execute([':application_id' => $application_id]);
    $application = $app_stmt->fetch();

    if (!$application) {
        throw new Exception('Application not found.');
    }

    if ($application['member_id']) {
        throw new Exception('Application is already linked to a member (ID: ' . $application['member_id'] . ').');
    }

    // Step 2: Verify the target member exists
    $member_sql = "SELECT id, first_name, last_name, email, membership_status, membership_expires_at
                   FROM members
                   WHERE id = :member_id";
    $member_stmt = $pdo->prepare($member_sql);
    $member_stmt->execute([':member_id' => $member_id]);
    $member = $member_stmt->fetch();

    if (!$member) {
        throw new Exception('Target member not found.');
    }

    // Step 3: Link the application to the member
    $link_sql = "UPDATE membership_applications
                 SET member_id = :member_id
                 WHERE id = :application_id";
    $link_stmt = $pdo->prepare($link_sql);
    $link_stmt->execute([
        ':member_id' => $member_id,
        ':application_id' => $application_id
    ]);

    $membership_applied = false;
    $new_status = null;
    $new_expires_at = null;

    // Step 4: If application is approved, apply membership to member
    if ($application['status'] === 'approved') {
        // Membership level mapping (same as approve.php)
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

        $previous_status = $member['membership_status'];
        $previous_expires_at = $member['membership_expires_at'];

        // Update member with membership details
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

        // Get new expiry date
        $expiry_sql = "SELECT membership_expires_at FROM members WHERE id = :id";
        $expiry_stmt = $pdo->prepare($expiry_sql);
        $expiry_stmt->execute([':id' => $member_id]);
        $new_expires_at = $expiry_stmt->fetchColumn();

        // Log to membership_renewals
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
            ':note' => 'Manual link - Application #' . $application_id . ' linked and membership applied'
        ]);

        $membership_applied = true;
    }

    // Step 5: Log to activity_logs
    $member_name = trim($member['first_name'] . ' ' . $member['last_name']);
    $activity_description = sprintf(
        "Manually linked application #%d (%s) to member #%d (%s)%s",
        $application_id,
        $application['name'],
        $member_id,
        $member_name,
        $membership_applied ? ' - membership status applied' : ''
    );

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
        ':action_type' => 'application_linked',
        ':entity_type' => 'membership_application',
        ':entity_id' => $application_id,
        ':description' => $activity_description,
        ':old_value' => json_encode([
            'member_id' => null,
            'member_status' => $member['membership_status'],
            'member_expires_at' => $member['membership_expires_at']
        ]),
        ':new_value' => json_encode([
            'member_id' => $member_id,
            'member_status' => $membership_applied ? $new_status : $member['membership_status'],
            'member_expires_at' => $membership_applied ? $new_expires_at : $member['membership_expires_at'],
            'membership_applied' => $membership_applied
        ]),
        ':ip_address' => $_SERVER['REMOTE_ADDR'] ?? null,
        ':user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? null
    ]);

    $pdo->commit();

    // Success message
    if ($membership_applied) {
        $_SESSION['success_message'] = sprintf(
            'Application #%d linked to %s (Member #%d). Membership status "%s" applied, expires %s.',
            $application_id,
            htmlspecialchars($member_name, ENT_QUOTES, 'UTF-8'),
            $member_id,
            $new_status,
            date('M j, Y', strtotime($new_expires_at))
        );
    } else {
        $_SESSION['success_message'] = sprintf(
            'Application #%d linked to %s (Member #%d). Application is pending - membership will be applied upon approval.',
            $application_id,
            htmlspecialchars($member_name, ENT_QUOTES, 'UTF-8'),
            $member_id
        );
    }

    header('Location: /admin/membership/view.php?id=' . $application_id);
    exit;

} catch (PDOException $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    error_log('Link member error (DB): ' . $e->getMessage());
    $_SESSION['error_message'] = 'Database error occurred while linking member. Please try again.';

    header('Location: /admin/membership/view.php?id=' . $application_id);
    exit;

} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    $_SESSION['error_message'] = 'Error: ' . $e->getMessage();

    header('Location: /admin/membership/view.php?id=' . $application_id);
    exit;
}