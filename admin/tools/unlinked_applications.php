<?php
/**
 * Admin Tools: Unlinked Applications
 *
 * View and manage membership applications that are not linked to member accounts.
 * Restricted to super admins only.
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
    header('Location: /members/index.php');
    exit;
}

// Check if user is super admin
$isSuperAdmin = $_SESSION['is_super_admin'] ?? false;
if (!$isSuperAdmin) {
    $_SESSION['error_message'] = 'Access denied. Super admin privileges required.';
    header('Location: /admin/index.php');
    exit;
}

$page_title = "Unlinked Applications | Admin Tools | Arizona Railway Museum";

$pdo = getDbConnection();

// Get filter
$filter_status = $_GET['status'] ?? 'all';
$allowed_statuses = ['all', 'pending', 'approved', 'rejected'];
if (!in_array($filter_status, $allowed_statuses)) {
    $filter_status = 'all';
}

// Get unlinked applications
try {
    $base_sql = "SELECT id, name, email, phone, membership_level, status, created_at
                 FROM membership_applications
                 WHERE member_id IS NULL";

    if ($filter_status === 'all') {
        $sql = $base_sql . " ORDER BY created_at DESC";
        $stmt = $pdo->query($sql);
    } else {
        $sql = $base_sql . " AND status = :status ORDER BY created_at DESC";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([':status' => $filter_status]);
    }

    $applications = $stmt->fetchAll();

    // Get counts
    $count_sql = "SELECT
        COUNT(*) as total,
        SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending,
        SUM(CASE WHEN status = 'approved' THEN 1 ELSE 0 END) as approved,
        SUM(CASE WHEN status = 'rejected' THEN 1 ELSE 0 END) as rejected
    FROM membership_applications
    WHERE member_id IS NULL";
    $counts = $pdo->query($count_sql)->fetch();

} catch (PDOException $e) {
    error_log('Unlinked applications error: ' . $e->getMessage());
    $applications = [];
    $counts = ['total' => 0, 'pending' => 0, 'approved' => 0, 'rejected' => 0];
}

require_once __DIR__ . '/../../assets/header.php';
?>
</div></div><!-- Close grid-container and page-content for full-width hero -->

<section class="arm-hero" style="margin-top: -4.5rem; padding: 2.5rem 0 2rem;">
    <div class="grid-container">
        <div class="grid-x grid-margin-x align-middle">
            <div class="small-12 cell">
                <h1 style="margin-bottom: 0.25rem;">Unlinked Applications</h1>
                <p class="lead" style="margin-bottom: 0;">Applications without linked member accounts (guest submissions).</p>
            </div>
        </div>
    </div>
</section>

<div class="page-content">
<div class="grid-container" style="padding-top: 2rem; margin-bottom: 2rem;">

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

<div class="grid-x grid-margin-x">
    <div class="small-12 cell">
        <!-- Filter Tabs -->
        <div style="margin-bottom: 1rem; border-bottom: 2px solid #e0e0e0;">
            <div style="display: flex; gap: 0.5rem; flex-wrap: wrap;">
                <a href="?status=all"
                   class="button <?php echo $filter_status === 'all' ? 'primary' : 'secondary'; ?> small"
                   style="margin-bottom: -2px; border-radius: 5px 5px 0 0;">
                    All <span class="badge" style="background: #fff; color: #1779ba; margin-left: 0.5rem;"><?php echo (int)$counts['total']; ?></span>
                </a>
                <a href="?status=pending"
                   class="button <?php echo $filter_status === 'pending' ? 'primary' : 'secondary'; ?> small"
                   style="margin-bottom: -2px; border-radius: 5px 5px 0 0;">
                    Pending <?php if ((int)$counts['pending'] > 0): ?><span class="badge" style="background: #fff; color: #1779ba; margin-left: 0.5rem;"><?php echo (int)$counts['pending']; ?></span><?php endif; ?>
                </a>
                <a href="?status=approved"
                   class="button <?php echo $filter_status === 'approved' ? 'primary' : 'secondary'; ?> small"
                   style="margin-bottom: -2px; border-radius: 5px 5px 0 0;">
                    Approved <?php if ((int)$counts['approved'] > 0): ?><span class="badge" style="background: #fff; color: #1779ba; margin-left: 0.5rem;"><?php echo (int)$counts['approved']; ?></span><?php endif; ?>
                </a>
                <a href="?status=rejected"
                   class="button <?php echo $filter_status === 'rejected' ? 'primary' : 'secondary'; ?> small"
                   style="margin-bottom: -2px; border-radius: 5px 5px 0 0;">
                    Rejected <?php if ((int)$counts['rejected'] > 0): ?><span class="badge" style="background: #fff; color: #1779ba; margin-left: 0.5rem;"><?php echo (int)$counts['rejected']; ?></span><?php endif; ?>
                </a>
            </div>
        </div>

        <div class="card arm-card">
            <div class="card-section">
                <?php if (empty($applications)): ?>
                    <p style="margin: 0; text-align: center; color: #666;">
                        No unlinked <?php echo $filter_status === 'all' ? '' : $filter_status; ?> applications found.
                    </p>
                <?php else: ?>
                    <div class="callout secondary" style="margin-bottom: 1rem;">
                        <p style="margin: 0; font-size: 0.9rem;">
                            <strong>Note:</strong> These applications were submitted by guests without accounts.
                            Use "View Details" to link them to existing members or approve them to auto-link when the member registers.
                        </p>
                    </div>

                    <div style="overflow-x: auto;">
                        <table class="hover" style="border-radius: 8px; overflow: hidden; border: 1px solid #e0e0e0;">
                            <thead>
                                <tr>
                                    <th width="50">ID</th>
                                    <th>Name</th>
                                    <th>Email</th>
                                    <th>Membership Level</th>
                                    <th width="120">Submitted</th>
                                    <th width="100">Status</th>
                                    <th width="120" class="text-center">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($applications as $app): ?>
                                    <tr>
                                        <td><?php echo $app['id']; ?></td>
                                        <td>
                                            <strong><?php echo htmlspecialchars($app['name'], ENT_QUOTES, 'UTF-8'); ?></strong>
                                            <?php if ($app['phone']): ?>
                                                <br><small style="color: #666;">
                                                    <?php
                                                    $phone = preg_replace('/\D/', '', $app['phone']);
                                                    if (strlen($phone) == 10) {
                                                        echo '(' . substr($phone, 0, 3) . ') ' . substr($phone, 3, 3) . '-' . substr($phone, 6);
                                                    } else {
                                                        echo htmlspecialchars($app['phone'], ENT_QUOTES, 'UTF-8');
                                                    }
                                                    ?>
                                                </small>
                                            <?php endif; ?>
                                        </td>
                                        <td><?php echo htmlspecialchars($app['email'], ENT_QUOTES, 'UTF-8'); ?></td>
                                        <td>
                                            <?php
                                            $level_display = str_replace('_', ' ', $app['membership_level']);
                                            echo htmlspecialchars(ucwords($level_display), ENT_QUOTES, 'UTF-8');
                                            ?>
                                        </td>
                                        <td>
                                            <small><?php echo date('M j, Y', strtotime($app['created_at'])); ?></small>
                                        </td>
                                        <td>
                                            <span class="label <?php
                                                echo $app['status'] === 'pending' ? 'warning' :
                                                    ($app['status'] === 'approved' ? 'success' : 'alert');
                                            ?>">
                                                <?php echo strtoupper($app['status']); ?>
                                            </span>
                                        </td>
                                        <td class="text-center">
                                            <a href="/admin/membership/view/<?php echo $app['id']; ?>"
                                               class="button tiny primary"
                                               style="margin: 0; border-radius: 8px;">
                                                View Details
                                            </a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<div class="grid-x grid-margin-x" style="margin-top: 1rem;">
    <div class="small-12 cell">
        <a href="/admin/tools/" class="button secondary" style="border-radius: 8px;">&larr; Back to Tools</a>
        <a href="/admin/membership/" class="button secondary" style="border-radius: 8px;">All Applications</a>
    </div>
</div>

<?php require_once __DIR__ . '/../../assets/footer.php'; ?>
