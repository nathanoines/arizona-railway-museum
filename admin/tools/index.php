<?php
/**
 * Admin Tools Dashboard
 *
 * Database management, system monitoring, and cleanup utilities.
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

$page_title = "Database Tools | Admin | Arizona Railway Museum";

$pdo = getDbConnection();

// ========================================
// GATHER ALL STATISTICS
// ========================================

$stats = [];

// Applications stats
try {
    $app_stats = $pdo->query("SELECT
        COUNT(*) as total,
        SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending,
        SUM(CASE WHEN status = 'approved' THEN 1 ELSE 0 END) as approved,
        SUM(CASE WHEN status = 'rejected' THEN 1 ELSE 0 END) as rejected,
        SUM(CASE WHEN status = 'rejected' AND created_at < DATE_SUB(NOW(), INTERVAL 90 DAY) THEN 1 ELSE 0 END) as old_rejected,
        SUM(CASE WHEN member_id IS NULL THEN 1 ELSE 0 END) as unlinked
    FROM membership_applications")->fetch();
    $stats['applications'] = $app_stats;
} catch (PDOException $e) {
    $stats['applications'] = null;
}

// Activity logs stats
try {
    $activity_stats = $pdo->query("SELECT
        COUNT(*) as total,
        SUM(CASE WHEN archived_at IS NULL THEN 1 ELSE 0 END) as active,
        SUM(CASE WHEN archived_at IS NOT NULL THEN 1 ELSE 0 END) as archived,
        SUM(CASE WHEN archived_at IS NULL AND created_at < DATE_SUB(NOW(), INTERVAL 90 DAY) THEN 1 ELSE 0 END) as older_than_90_days,
        SUM(CASE WHEN archived_at IS NULL AND created_at < DATE_SUB(NOW(), INTERVAL 180 DAY) THEN 1 ELSE 0 END) as older_than_180_days
    FROM activity_logs")->fetch();
    $stats['activity_logs'] = $activity_stats;
} catch (PDOException $e) {
    $stats['activity_logs'] = null;
}

// Members stats
try {
    $member_stats = $pdo->query("SELECT
        COUNT(*) as total,
        SUM(CASE WHEN membership_status = 'inactive' AND membership_activated_at IS NULL THEN 1 ELSE 0 END) as never_activated,
        SUM(CASE WHEN user_role = 'admin' THEN 1 ELSE 0 END) as admins,
        SUM(CASE WHEN is_super_admin = 1 THEN 1 ELSE 0 END) as super_admins,
        SUM(CASE WHEN is_key_holder = 1 THEN 1 ELSE 0 END) as key_holders,
        SUM(CASE WHEN membership_expires_at BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 30 DAY) THEN 1 ELSE 0 END) as expiring_30_days,
        SUM(CASE WHEN membership_expires_at BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 7 DAY) THEN 1 ELSE 0 END) as expiring_7_days
    FROM members")->fetch();
    $stats['members'] = $member_stats;
} catch (PDOException $e) {
    $stats['members'] = null;
}

// Equipment stats
try {
    $equip_stats = [
        'total' => 0,
        'on_display' => 0,
        'stored' => 0,
        'restoration' => 0,
        'types' => 0,
        'categories' => 0
    ];

    // Get total count
    $equip_stats['total'] = (int)$pdo->query("SELECT COUNT(*) FROM equipment")->fetchColumn();

    // Get distinct types and categories
    $equip_stats['types'] = (int)$pdo->query("SELECT COUNT(DISTINCT equipment_type) FROM equipment")->fetchColumn();
    $equip_stats['categories'] = (int)$pdo->query("SELECT COUNT(DISTINCT equipment_category) FROM equipment WHERE equipment_category IS NOT NULL")->fetchColumn();

    // Get status breakdown using PHP string matching (same approach as admin/index.php)
    $status_rows = $pdo->query("SELECT status, COUNT(*) as cnt FROM equipment GROUP BY status")->fetchAll();
    foreach ($status_rows as $row) {
        $status_lower = strtolower($row['status'] ?? '');
        if (strpos($status_lower, 'display') !== false) {
            $equip_stats['on_display'] += (int)$row['cnt'];
        } elseif (strpos($status_lower, 'stored') !== false) {
            $equip_stats['stored'] += (int)$row['cnt'];
        } elseif (strpos($status_lower, 'restoration') !== false) {
            $equip_stats['restoration'] += (int)$row['cnt'];
        }
    }

    $stats['equipment'] = $equip_stats;
} catch (PDOException $e) {
    error_log('Tools equipment stats error: ' . $e->getMessage());
    $stats['equipment'] = null;
}

// Equipment media stats
try {
    // Documents are stored in database
    $doc_count = $pdo->query("SELECT COUNT(*) FROM equipment_documents")->fetchColumn();

    // Audio is a column in equipment table (count items with audio_file set)
    $audio_count = $pdo->query("SELECT COUNT(*) FROM equipment WHERE audio_file IS NOT NULL AND audio_file != ''")->fetchColumn();

    // Photos are stored as files - count from filesystem
    $photo_count = 0;
    $images_dir = __DIR__ . '/../../images/equipment';
    if (is_dir($images_dir)) {
        $equipment_dirs = glob($images_dir . '/*', GLOB_ONLYDIR);
        foreach ($equipment_dirs as $dir) {
            // Count all .jpg files in each equipment directory
            $photo_count += count(glob($dir . '/*.jpg'));
        }
    }

    $stats['media'] = [
        'photos' => $photo_count,
        'documents' => (int)$doc_count,
        'audio_files' => (int)$audio_count
    ];
} catch (PDOException $e) {
    $stats['media'] = null;
}

// Get admin users for role management
try {
    $admins = $pdo->query("SELECT id, first_name, last_name, email, user_role, is_super_admin, is_key_holder
                           FROM members
                           WHERE user_role = 'admin'
                           ORDER BY last_name, first_name")->fetchAll();
} catch (PDOException $e) {
    $admins = [];
}

// Get expiring memberships
try {
    $expiring_members = $pdo->query("SELECT id, first_name, last_name, email, membership_status, membership_expires_at
                                     FROM members
                                     WHERE membership_expires_at BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 30 DAY)
                                     ORDER BY membership_expires_at ASC
                                     LIMIT 10")->fetchAll();
} catch (PDOException $e) {
    $expiring_members = [];
}

// Get unlinked applications
try {
    $unlinked_apps = $pdo->query("SELECT id, name, email, membership_level, status, created_at
                                  FROM membership_applications
                                  WHERE member_id IS NULL
                                  ORDER BY created_at DESC
                                  LIMIT 10")->fetchAll();
} catch (PDOException $e) {
    $unlinked_apps = [];
}

// Database table sizes
try {
    $db_name = DB_NAME;
    $table_sizes = $pdo->query("SELECT
        table_name as name,
        table_rows as rows,
        ROUND((data_length + index_length) / 1024, 2) as size_kb
    FROM information_schema.tables
    WHERE table_schema = '{$db_name}'
    ORDER BY (data_length + index_length) DESC
    LIMIT 10")->fetchAll();
} catch (PDOException $e) {
    $table_sizes = [];
}

require_once __DIR__ . '/../../assets/header.php';
?>
</div></div><!-- Close grid-container and page-content for full-width hero -->

<section class="arm-hero" style="margin-top: -4.5rem; padding: 2.5rem 0 2rem;">
    <div class="grid-container">
        <div class="grid-x grid-margin-x align-middle">
            <div class="small-12 cell">
                <h1 style="margin-bottom: 0.25rem;">Database Tools</h1>
                <p class="lead" style="margin-bottom: 0;">System maintenance, monitoring, and cleanup utilities.</p>
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

<style>
    .tools-section { margin-bottom: 2.5rem; }
    .tools-section h2 {
        font-size: 1.4rem;
        color: #333;
        margin-bottom: 1rem;
        padding-bottom: 0.5rem;
        border-bottom: 2px solid #e0e0e0;
    }
    .tools-section h2 span {
        margin-right: 0.5rem;
    }
    .stat-box {
        background: #f8f9fa;
        padding: 0.75rem;
        border-radius: 5px;
        margin-bottom: 1rem;
    }
    .stat-row {
        display: flex;
        justify-content: space-between;
        font-size: 0.85rem;
        color: #666;
        margin-bottom: 0.25rem;
    }
    .stat-row:last-child { margin-bottom: 0; }
    .stat-row.highlight { color: #cc4b37; }
    .stat-row.warning { color: #ffae00; }
    .stat-row strong { color: #333; }
    .mini-table {
        width: 100%;
        font-size: 0.85rem;
        border-collapse: collapse;
    }
    .mini-table th, .mini-table td {
        padding: 0.5rem;
        text-align: left;
        border-bottom: 1px solid #e0e0e0;
    }
    .mini-table th {
        background: #f8f9fa;
        font-weight: 600;
        color: #333;
    }
    .mini-table tr:hover { background: #f8f9fa; }
    .toggle-btn {
        padding: 0.2rem 0.5rem;
        font-size: 0.75rem;
        border-radius: 4px;
        cursor: pointer;
        border: none;
        transition: all 0.2s;
    }
    .toggle-btn.on { background: #4caf50; color: white; }
    .toggle-btn.off { background: #e0e0e0; color: #666; }
    .toggle-btn:hover { opacity: 0.8; }
</style>

<!-- ========================================
     SECTION 1: SYSTEM INFORMATION
     ======================================== -->
<div class="tools-section">
    <h2><span>&#128202;</span> System Information</h2>

    <div class="grid-x grid-margin-x">
        <!-- Server Info -->
        <div class="small-12 medium-6 large-3 cell" style="margin-bottom: 1rem;">
            <div class="card arm-card" style="height: 100%;">
                <div class="card-section">
                    <h4 style="font-size: 1rem; color: #1779ba; margin-bottom: 0.75rem;">Server Environment</h4>
                    <div class="stat-box">
                        <?php
                        // Get system uptime
                        $uptime_str = 'N/A';
                        if (file_exists('/proc/uptime')) {
                            $uptime_seconds = (int)file_get_contents('/proc/uptime');
                            $days = floor($uptime_seconds / 86400);
                            $hours = floor(($uptime_seconds % 86400) / 3600);
                            $minutes = floor(($uptime_seconds % 3600) / 60);
                            if ($days > 0) {
                                $uptime_str = $days . 'd ' . $hours . 'h ' . $minutes . 'm';
                            } elseif ($hours > 0) {
                                $uptime_str = $hours . 'h ' . $minutes . 'm';
                            } else {
                                $uptime_str = $minutes . ' min';
                            }
                        }
                        ?>
                        <div class="stat-row">
                            <span>Uptime:</span>
                            <strong><?php echo $uptime_str; ?></strong>
                        </div>
                        <div class="stat-row">
                            <span>PHP Version:</span>
                            <strong><?php echo phpversion(); ?></strong>
                        </div>
                        <div class="stat-row">
                            <span>Memory Limit:</span>
                            <strong><?php echo ini_get('memory_limit'); ?></strong>
                        </div>
                        <div class="stat-row">
                            <span>Max Upload:</span>
                            <strong><?php echo ini_get('upload_max_filesize'); ?></strong>
                        </div>
                        <div class="stat-row">
                            <span>Timezone:</span>
                            <strong><?php echo date_default_timezone_get(); ?></strong>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Database Overview -->
        <div class="small-12 medium-6 large-3 cell" style="margin-bottom: 1rem;">
            <div class="card arm-card" style="height: 100%;">
                <div class="card-section">
                    <h4 style="font-size: 1rem; color: #1779ba; margin-bottom: 0.75rem;">Database Overview</h4>
                    <?php if ($stats['members']): ?>
                    <div class="stat-box">
                        <div class="stat-row">
                            <span>Total Members:</span>
                            <strong><?php echo number_format((int)$stats['members']['total']); ?></strong>
                        </div>
                        <div class="stat-row">
                            <span>Total Applications:</span>
                            <strong><?php echo number_format((int)$stats['applications']['total']); ?></strong>
                        </div>
                        <div class="stat-row">
                            <span>Activity Logs:</span>
                            <strong><?php echo number_format((int)($stats['activity_logs']['total'] ?? 0)); ?></strong>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- User Roles -->
        <div class="small-12 medium-6 large-3 cell" style="margin-bottom: 1rem;">
            <div class="card arm-card" style="height: 100%;">
                <div class="card-section">
                    <h4 style="font-size: 1rem; color: #1779ba; margin-bottom: 0.75rem;">User Roles</h4>
                    <?php if ($stats['members']): ?>
                    <div class="stat-box">
                        <div class="stat-row">
                            <span>Admins:</span>
                            <strong><?php echo number_format((int)$stats['members']['admins']); ?></strong>
                        </div>
                        <div class="stat-row">
                            <span>Super Admins:</span>
                            <strong><?php echo number_format((int)$stats['members']['super_admins']); ?></strong>
                        </div>
                        <div class="stat-row">
                            <span>Key Holders:</span>
                            <strong><?php echo number_format((int)$stats['members']['key_holders']); ?></strong>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Membership Alerts -->
        <div class="small-12 medium-6 large-3 cell" style="margin-bottom: 1rem;">
            <div class="card arm-card" style="height: 100%;">
                <div class="card-section">
                    <h4 style="font-size: 1rem; color: #1779ba; margin-bottom: 0.75rem;">Membership Alerts</h4>
                    <?php if ($stats['members']): ?>
                    <div class="stat-box">
                        <div class="stat-row <?php echo (int)$stats['members']['expiring_7_days'] > 0 ? 'highlight' : ''; ?>">
                            <span>Expiring (7 days):</span>
                            <strong><?php echo number_format((int)$stats['members']['expiring_7_days']); ?></strong>
                        </div>
                        <div class="stat-row <?php echo (int)$stats['members']['expiring_30_days'] > 0 ? 'warning' : ''; ?>">
                            <span>Expiring (30 days):</span>
                            <strong><?php echo number_format((int)$stats['members']['expiring_30_days']); ?></strong>
                        </div>
                        <div class="stat-row">
                            <span>Never Activated:</span>
                            <strong><?php echo number_format((int)$stats['members']['never_activated']); ?></strong>
                        </div>
                        <div class="stat-row">
                            <span>Unlinked Apps:</span>
                            <strong><?php echo number_format((int)$stats['applications']['unlinked']); ?></strong>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Equipment Collection -->
        <div class="small-12 medium-6 large-3 cell" style="margin-bottom: 1rem;">
            <div class="card arm-card" style="height: 100%;">
                <div class="card-section">
                    <h4 style="font-size: 1rem; color: #1779ba; margin-bottom: 0.75rem;">Equipment Collection</h4>
                    <?php if ($stats['equipment']): ?>
                    <div class="stat-box">
                        <div class="stat-row">
                            <span>Total Items:</span>
                            <strong><?php echo number_format((int)$stats['equipment']['total']); ?></strong>
                        </div>
                        <div class="stat-row">
                            <span>On Display:</span>
                            <strong><?php echo number_format((int)$stats['equipment']['on_display']); ?></strong>
                        </div>
                        <div class="stat-row">
                            <span>In Storage:</span>
                            <strong><?php echo number_format((int)$stats['equipment']['stored']); ?></strong>
                        </div>
                        <div class="stat-row">
                            <span>Under Restoration:</span>
                            <strong><?php echo number_format((int)$stats['equipment']['restoration']); ?></strong>
                        </div>
                        <div class="stat-row" style="margin-top: 0.5rem; padding-top: 0.5rem; border-top: 1px solid #e0e0e0;">
                            <span>Equipment Types:</span>
                            <strong><?php echo number_format((int)$stats['equipment']['types']); ?></strong>
                        </div>
                        <div class="stat-row">
                            <span>Categories:</span>
                            <strong><?php echo number_format((int)$stats['equipment']['categories']); ?></strong>
                        </div>
                    </div>
                    <?php else: ?>
                    <div class="stat-box">
                        <p style="font-size: 0.85rem; color: #888; margin: 0;">Equipment data unavailable.</p>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Media Files -->
        <div class="small-12 medium-6 large-3 cell" style="margin-bottom: 1rem;">
            <div class="card arm-card" style="height: 100%;">
                <div class="card-section">
                    <h4 style="font-size: 1rem; color: #1779ba; margin-bottom: 0.75rem;">Media Files</h4>
                    <?php if ($stats['media']): ?>
                    <div class="stat-box">
                        <div class="stat-row">
                            <span>Photos:</span>
                            <strong><?php echo number_format((int)$stats['media']['photos']); ?></strong>
                        </div>
                        <div class="stat-row">
                            <span>Documents:</span>
                            <strong><?php echo number_format((int)$stats['media']['documents']); ?></strong>
                        </div>
                        <div class="stat-row">
                            <span>Audio Files:</span>
                            <strong><?php echo number_format((int)$stats['media']['audio_files']); ?></strong>
                        </div>
                    </div>
                    <?php else: ?>
                    <div class="stat-box">
                        <p style="font-size: 0.85rem; color: #888; margin: 0;">Media data unavailable.</p>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- ========================================
     SECTION 2: USER ROLE MANAGEMENT
     ======================================== -->
<div class="tools-section">
    <h2><span>&#128101;</span> User Role Management</h2>

    <div class="grid-x grid-margin-x">
        <div class="small-12 large-8 cell" style="margin-bottom: 1rem;">
            <div class="card arm-card">
                <div class="card-section">
                    <p style="font-size: 0.9rem; color: #666; margin-bottom: 1rem;">
                        Toggle admin privileges for users. Changes take effect on their next login.
                    </p>

                    <?php if (!empty($admins)): ?>
                    <div style="overflow-x: auto;">
                        <table class="mini-table">
                            <thead>
                                <tr>
                                    <th>Name</th>
                                    <th>Email</th>
                                    <th style="text-align: center;">Super Admin</th>
                                    <th style="text-align: center;">Key Holder</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($admins as $admin): ?>
                                <tr>
                                    <td>
                                        <strong><?php echo htmlspecialchars(trim($admin['first_name'] . ' ' . $admin['last_name']) ?: 'Unknown', ENT_QUOTES, 'UTF-8'); ?></strong>
                                        <?php if ($admin['id'] == $_SESSION['user_id']): ?>
                                            <span style="font-size: 0.7rem; color: #888;">(you)</span>
                                        <?php endif; ?>
                                    </td>
                                    <td style="font-size: 0.85rem; color: #666;">
                                        <?php echo htmlspecialchars($admin['email'], ENT_QUOTES, 'UTF-8'); ?>
                                    </td>
                                    <td style="text-align: center;">
                                        <?php if ($admin['id'] == $_SESSION['user_id']): ?>
                                            <span class="toggle-btn on" style="cursor: not-allowed;" title="Cannot modify your own super admin status">ON</span>
                                        <?php else: ?>
                                            <form method="post" action="handlers/toggle_role.php" style="display: inline;">
                                                <input type="hidden" name="user_id" value="<?php echo $admin['id']; ?>">
                                                <input type="hidden" name="role" value="super_admin">
                                                <input type="hidden" name="current" value="<?php echo $admin['is_super_admin']; ?>">
                                                <button type="submit" class="toggle-btn <?php echo $admin['is_super_admin'] ? 'on' : 'off'; ?>">
                                                    <?php echo $admin['is_super_admin'] ? 'ON' : 'OFF'; ?>
                                                </button>
                                            </form>
                                        <?php endif; ?>
                                    </td>
                                    <td style="text-align: center;">
                                        <form method="post" action="handlers/toggle_role.php" style="display: inline;">
                                            <input type="hidden" name="user_id" value="<?php echo $admin['id']; ?>">
                                            <input type="hidden" name="role" value="key_holder">
                                            <input type="hidden" name="current" value="<?php echo $admin['is_key_holder']; ?>">
                                            <button type="submit" class="toggle-btn <?php echo $admin['is_key_holder'] ? 'on' : 'off'; ?>">
                                                <?php echo $admin['is_key_holder'] ? 'ON' : 'OFF'; ?>
                                            </button>
                                        </form>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php else: ?>
                        <p style="color: #888;">No admin users found.</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Database Table Sizes -->
        <div class="small-12 large-4 cell" style="margin-bottom: 1rem;">
            <div class="card arm-card">
                <div class="card-section">
                    <h4 style="font-size: 1rem; color: #1779ba; margin-bottom: 0.75rem;">Table Sizes</h4>
                    <?php if (!empty($table_sizes)): ?>
                    <div style="overflow-x: auto;">
                        <table class="mini-table">
                            <thead>
                                <tr>
                                    <th>Table</th>
                                    <th style="text-align: right;">Rows</th>
                                    <th style="text-align: right;">Size</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($table_sizes as $table): ?>
                                <tr>
                                    <td style="font-size: 0.8rem;"><?php echo htmlspecialchars($table['name'], ENT_QUOTES, 'UTF-8'); ?></td>
                                    <td style="text-align: right; font-size: 0.8rem;"><?php echo number_format((int)$table['rows']); ?></td>
                                    <td style="text-align: right; font-size: 0.8rem;"><?php echo number_format((float)$table['size_kb'], 1); ?> KB</td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php else: ?>
                        <p style="color: #888; font-size: 0.9rem;">Unable to retrieve table sizes.</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- ========================================
     SECTION 3: DATA EXPORT
     ======================================== -->
<div class="tools-section">
    <h2><span>&#128229;</span> Data Export</h2>

    <div class="grid-x grid-margin-x">
        <!-- Export Members -->
        <div class="small-12 medium-6 large-4 cell" style="margin-bottom: 1rem;">
            <div class="card arm-card" style="height: 100%;">
                <div class="card-section">
                    <h4 style="font-size: 1rem; color: #1779ba; margin-bottom: 0.5rem;">Export Members</h4>
                    <p style="font-size: 0.85rem; color: #666; margin-bottom: 1rem;">
                        Download member data as CSV for mailing lists or reports.
                    </p>
                    <form method="post" action="handlers/export_members.php">
                        <label style="font-size: 0.9rem;">Include:
                            <select name="filter" style="margin-top: 0.25rem;">
                                <option value="all">All Members</option>
                                <option value="active">Active Members Only</option>
                                <option value="expiring">Expiring Soon (30 days)</option>
                                <option value="inactive">Inactive/Expired</option>
                            </select>
                        </label>
                        <button type="submit" class="button primary small" style="border-radius: 8px; margin-top: 0.5rem;">
                            Download CSV
                        </button>
                    </form>
                </div>
            </div>
        </div>

        <!-- Export Applications -->
        <div class="small-12 medium-6 large-4 cell" style="margin-bottom: 1rem;">
            <div class="card arm-card" style="height: 100%;">
                <div class="card-section">
                    <h4 style="font-size: 1rem; color: #1779ba; margin-bottom: 0.5rem;">Export Applications</h4>
                    <p style="font-size: 0.85rem; color: #666; margin-bottom: 1rem;">
                        Download application data as CSV for record keeping.
                    </p>
                    <form method="post" action="handlers/export_applications.php">
                        <label style="font-size: 0.9rem;">Include:
                            <select name="filter" style="margin-top: 0.25rem;">
                                <option value="all">All Applications</option>
                                <option value="pending">Pending Only</option>
                                <option value="approved">Approved Only</option>
                                <option value="rejected">Rejected Only</option>
                            </select>
                        </label>
                        <button type="submit" class="button primary small" style="border-radius: 8px; margin-top: 0.5rem;">
                            Download CSV
                        </button>
                    </form>
                </div>
            </div>
        </div>

        <!-- Export Activity Logs -->
        <div class="small-12 medium-6 large-4 cell" style="margin-bottom: 1rem;">
            <div class="card arm-card" style="height: 100%;">
                <div class="card-section">
                    <h4 style="font-size: 1rem; color: #1779ba; margin-bottom: 0.5rem;">Export Activity Logs</h4>
                    <p style="font-size: 0.85rem; color: #666; margin-bottom: 1rem;">
                        Download activity log data for auditing purposes.
                    </p>
                    <form method="post" action="handlers/export_activity_logs.php">
                        <label style="font-size: 0.9rem;">Time Period:
                            <select name="days" style="margin-top: 0.25rem;">
                                <option value="30">Last 30 Days</option>
                                <option value="90">Last 90 Days</option>
                                <option value="180">Last 180 Days</option>
                                <option value="all">All Time</option>
                            </select>
                        </label>
                        <button type="submit" class="button primary small" style="border-radius: 8px; margin-top: 0.5rem;" <?php echo $stats['activity_logs'] ? '' : 'disabled'; ?>>
                            Download CSV
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- ========================================
     SECTION 4: DATA CLEANUP
     ======================================== -->
<div class="tools-section">
    <h2><span>&#128465;</span> Data Cleanup</h2>

    <div class="callout warning" style="margin-bottom: 1.5rem;">
        <p style="margin: 0;"><strong>Warning:</strong> These operations permanently delete data and cannot be undone.</p>
    </div>

    <div class="grid-x grid-margin-x">
        <!-- Cleanup Applications -->
        <div class="small-12 medium-6 large-4 cell" style="margin-bottom: 1rem;">
            <div class="card arm-card" style="height: 100%;">
                <div class="card-section">
                    <h4 style="font-size: 1rem; color: #cc4b37; margin-bottom: 0.5rem;">Cleanup Applications</h4>
                    <p style="font-size: 0.85rem; color: #666; margin-bottom: 0.75rem;">
                        Remove old rejected membership applications.
                    </p>
                    <?php if ($stats['applications']): ?>
                    <div class="stat-box">
                        <div class="stat-row">
                            <span>Total Applications:</span>
                            <strong><?php echo number_format((int)$stats['applications']['total']); ?></strong>
                        </div>
                        <div class="stat-row">
                            <span>Rejected:</span>
                            <strong><?php echo number_format((int)$stats['applications']['rejected']); ?></strong>
                        </div>
                        <div class="stat-row highlight">
                            <span>Rejected (90+ days):</span>
                            <strong><?php echo number_format((int)$stats['applications']['old_rejected']); ?></strong>
                        </div>
                    </div>
                    <?php endif; ?>
                    <form method="post" action="handlers/cleanup_applications.php" onsubmit="return confirm('Delete old rejected applications? This cannot be undone.');">
                        <label style="font-size: 0.9rem;">Older than:
                            <select name="days" style="margin-top: 0.25rem;">
                                <option value="90">90 days</option>
                                <option value="180">180 days</option>
                                <option value="365">1 year</option>
                            </select>
                        </label>
                        <button type="submit" class="button alert small" style="border-radius: 8px; margin-top: 0.5rem;">
                            Cleanup Applications
                        </button>
                    </form>
                </div>
            </div>
        </div>

        <!-- Archive Activity Logs -->
        <div class="small-12 medium-6 large-4 cell" style="margin-bottom: 1rem;">
            <div class="card arm-card" style="height: 100%;">
                <div class="card-section">
                    <h4 style="font-size: 1rem; color: #e65100; margin-bottom: 0.5rem;">Archive Activity Logs</h4>
                    <p style="font-size: 0.85rem; color: #666; margin-bottom: 0.75rem;">
                        Archive old activity logs (soft delete).
                    </p>
                    <?php if ($stats['activity_logs']): ?>
                    <div class="stat-box">
                        <div class="stat-row">
                            <span>Active Entries:</span>
                            <strong><?php echo number_format((int)$stats['activity_logs']['active']); ?></strong>
                        </div>
                        <div class="stat-row" style="color: #888;">
                            <span>Already Archived:</span>
                            <strong><?php echo number_format((int)$stats['activity_logs']['archived']); ?></strong>
                        </div>
                        <div class="stat-row">
                            <span>Older than 90 days:</span>
                            <strong><?php echo number_format((int)$stats['activity_logs']['older_than_90_days']); ?></strong>
                        </div>
                        <div class="stat-row highlight">
                            <span>Older than 180 days:</span>
                            <strong><?php echo number_format((int)$stats['activity_logs']['older_than_180_days']); ?></strong>
                        </div>
                    </div>
                    <?php else: ?>
                    <div class="stat-box">
                        <p style="font-size: 0.85rem; color: #888; margin: 0;">Activity logs not available.</p>
                    </div>
                    <?php endif; ?>
                    <form method="post" action="handlers/bulk_archive_activity.php" onsubmit="return confirm('Archive old activity logs? They will be hidden from the activity log view.');">
                        <label style="font-size: 0.9rem;">Older than:
                            <select name="days" style="margin-top: 0.25rem;">
                                <option value="90">90 days</option>
                                <option value="180">180 days</option>
                                <option value="365">1 year</option>
                            </select>
                        </label>
                        <button type="submit" class="button warning small" style="border-radius: 8px; margin-top: 0.5rem;" <?php echo $stats['activity_logs'] ? '' : 'disabled'; ?>>
                            Archive Logs
                        </button>
                    </form>
                </div>
            </div>
        </div>

        <!-- Cleanup Unlinked Applications -->
        <div class="small-12 medium-6 large-4 cell" style="margin-bottom: 1rem;">
            <div class="card arm-card" style="height: 100%;">
                <div class="card-section">
                    <h4 style="font-size: 1rem; color: #cc4b37; margin-bottom: 0.5rem;">Orphaned Records</h4>
                    <p style="font-size: 0.85rem; color: #666; margin-bottom: 0.75rem;">
                        Clean up unlinked or orphaned data.
                    </p>
                    <div class="stat-box">
                        <div class="stat-row">
                            <span>Unlinked Applications:</span>
                            <strong><?php echo number_format((int)($stats['applications']['unlinked'] ?? 0)); ?></strong>
                        </div>
                        <div class="stat-row">
                            <span>Never Activated Users:</span>
                            <strong><?php echo number_format((int)($stats['members']['never_activated'] ?? 0)); ?></strong>
                        </div>
                    </div>
                    <a href="unlinked_applications.php" class="button secondary small" style="border-radius: 8px; margin-top: 0.5rem;">
                        View Unlinked Apps
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- ========================================
     SECTION 5: QUICK VIEWS
     ======================================== -->
<div class="tools-section">
    <h2><span>&#128203;</span> Quick Views</h2>

    <div class="grid-x grid-margin-x">
        <!-- Expiring Memberships -->
        <div class="small-12 large-6 cell" style="margin-bottom: 1rem;">
            <div class="card arm-card">
                <div class="card-section">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.75rem;">
                        <h4 style="font-size: 1rem; color: #1779ba; margin: 0;">Expiring Memberships (30 days)</h4>
                        <?php if ((int)($stats['members']['expiring_30_days'] ?? 0) > 0): ?>
                            <span class="label warning"><?php echo (int)$stats['members']['expiring_30_days']; ?> members</span>
                        <?php endif; ?>
                    </div>

                    <?php if (!empty($expiring_members)): ?>
                    <div style="overflow-x: auto;">
                        <table class="mini-table">
                            <thead>
                                <tr>
                                    <th>Name</th>
                                    <th>Email</th>
                                    <th>Expires</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($expiring_members as $member): ?>
                                <tr>
                                    <td>
                                        <a href="/admin/membership/edit/<?php echo $member['id']; ?>">
                                            <?php echo htmlspecialchars(trim($member['first_name'] . ' ' . $member['last_name']) ?: $member['email'], ENT_QUOTES, 'UTF-8'); ?>
                                        </a>
                                    </td>
                                    <td style="font-size: 0.8rem; color: #666;">
                                        <?php echo htmlspecialchars($member['email'], ENT_QUOTES, 'UTF-8'); ?>
                                    </td>
                                    <td style="font-size: 0.8rem;">
                                        <?php
                                        $days_left = (int)((strtotime($member['membership_expires_at']) - time()) / 86400);
                                        $color = $days_left <= 7 ? '#cc4b37' : '#666';
                                        ?>
                                        <span style="color: <?php echo $color; ?>;">
                                            <?php echo date('M j, Y', strtotime($member['membership_expires_at'])); ?>
                                            (<?php echo $days_left; ?>d)
                                        </span>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php else: ?>
                        <p style="color: #888; font-size: 0.9rem; margin: 0;">No memberships expiring in the next 30 days.</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Unlinked Applications -->
        <div class="small-12 large-6 cell" style="margin-bottom: 1rem;">
            <div class="card arm-card">
                <div class="card-section">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.75rem;">
                        <h4 style="font-size: 1rem; color: #1779ba; margin: 0;">Unlinked Applications</h4>
                        <?php if ((int)($stats['applications']['unlinked'] ?? 0) > 0): ?>
                            <span class="label secondary"><?php echo (int)$stats['applications']['unlinked']; ?> apps</span>
                        <?php endif; ?>
                    </div>

                    <?php if (!empty($unlinked_apps)): ?>
                    <div style="overflow-x: auto;">
                        <table class="mini-table">
                            <thead>
                                <tr>
                                    <th>Name</th>
                                    <th>Email</th>
                                    <th>Status</th>
                                    <th>Date</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($unlinked_apps as $app): ?>
                                <tr>
                                    <td>
                                        <a href="/admin/membership/view/<?php echo $app['id']; ?>">
                                            <?php echo htmlspecialchars($app['name'], ENT_QUOTES, 'UTF-8'); ?>
                                        </a>
                                    </td>
                                    <td style="font-size: 0.8rem; color: #666;">
                                        <?php echo htmlspecialchars($app['email'], ENT_QUOTES, 'UTF-8'); ?>
                                    </td>
                                    <td>
                                        <span class="label <?php
                                            echo $app['status'] === 'pending' ? 'warning' :
                                                ($app['status'] === 'approved' ? 'success' : 'alert');
                                        ?>" style="font-size: 0.7rem;">
                                            <?php echo strtoupper($app['status']); ?>
                                        </span>
                                    </td>
                                    <td style="font-size: 0.8rem; color: #666;">
                                        <?php echo date('M j', strtotime($app['created_at'])); ?>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php else: ?>
                        <p style="color: #888; font-size: 0.9rem; margin: 0;">All applications are linked to member accounts.</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="grid-x grid-margin-x" style="margin-top: 1rem;">
    <div class="small-12 cell">
        <a href="/admin/" class="button secondary" style="border-radius: 8px;">&larr; Back to Dashboard</a>
    </div>
</div>

<?php require_once __DIR__ . '/../../assets/footer.php'; ?>
