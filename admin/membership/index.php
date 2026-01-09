<?php
/**
 * Admin Interface: Membership Applications
 *
 * Lists all membership applications with filtering by status
 * Allows admins to approve or reject applications
 */

declare(strict_types=1);

session_start();
require_once __DIR__ . '/../../config/db.php';

// Helper function to format phone numbers
function formatPhone($phone) {
    if (empty($phone)) return 'No phone';
    $phone = preg_replace('/\D/', '', $phone);
    if (strlen($phone) == 10) {
        return '(' . substr($phone, 0, 3) . ') ' . substr($phone, 3, 3) . '-' . substr($phone, 6);
    }
    return $phone;
}

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

$page_title = "Membership Applications | Admin | Arizona Railway Museum";

// Get filter status from query string
$filter_status = $_GET['status'] ?? 'all';
$allowed_statuses = ['all', 'pending', 'approved', 'rejected'];
if (!in_array($filter_status, $allowed_statuses)) {
    $filter_status = 'all';
}

try {
    $pdo = getDbConnection();

    // Build query based on filter - JOIN with members to get current info
    $select_sql = "SELECT 
                    ma.*,
                    m.first_name,
                    m.last_name,
                    m.email as member_email
                FROM membership_applications ma
                LEFT JOIN members m ON ma.member_id = m.id";
    
    if ($filter_status === 'all') {
        $sql = $select_sql . " ORDER BY ma.created_at DESC";
        $stmt = $pdo->query($sql);
    } else {
        $sql = $select_sql . " WHERE ma.status = :status ORDER BY ma.created_at DESC";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([':status' => $filter_status]);
    }

    $applications = $stmt->fetchAll();
    
    // Add display names for each application
    foreach ($applications as &$app) {
        if ($app['member_id'] && $app['first_name']) {
            $app['display_name'] = trim($app['first_name'] . ' ' . $app['last_name']);
            $app['display_email'] = $app['member_email'] ?: $app['email'];
        } else {
            $app['display_name'] = $app['name'];
            $app['display_email'] = $app['email'];
        }
    }
    unset($app);

    // Get counts for badges
    $count_sql = "SELECT
                    SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending_count,
                    SUM(CASE WHEN status = 'approved' THEN 1 ELSE 0 END) as approved_count,
                    SUM(CASE WHEN status = 'rejected' THEN 1 ELSE 0 END) as rejected_count,
                    COUNT(*) as total_count
                  FROM membership_applications";
    $count_stmt = $pdo->query($count_sql);
    $counts = $count_stmt->fetch();

} catch (PDOException $e) {
    error_log('Error loading applications: ' . $e->getMessage());
    $applications = [];
    $counts = ['pending_count' => 0, 'approved_count' => 0, 'rejected_count' => 0, 'total_count' => 0];
}

require_once __DIR__ . '/../../assets/header.php';
?>

</div></div><!-- Close grid-container and page-content for full-width hero -->

<section class="arm-hero" style="margin-top: -4.5rem; padding: 2.5rem 0 2rem;">
    <div class="grid-container">
        <div class="grid-x grid-margin-x align-middle">
            <div class="small-12 cell">
                <h1 style="margin-bottom: 0.25rem;">Membership Applications</h1>
                <p class="lead" style="margin-bottom: 0;">Review and manage membership applications.</p>
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

        <!-- Filter Tabs -->
        <div style="margin: 1.5rem 0 0 0; border-bottom: 2px solid #e0e0e0;">
            <div style="display: flex; gap: 0.5rem; flex-wrap: wrap; justify-content: space-between; align-items: center;">
                <div style="display: flex; gap: 0.5rem; flex-wrap: wrap;">
                    <a href="/admin/membership/all"
                       class="button <?php echo $filter_status === 'all' ? 'primary' : 'secondary'; ?>"
                       style="margin-bottom: -2px; border-radius: 5px 5px 0 0;">
                        All Applications
                        <span class="badge" style="background: #fff; color: #1779ba; margin-left: 0.5rem;">
                            <?php echo $counts['total_count']; ?>
                        </span>
                    </a>

                    <a href="/admin/membership/pending"
                       class="button <?php echo $filter_status === 'pending' ? 'primary' : 'secondary'; ?>"
                       style="margin-bottom: -2px; border-radius: 5px 5px 0 0;">
                        Pending
                        <?php if ($counts['pending_count'] > 0): ?>
                            <span class="badge" style="background: #fff; color: #1779ba; margin-left: 0.5rem;">
                                <?php echo $counts['pending_count']; ?>
                            </span>
                        <?php endif; ?>
                    </a>

                    <a href="/admin/membership/approved"
                       class="button <?php echo $filter_status === 'approved' ? 'primary' : 'secondary'; ?>"
                       style="margin-bottom: -2px; border-radius: 5px 5px 0 0;">
                        Approved
                        <?php if ($counts['approved_count'] > 0): ?>
                            <span class="badge" style="background: #fff; color: #1779ba; margin-left: 0.5rem;">
                                <?php echo $counts['approved_count']; ?>
                            </span>
                        <?php endif; ?>
                    </a>

                    <a href="/admin/membership/rejected"
                       class="button <?php echo $filter_status === 'rejected' ? 'primary' : 'secondary'; ?>"
                       style="margin-bottom: -2px; border-radius: 5px 5px 0 0;">
                        Rejected
                        <?php if ($counts['rejected_count'] > 0): ?>
                            <span class="badge" style="background: #fff; color: #1779ba; margin-left: 0.5rem;">
                                <?php echo $counts['rejected_count']; ?>
                            </span>
                        <?php endif; ?>
                    </a>
                </div>
                <a href="add.php" class="button primary small" style="border-radius: 8px; margin: 0 0 0.5rem 0;">+ Add Paper Application</a>
            </div>
        </div>

        <div class="card arm-card" style="margin-top: 0;">
            <div class="card-section">
                <?php if (empty($applications)): ?>
                    <p style="margin: 0; text-align: center; color: #666;">No <?php echo $filter_status === 'all' ? '' : $filter_status; ?> applications found.</p>
                <?php else: ?>
                    <div class="arm-card-table-wrapper">
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
                                            <strong><?php echo htmlspecialchars($app['display_name'], ENT_QUOTES, 'UTF-8'); ?></strong><br>
                                            <small style="color: #666;">
                                                <?php echo formatPhone($app['phone']); ?>
                                            </small>
                                        </td>
                                        <td><?php echo htmlspecialchars($app['display_email'], ENT_QUOTES, 'UTF-8'); ?></td>
                                        <td>
                                            <?php
                                            $level_display = str_replace('_', ' ', $app['membership_level']);
                                            echo htmlspecialchars(ucwords($level_display), ENT_QUOTES, 'UTF-8');
                                            
                                            if ($app['sustaining_amount']) {
                                                echo '<br><small style="color: #666;">$' . number_format((float)$app['sustaining_amount'], 2) . '</small>';
                                            }
                                            if ($app['corporate_amount']) {
                                                echo '<br><small style="color: #666;">$' . number_format((float)$app['corporate_amount'], 2) . '</small>';
                                            }
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

<?php require_once __DIR__ . '/../../assets/footer.php'; ?>
