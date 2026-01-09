<?php
/**
 * Admin: Registered Members List
 *
 * View and manage all registered user accounts
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
    header('Location: /members/');
    exit;
}

$pdo = getDbConnection();

// Filters
$filter = $_GET['filter'] ?? 'all';
$search = trim($_GET['search'] ?? '');

// Pagination
$page = max(1, (int)($_GET['page'] ?? 1));
$per_page = 25;
$offset = ($page - 1) * $per_page;

// Build query based on filter
$where_clauses = [];
$params = [];

switch ($filter) {
    case 'active':
        $where_clauses[] = "membership_status != 'inactive' AND membership_expires_at > CURDATE()";
        break;
    case 'inactive':
        $where_clauses[] = "(membership_status = 'inactive' OR membership_expires_at IS NULL OR membership_expires_at <= CURDATE())";
        break;
    case 'never_activated':
        $where_clauses[] = "membership_activated_at IS NULL";
        break;
    case 'expiring':
        $where_clauses[] = "membership_expires_at BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 30 DAY)";
        break;
    case 'new':
        $where_clauses[] = "created_at >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)";
        break;
}

if ($search !== '') {
    $where_clauses[] = "(first_name LIKE :search OR last_name LIKE :search OR email LIKE :search)";
    $params[':search'] = '%' . $search . '%';
}

$where_sql = !empty($where_clauses) ? 'WHERE ' . implode(' AND ', $where_clauses) : '';

// Get total count
$count_sql = "SELECT COUNT(*) FROM members {$where_sql}";
$count_stmt = $pdo->prepare($count_sql);
$count_stmt->execute($params);
$total_members = (int)$count_stmt->fetchColumn();
$total_pages = max(1, (int)ceil($total_members / $per_page));

// Fetch members
$sql = "SELECT id, first_name, last_name, email, user_role, is_key_holder,
               membership_status, membership_term, membership_expires_at,
               membership_activated_at, created_at
        FROM members
        {$where_sql}
        ORDER BY created_at DESC
        LIMIT {$per_page} OFFSET {$offset}";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$members = $stmt->fetchAll();

// Get filter counts
$filter_counts = [];
try {
    $counts_sql = "SELECT
        COUNT(*) as total,
        SUM(CASE WHEN membership_status != 'inactive' AND membership_expires_at > CURDATE() THEN 1 ELSE 0 END) as active,
        SUM(CASE WHEN membership_status = 'inactive' OR membership_expires_at IS NULL OR membership_expires_at <= CURDATE() THEN 1 ELSE 0 END) as inactive,
        SUM(CASE WHEN membership_activated_at IS NULL THEN 1 ELSE 0 END) as never_activated,
        SUM(CASE WHEN membership_expires_at BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 30 DAY) THEN 1 ELSE 0 END) as expiring,
        SUM(CASE WHEN created_at >= DATE_SUB(CURDATE(), INTERVAL 30 DAY) THEN 1 ELSE 0 END) as new_signups
    FROM members";
    $filter_counts = $pdo->query($counts_sql)->fetch();
} catch (PDOException $e) {
    error_log('Filter counts error: ' . $e->getMessage());
}

$page_title = 'Registered Members | Arizona Railway Museum';
require_once __DIR__ . '/../../assets/header.php';
?>

</div></div><!-- Close grid-container and page-content for full-width hero -->

<section class="arm-hero" style="margin-top: -4.5rem; padding: 2.5rem 0 2rem;">
    <div class="grid-container">
        <div class="grid-x grid-margin-x align-middle">
            <div class="small-12 cell">
                <h1 style="margin-bottom: 0.25rem;">Registered Members</h1>
                <p class="lead" style="margin-bottom: 0;">View and manage all registered user accounts.</p>
            </div>
        </div>
    </div>
</section>

<div class="page-content">
<div class="grid-container" style="padding-top: 2rem; margin-bottom: 2rem;">

<div class="grid-x grid-margin-x">
    <div class="small-12 cell">

        <!-- Filter Tabs -->
        <div style="margin: 1.5rem 0 0 0; border-bottom: 2px solid #e0e0e0;">
            <div style="display: flex; gap: 0.5rem; flex-wrap: wrap; justify-content: space-between; align-items: flex-end;">
                <div style="display: flex; gap: 0.5rem; flex-wrap: wrap;">
                    <a href="?filter=all"
                       class="button <?php echo $filter === 'all' ? 'primary' : 'secondary'; ?>"
                       style="margin-bottom: -2px; border-radius: 5px 5px 0 0;">
                        All
                        <span class="badge" style="background: #fff; color: #1779ba; margin-left: 0.5rem;">
                            <?php echo (int)($filter_counts['total'] ?? 0); ?>
                        </span>
                    </a>

                    <a href="?filter=active"
                       class="button <?php echo $filter === 'active' ? 'primary' : 'secondary'; ?>"
                       style="margin-bottom: -2px; border-radius: 5px 5px 0 0;">
                        Active
                        <?php if ((int)($filter_counts['active'] ?? 0) > 0): ?>
                            <span class="badge" style="background: #fff; color: #1779ba; margin-left: 0.5rem;">
                                <?php echo (int)$filter_counts['active']; ?>
                            </span>
                        <?php endif; ?>
                    </a>

                    <a href="?filter=inactive"
                       class="button <?php echo $filter === 'inactive' ? 'primary' : 'secondary'; ?>"
                       style="margin-bottom: -2px; border-radius: 5px 5px 0 0;">
                        Inactive
                        <?php if ((int)($filter_counts['inactive'] ?? 0) > 0): ?>
                            <span class="badge" style="background: #fff; color: #1779ba; margin-left: 0.5rem;">
                                <?php echo (int)$filter_counts['inactive']; ?>
                            </span>
                        <?php endif; ?>
                    </a>

                    <a href="?filter=never_activated"
                       class="button <?php echo $filter === 'never_activated' ? 'primary' : 'secondary'; ?>"
                       style="margin-bottom: -2px; border-radius: 5px 5px 0 0;">
                        Never Activated
                        <?php if ((int)($filter_counts['never_activated'] ?? 0) > 0): ?>
                            <span class="badge" style="background: #fff; color: #1779ba; margin-left: 0.5rem;">
                                <?php echo (int)$filter_counts['never_activated']; ?>
                            </span>
                        <?php endif; ?>
                    </a>

                    <a href="?filter=expiring"
                       class="button <?php echo $filter === 'expiring' ? 'primary' : 'secondary'; ?>"
                       style="margin-bottom: -2px; border-radius: 5px 5px 0 0;">
                        Expiring Soon
                        <?php if ((int)($filter_counts['expiring'] ?? 0) > 0): ?>
                            <span class="badge" style="background: #fff; color: #1779ba; margin-left: 0.5rem;">
                                <?php echo (int)$filter_counts['expiring']; ?>
                            </span>
                        <?php endif; ?>
                    </a>

                    <a href="?filter=new"
                       class="button <?php echo $filter === 'new' ? 'primary' : 'secondary'; ?>"
                       style="margin-bottom: -2px; border-radius: 5px 5px 0 0;">
                        New (30 days)
                        <?php if ((int)($filter_counts['new_signups'] ?? 0) > 0): ?>
                            <span class="badge" style="background: #fff; color: #1779ba; margin-left: 0.5rem;">
                                <?php echo (int)$filter_counts['new_signups']; ?>
                            </span>
                        <?php endif; ?>
                    </a>
                </div>

                <!-- Search -->
                <form method="get" style="display: flex; gap: 0.5rem; margin-bottom: 0.5rem;">
                    <input type="hidden" name="filter" value="<?php echo htmlspecialchars($filter, ENT_QUOTES, 'UTF-8'); ?>">
                    <input type="text" name="search" placeholder="Search..."
                           value="<?php echo htmlspecialchars($search, ENT_QUOTES, 'UTF-8'); ?>"
                           style="margin: 0; width: 180px;">
                    <button type="submit" class="button primary small" style="margin: 0; border-radius: 8px;">Search</button>
                    <?php if ($search !== ''): ?>
                        <a href="?filter=<?php echo htmlspecialchars($filter, ENT_QUOTES, 'UTF-8'); ?>" class="button secondary small" style="margin: 0; border-radius: 8px;">Clear</a>
                    <?php endif; ?>
                </form>
            </div>
        </div>

        <div class="card arm-card" style="margin-top: 0; border-radius: 0 0 8px 8px;">
            <div class="card-section">
                <?php if (empty($members)): ?>
                    <p style="margin: 0; text-align: center; color: #666;">No members found matching your criteria.</p>
                <?php else: ?>
                    <div class="arm-card-table-wrapper">
                        <table class="hover" style="border-radius: 8px; overflow: hidden; border: 1px solid #e0e0e0;">
                            <thead>
                            <tr>
                                <th style="padding: 0.75rem;">Name</th>
                                <th style="padding: 0.75rem;">Email</th>
                                <th style="padding: 0.75rem;">Status</th>
                                <th style="padding: 0.75rem;">Role</th>
                                <th style="padding: 0.75rem;">Expires</th>
                                <th style="padding: 0.75rem;">Registered</th>
                                <th width="100" class="text-center" style="padding: 0.75rem;">Actions</th>
                            </tr>
                            </thead>
                            <tbody>
                            <?php foreach ($members as $member): ?>
                                <?php
                                $name = trim($member['first_name'] . ' ' . $member['last_name']);
                                $is_active = $member['membership_status'] !== 'inactive'
                                             && $member['membership_expires_at']
                                             && strtotime($member['membership_expires_at']) > time();
                                $is_expiring = $member['membership_expires_at']
                                               && strtotime($member['membership_expires_at']) > time()
                                               && strtotime($member['membership_expires_at']) <= strtotime('+30 days');
                                $never_activated = empty($member['membership_activated_at']);
                                ?>
                                <tr>
                                    <td style="padding: 0.75rem;">
                                        <strong style="color: #1779ba;">
                                            <?php echo htmlspecialchars($name ?: '(No name)', ENT_QUOTES, 'UTF-8'); ?>
                                        </strong>
                                    </td>
                                    <td style="padding: 0.75rem; font-size: 0.9rem;">
                                        <?php echo htmlspecialchars($member['email'], ENT_QUOTES, 'UTF-8'); ?>
                                    </td>
                                    <td style="padding: 0.75rem;">
                                        <?php if ($never_activated): ?>
                                            <span class="label warning" style="font-size: 0.75rem;">Not Activated</span>
                                        <?php elseif ($is_active): ?>
                                            <span class="label success" style="font-size: 0.75rem;">
                                                <?php echo htmlspecialchars(ucwords(str_replace('_', ' ', $member['membership_status'])), ENT_QUOTES, 'UTF-8'); ?>
                                            </span>
                                        <?php else: ?>
                                            <span class="label secondary" style="font-size: 0.75rem;">Inactive</span>
                                        <?php endif; ?>
                                        <?php if ($member['is_key_holder']): ?>
                                            <span class="label" style="font-size: 0.75rem; margin-left: 0.25rem; background: #d4a017; color: #000;">Key Holder</span>
                                        <?php endif; ?>
                                        <?php if ($is_expiring): ?>
                                            <span class="label alert" style="font-size: 0.75rem; margin-left: 0.25rem;">Expiring</span>
                                        <?php endif; ?>
                                    </td>
                                    <td style="padding: 0.75rem;">
                                        <?php if ($member['user_role'] === 'admin'): ?>
                                            <span class="label primary" style="font-size: 0.75rem;">Admin</span>
                                        <?php else: ?>
                                            <span style="color: #666; font-size: 0.85rem;">User</span>
                                        <?php endif; ?>
                                    </td>
                                    <td style="padding: 0.75rem; font-size: 0.85rem;">
                                        <?php if ($member['membership_expires_at']): ?>
                                            <?php
                                            $exp_date = strtotime($member['membership_expires_at']);
                                            $is_expired = $exp_date < time();
                                            ?>
                                            <span style="<?php echo $is_expired ? 'color: #cc4b37;' : ''; ?>">
                                                <?php echo date('M j, Y', $exp_date); ?>
                                            </span>
                                        <?php else: ?>
                                            <span style="color: #999;">&mdash;</span>
                                        <?php endif; ?>
                                    </td>
                                    <td style="padding: 0.75rem; font-size: 0.85rem; color: #666;">
                                        <?php echo date('M j, Y', strtotime($member['created_at'])); ?>
                                    </td>
                                    <td class="text-center" style="padding: 0.75rem;">
                                        <a href="/admin/membership/edit/<?php echo (int)$member['id']; ?>"
                                           class="button tiny primary"
                                           style="margin: 0; border-radius: 8px;">Edit</a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>

                    <!-- Pagination -->
                    <?php if ($total_pages > 1): ?>
                        <div style="padding: 1rem; border-top: 1px solid #e0e0e0; text-align: center;">
                            <nav aria-label="Pagination">
                                <?php
                                $base_url = "?filter=" . urlencode($filter);
                                if ($search !== '') {
                                    $base_url .= "&search=" . urlencode($search);
                                }
                                ?>
                                <ul class="pagination" style="margin: 0; display: inline-flex; gap: 0.25rem;">
                                    <?php if ($page > 1): ?>
                                        <li><a href="<?php echo $base_url; ?>&page=<?php echo $page - 1; ?>" style="padding: 0.5rem 0.75rem;">&laquo;</a></li>
                                    <?php endif; ?>

                                    <?php
                                    $start = max(1, $page - 2);
                                    $end = min($total_pages, $page + 2);

                                    if ($start > 1) {
                                        echo '<li><a href="' . $base_url . '&page=1" style="padding: 0.5rem 0.75rem;">1</a></li>';
                                        if ($start > 2) echo '<li><span style="padding: 0.5rem;">...</span></li>';
                                    }

                                    for ($i = $start; $i <= $end; $i++):
                                    ?>
                                        <li>
                                            <?php if ($i === $page): ?>
                                                <span style="padding: 0.5rem 0.75rem; background: #1779ba; color: white; border-radius: 4px;"><?php echo $i; ?></span>
                                            <?php else: ?>
                                                <a href="<?php echo $base_url; ?>&page=<?php echo $i; ?>" style="padding: 0.5rem 0.75rem;"><?php echo $i; ?></a>
                                            <?php endif; ?>
                                        </li>
                                    <?php endfor; ?>

                                    <?php
                                    if ($end < $total_pages) {
                                        if ($end < $total_pages - 1) echo '<li><span style="padding: 0.5rem;">...</span></li>';
                                        echo '<li><a href="' . $base_url . '&page=' . $total_pages . '" style="padding: 0.5rem 0.75rem;">' . $total_pages . '</a></li>';
                                    }
                                    ?>

                                    <?php if ($page < $total_pages): ?>
                                        <li><a href="<?php echo $base_url; ?>&page=<?php echo $page + 1; ?>" style="padding: 0.5rem 0.75rem;">&raquo;</a></li>
                                    <?php endif; ?>
                                </ul>
                            </nav>
                        </div>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        </div>

    </div>
</div>

<?php require_once __DIR__ . '/../../assets/footer.php'; ?>
