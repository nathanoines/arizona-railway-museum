<?php
/**
 * Activity Log Viewer
 *
 * Displays all activity log entries with full details.
 */

declare(strict_types=1);

session_start();
require_once __DIR__ . '/../../config/db.php';

// Set timezone to MST (Arizona)
date_default_timezone_set('America/Phoenix');

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

$isSuperAdmin = $_SESSION['is_super_admin'] ?? false;

$pdo = getDbConnection();

// Pagination
$per_page = 25;
$page = isset($_GET['page']) && ctype_digit($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($page - 1) * $per_page;

// Filters
$filter_action = $_GET['action'] ?? '';
$filter_entity = $_GET['entity'] ?? '';
$filter_user = $_GET['user'] ?? '';
$filter_date_from = $_GET['date_from'] ?? '';
$filter_date_to = $_GET['date_to'] ?? '';

// Build query with filters
$where_clauses = [];
$params = [];

// Filter out archived entries
$where_clauses[] = "al.archived_at IS NULL";

if ($filter_action !== '') {
    $where_clauses[] = "al.action_type = :action_type";
    $params[':action_type'] = $filter_action;
}

if ($filter_entity !== '') {
    $where_clauses[] = "al.entity_type = :entity_type";
    $params[':entity_type'] = $filter_entity;
}

if ($filter_user !== '' && ctype_digit($filter_user)) {
    $where_clauses[] = "al.user_id = :user_id";
    $params[':user_id'] = (int)$filter_user;
}

if ($filter_date_from !== '') {
    $where_clauses[] = "DATE(al.created_at) >= :date_from";
    $params[':date_from'] = $filter_date_from;
}

if ($filter_date_to !== '') {
    $where_clauses[] = "DATE(al.created_at) <= :date_to";
    $params[':date_to'] = $filter_date_to;
}

$where_sql = '';
if (!empty($where_clauses)) {
    $where_sql = 'WHERE ' . implode(' AND ', $where_clauses);
}

// Get total count
$count_sql = "SELECT COUNT(*) FROM activity_logs al $where_sql";
$count_stmt = $pdo->prepare($count_sql);
$count_stmt->execute($params);
$total_records = (int)$count_stmt->fetchColumn();
$total_pages = (int)ceil($total_records / $per_page);

// Get activity logs with user info
$sql = "SELECT al.*, m.first_name, m.last_name, m.email as user_email
        FROM activity_logs al
        LEFT JOIN members m ON al.user_id = m.id
        $where_sql
        ORDER BY al.created_at DESC
        LIMIT $per_page OFFSET $offset";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$activities = $stmt->fetchAll();

// Get distinct action types for filter dropdown (exclude archived)
$action_types = $pdo->query("SELECT DISTINCT action_type FROM activity_logs WHERE archived_at IS NULL ORDER BY action_type")->fetchAll(PDO::FETCH_COLUMN);

// Get distinct entity types for filter dropdown (exclude archived)
$entity_types = $pdo->query("SELECT DISTINCT entity_type FROM activity_logs WHERE archived_at IS NULL ORDER BY entity_type")->fetchAll(PDO::FETCH_COLUMN);

// Get users who have activity for filter dropdown (exclude archived)
$users_with_activity = $pdo->query("
    SELECT DISTINCT al.user_id, m.first_name, m.last_name
    FROM activity_logs al
    LEFT JOIN members m ON al.user_id = m.id
    WHERE al.user_id IS NOT NULL AND al.archived_at IS NULL
    ORDER BY m.last_name, m.first_name
")->fetchAll();

// Stats summary (exclude archived for accurate counts)
$current_user_id = $_SESSION['user_id'];
$stats_summary = $pdo->query("
    SELECT
        COUNT(*) as all_time_total,
        SUM(CASE WHEN DATE(created_at) = CURDATE() THEN 1 ELSE 0 END) as today_total,
        SUM(CASE WHEN DATE(created_at) = CURDATE() AND user_id = $current_user_id THEN 1 ELSE 0 END) as today_you,
        SUM(CASE WHEN created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY) THEN 1 ELSE 0 END) as week_total
    FROM activity_logs
    WHERE archived_at IS NULL
")->fetch();

$page_title = "Activity Log | Arizona Railway Museum";
require_once __DIR__ . '/../../assets/header.php';
?>

<style>
    .activity-table {
        width: 100%;
        border-collapse: collapse;
        font-size: 0.9rem;
    }
    .activity-table th,
    .activity-table td {
        padding: 0.75rem;
        text-align: left;
        border-bottom: 1px solid #e0e0e0;
        vertical-align: top;
    }
    .activity-table th {
        background: #f5f5f5;
        font-weight: 600;
        white-space: nowrap;
    }
    .activity-table tr:hover {
        background: #fafafa;
    }
    .activity-table .mono {
        font-family: 'Consolas', 'Monaco', monospace;
        font-size: 0.8rem;
        color: #666;
    }
    .activity-table .action-badge {
        display: inline-block;
        padding: 0.2rem 0.5rem;
        border-radius: 4px;
        font-size: 0.75rem;
        font-weight: 600;
        text-transform: uppercase;
    }
    .action-badge.approved, .action-badge.added, .action-badge.created { background: #c8e6c9; color: #2e7d32; }
    .action-badge.rejected, .action-badge.deleted, .action-badge.closed { background: #ffcdd2; color: #c62828; }
    .action-badge.updated, .action-badge.linked { background: #bbdefb; color: #1565c0; }
    .action-badge.unrejected { background: #fff9c4; color: #f57f17; }
    .action-badge.published, .action-badge.opened { background: #b3e5fc; color: #0277bd; }
    .action-badge.vote { background: #e1bee7; color: #7b1fa2; }

    .filter-form {
        background: #f5f5f5;
        padding: 1rem;
        border-radius: 8px;
        margin-bottom: 1.5rem;
    }
    .filter-form label {
        font-size: 0.85rem;
        font-weight: 600;
        margin-bottom: 0.25rem;
    }
    .filter-form select,
    .filter-form input {
        font-size: 0.9rem;
        margin-bottom: 0;
    }

    .pagination {
        display: flex;
        gap: 0.5rem;
        justify-content: center;
        margin-top: 1.5rem;
        flex-wrap: wrap;
    }
    .pagination a,
    .pagination span {
        padding: 0.5rem 0.75rem;
        border: 1px solid #ddd;
        border-radius: 4px;
        text-decoration: none;
        color: #333;
        line-height: 1;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        min-width: 2.5rem;
        box-sizing: border-box;
    }
    .pagination a:hover {
        background: #f0f0f0;
    }
    .pagination .current {
        background: #1779ba;
        color: white;
        border-color: #1779ba;
    }
    .pagination .disabled {
        color: #999;
        pointer-events: none;
    }

    .detail-row {
        display: none;
        background: #fafafa;
    }
    .detail-row td {
        padding: 1rem;
    }
    .detail-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 1rem;
    }
    .detail-item {
        background: white;
        padding: 0.75rem;
        border-radius: 4px;
        border: 1px solid #e0e0e0;
    }
    .detail-item label {
        font-size: 0.75rem;
        color: #888;
        text-transform: uppercase;
        display: block;
        margin-bottom: 0.25rem;
    }
    .detail-item .value {
        font-size: 0.9rem;
        word-break: break-all;
    }
    .expand-btn {
        cursor: pointer;
        color: #1779ba;
        text-decoration: underline;
        font-size: 0.8rem;
    }
</style>

</div></div><!-- Close grid-container and page-content for full-width hero -->

<section class="arm-hero" style="margin-top: -4.5rem; padding: 2.5rem 0 2rem;">
    <div class="grid-container">
        <div class="grid-x grid-margin-x align-middle">
            <div class="small-12 cell">
                <h1 style="margin-bottom: 0.25rem;">Activity Log</h1>
                <p class="lead" style="margin-bottom: 0;">
                    View all administrative actions recorded in the system.<br>
                    <strong><?php echo number_format($total_records); ?></strong> total entries.
                </p>
            </div>
        </div>
    </div>
</section>

<div class="page-content">
<div class="grid-container" style="padding-top: 2rem; margin-bottom: 2rem;">

<?php if (isset($_SESSION['success_message'])): ?>
    <div class="callout success" style="margin-bottom: 1rem;">
        <?php
        echo htmlspecialchars($_SESSION['success_message'], ENT_QUOTES, 'UTF-8');
        unset($_SESSION['success_message']);
        ?>
    </div>
<?php endif; ?>

<?php if (isset($_SESSION['error_message'])): ?>
    <div class="callout alert" style="margin-bottom: 1rem;">
        <?php
        echo htmlspecialchars($_SESSION['error_message'], ENT_QUOTES, 'UTF-8');
        unset($_SESSION['error_message']);
        ?>
    </div>
<?php endif; ?>

<!-- Stats Summary -->
<div class="grid-x grid-margin-x" style="margin-bottom: 1.5rem;">
    <div class="small-6 medium-3 cell">
        <div style="background: #e8f5e9; padding: 1rem; border-radius: 8px; text-align: center;">
            <div style="font-size: 1.75rem; font-weight: 700; color: #2e7d32;">
                <?php echo number_format((int)($stats_summary['today_total'] ?? 0)); ?>
            </div>
            <div style="font-size: 0.85rem; color: #666;">Today</div>
        </div>
    </div>
    <div class="small-6 medium-3 cell">
        <div style="background: #e3f2fd; padding: 1rem; border-radius: 8px; text-align: center;">
            <div style="font-size: 1.75rem; font-weight: 700; color: #1565c0;">
                <?php echo number_format((int)($stats_summary['today_you'] ?? 0)); ?>
            </div>
            <div style="font-size: 0.85rem; color: #666;">By You Today</div>
        </div>
    </div>
    <div class="small-6 medium-3 cell">
        <div style="background: #fff3e0; padding: 1rem; border-radius: 8px; text-align: center;">
            <div style="font-size: 1.75rem; font-weight: 700; color: #e65100;">
                <?php echo number_format((int)($stats_summary['week_total'] ?? 0)); ?>
            </div>
            <div style="font-size: 0.85rem; color: #666;">Last 7 Days</div>
        </div>
    </div>
    <div class="small-6 medium-3 cell">
        <div style="background: #f3e5f5; padding: 1rem; border-radius: 8px; text-align: center;">
            <div style="font-size: 1.75rem; font-weight: 700; color: #7b1fa2;">
                <?php echo number_format((int)($stats_summary['all_time_total'] ?? 0)); ?>
            </div>
            <div style="font-size: 0.85rem; color: #666;">All Time</div>
        </div>
    </div>
</div>

<div class="grid-x grid-margin-x">
    <div class="small-12 cell">
        <!-- Filters -->
        <form method="get" class="filter-form">
            <div class="grid-x grid-margin-x align-bottom">
                <div class="small-6 medium-4 large-2 cell">
                    <label>Action Type</label>
                    <select name="action">
                        <option value="">All Actions</option>
                        <?php foreach ($action_types as $action): ?>
                            <option value="<?php echo htmlspecialchars($action, ENT_QUOTES, 'UTF-8'); ?>"
                                <?php echo $filter_action === $action ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars(ucwords(str_replace('_', ' ', $action)), ENT_QUOTES, 'UTF-8'); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="small-6 medium-4 large-2 cell">
                    <label>Entity Type</label>
                    <select name="entity">
                        <option value="">All Entities</option>
                        <?php foreach ($entity_types as $entity): ?>
                            <option value="<?php echo htmlspecialchars($entity, ENT_QUOTES, 'UTF-8'); ?>"
                                <?php echo $filter_entity === $entity ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars(ucwords($entity), ENT_QUOTES, 'UTF-8'); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="small-6 medium-4 large-2 cell">
                    <label>User</label>
                    <select name="user">
                        <option value="">All Users</option>
                        <?php foreach ($users_with_activity as $user): ?>
                            <option value="<?php echo (int)$user['user_id']; ?>"
                                <?php echo $filter_user === (string)$user['user_id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars(trim($user['first_name'] . ' ' . $user['last_name']) ?: 'User #' . $user['user_id'], ENT_QUOTES, 'UTF-8'); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="small-6 medium-4 large-2 cell">
                    <label>From Date</label>
                    <input type="date" name="date_from" value="<?php echo htmlspecialchars($filter_date_from, ENT_QUOTES, 'UTF-8'); ?>">
                </div>
                <div class="small-6 medium-4 large-2 cell">
                    <label>To Date</label>
                    <input type="date" name="date_to" value="<?php echo htmlspecialchars($filter_date_to, ENT_QUOTES, 'UTF-8'); ?>">
                </div>
                <div class="small-6 medium-4 large-2 cell">
                    <?php
                    // Build export URL with current filters
                    $export_params = [];
                    if ($filter_action !== '') $export_params['action'] = $filter_action;
                    if ($filter_entity !== '') $export_params['entity'] = $filter_entity;
                    if ($filter_user !== '') $export_params['user'] = $filter_user;
                    if ($filter_date_from !== '') $export_params['date_from'] = $filter_date_from;
                    if ($filter_date_to !== '') $export_params['date_to'] = $filter_date_to;
                    $export_url = '/admin/activity/handlers/export_pdf.php' . (!empty($export_params) ? '?' . http_build_query($export_params) : '');
                    ?>
                    <button type="submit" class="button primary small" style="margin-bottom: 0; border-radius: 4px;">Filter</button>
                    <a href="/admin/activity/" class="button secondary small" style="margin-bottom: 0; border-radius: 4px;">Clear</a>
                    <a href="<?php echo htmlspecialchars($export_url, ENT_QUOTES, 'UTF-8'); ?>" class="button success small" style="margin-bottom: 0; border-radius: 4px;" title="Export current view to PDF">PDF</a>
                </div>
            </div>
        </form>

        <!-- Activity Table -->
        <div class="card arm-card">
            <div class="card-section" style="padding: 0; overflow-x: auto;">
                <?php if (empty($activities)): ?>
                    <p style="padding: 2rem; text-align: center; color: #666;">No activity records found.</p>
                <?php else: ?>
                    <table class="activity-table">
                        <thead>
                            <tr>
                                <th style="width: 50px;">ID</th>
                                <th>Timestamp</th>
                                <th>Action</th>
                                <th>Entity</th>
                                <th>Description</th>
                                <th>User</th>
                                <th style="width: 60px;"></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($activities as $activity): ?>
                                <?php
                                $action_class = '';
                                $action_type = $activity['action_type'];
                                if (strpos($action_type, 'approved') !== false || strpos($action_type, 'added') !== false) {
                                    $action_class = 'approved';
                                } elseif (strpos($action_type, 'created') !== false) {
                                    $action_class = 'created';
                                } elseif (strpos($action_type, 'rejected') !== false || strpos($action_type, 'deleted') !== false) {
                                    $action_class = 'rejected';
                                } elseif (strpos($action_type, 'closed') !== false) {
                                    $action_class = 'closed';
                                } elseif (strpos($action_type, 'updated') !== false || strpos($action_type, 'linked') !== false) {
                                    $action_class = 'updated';
                                } elseif (strpos($action_type, 'unrejected') !== false) {
                                    $action_class = 'unrejected';
                                } elseif (strpos($action_type, 'published') !== false || strpos($action_type, 'opened') !== false) {
                                    $action_class = 'published';
                                } elseif (strpos($action_type, 'vote') !== false) {
                                    $action_class = 'vote';
                                }
                                ?>
                                <tr>
                                    <td class="mono"><?php echo (int)$activity['id']; ?></td>
                                    <td>
                                        <div><?php echo date('M j, Y', strtotime($activity['created_at'])); ?></div>
                                        <div class="mono" style="font-size: 0.75rem; color: #888;">
                                            <?php echo date('g:i:s A', strtotime($activity['created_at'])); ?>
                                        </div>
                                    </td>
                                    <td>
                                        <span class="action-badge <?php echo $action_class; ?>">
                                            <?php echo htmlspecialchars(str_replace('_', ' ', $activity['action_type']), ENT_QUOTES, 'UTF-8'); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php echo htmlspecialchars(ucwords(str_replace('_', ' ', $activity['entity_type'] ?? '—')), ENT_QUOTES, 'UTF-8'); ?>
                                        <?php if ($activity['entity_id']): ?>
                                            <span class="mono">#<?php echo (int)$activity['entity_id']; ?></span>
                                        <?php endif; ?>
                                    </td>
                                    <td style="max-width: 300px;">
                                        <?php echo htmlspecialchars($activity['description'] ?? '—', ENT_QUOTES, 'UTF-8'); ?>
                                    </td>
                                    <td>
                                        <?php
                                        $user_name = trim(($activity['first_name'] ?? '') . ' ' . ($activity['last_name'] ?? ''));
                                        echo htmlspecialchars($user_name ?: 'Unknown', ENT_QUOTES, 'UTF-8');
                                        ?>
                                        <?php if ($activity['user_id']): ?>
                                            <div class="mono" style="font-size: 0.7rem;">#<?php echo (int)$activity['user_id']; ?></div>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <span class="expand-btn" onclick="toggleDetail(<?php echo (int)$activity['id']; ?>)">Details</span>
                                    </td>
                                </tr>
                                <tr class="detail-row" id="detail-<?php echo (int)$activity['id']; ?>">
                                    <td colspan="7">
                                        <div class="detail-grid">
                                            <div class="detail-item">
                                                <label>User Email</label>
                                                <div class="value"><?php echo htmlspecialchars($activity['user_email'] ?? '—', ENT_QUOTES, 'UTF-8'); ?></div>
                                            </div>
                                            <div class="detail-item">
                                                <label>IP Address</label>
                                                <div class="value mono"><?php echo htmlspecialchars($activity['ip_address'] ?? '—', ENT_QUOTES, 'UTF-8'); ?></div>
                                            </div>
                                            <div class="detail-item">
                                                <label>Timestamp (MST)</label>
                                                <div class="value mono"><?php echo date('M j, Y g:i:s A', strtotime($activity['created_at'])); ?></div>
                                            </div>
                                            <div class="detail-item">
                                                <label>User Agent</label>
                                                <div class="value" style="font-size: 0.75rem; color: #888;">
                                                    <?php echo htmlspecialchars($activity['user_agent'] ?? '—', ENT_QUOTES, 'UTF-8'); ?>
                                                </div>
                                            </div>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
        </div>

        <!-- Pagination -->
        <?php if ($total_pages > 1): ?>
            <div class="pagination">
                <?php
                // Build query string for pagination links
                $query_params = $_GET;
                unset($query_params['page']);
                $query_string = http_build_query($query_params);
                $query_prefix = $query_string ? '&' : '';
                ?>

                <?php if ($page > 1): ?>
                    <a href="?page=1<?php echo $query_prefix . $query_string; ?>">&laquo; First</a>
                    <a href="?page=<?php echo $page - 1; ?><?php echo $query_prefix . $query_string; ?>">&lsaquo; Prev</a>
                <?php else: ?>
                    <span class="disabled">&laquo; First</span>
                    <span class="disabled">&lsaquo; Prev</span>
                <?php endif; ?>

                <?php
                $start = max(1, $page - 2);
                $end = min($total_pages, $page + 2);
                for ($i = $start; $i <= $end; $i++):
                ?>
                    <?php if ($i === $page): ?>
                        <span class="current"><?php echo $i; ?></span>
                    <?php else: ?>
                        <a href="?page=<?php echo $i; ?><?php echo $query_prefix . $query_string; ?>"><?php echo $i; ?></a>
                    <?php endif; ?>
                <?php endfor; ?>

                <?php if ($page < $total_pages): ?>
                    <a href="?page=<?php echo $page + 1; ?><?php echo $query_prefix . $query_string; ?>">Next &rsaquo;</a>
                    <a href="?page=<?php echo $total_pages; ?><?php echo $query_prefix . $query_string; ?>">Last &raquo;</a>
                <?php else: ?>
                    <span class="disabled">Next &rsaquo;</span>
                    <span class="disabled">Last &raquo;</span>
                <?php endif; ?>
            </div>
            <p style="text-align: center; margin-top: 0.5rem; color: #888; font-size: 0.85rem;">
                Page <?php echo $page; ?> of <?php echo $total_pages; ?>
            </p>
        <?php endif; ?>
    </div>
</div>

<script>
function toggleDetail(id) {
    var row = document.getElementById('detail-' + id);
    if (row.style.display === 'table-row') {
        row.style.display = 'none';
    } else {
        row.style.display = 'table-row';
    }
}
</script>

<?php require_once __DIR__ . '/../../assets/footer.php'; ?>
