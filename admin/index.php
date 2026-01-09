<?php
/**
 * Admin Dashboard
 *
 * Central hub for museum administration with quick stats,
 * management links, and recent activity.
 */

declare(strict_types=1);

session_start();
require_once __DIR__ . '/../config/db.php';

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

$page_title = "Admin Dashboard | Arizona Railway Museum";

$pdo = getDbConnection();

// Initialize defaults
$member_stats = ['total_members' => 0, 'active_members' => 0, 'inactive_members' => 0, 'expiring_soon' => 0];
$app_stats = ['total_applications' => 0, 'pending' => 0, 'approved' => 0, 'rejected' => 0];
$equip_stats = ['total_equipment' => 0, 'on_display' => 0, 'stored' => 0, 'under_restoration' => 0];
$event_stats = ['total_events' => 0, 'upcoming' => 0, 'past' => 0, 'featured' => 0];
$recent_apps = [];
$expiring_members = [];
$upcoming_events = [];
$recent_activity = [];
$recent_signups = [];

// ========================================
// QUICK STATS QUERIES (each in own try/catch)
// ========================================

// Member statistics
try {
    $member_stats_sql = "SELECT
        COUNT(*) as total_members,
        SUM(CASE WHEN membership_status != 'inactive' AND membership_expires_at > CURDATE() THEN 1 ELSE 0 END) as active_members,
        SUM(CASE WHEN membership_status = 'inactive' OR membership_expires_at IS NULL OR membership_expires_at <= CURDATE() THEN 1 ELSE 0 END) as inactive_members,
        SUM(CASE WHEN membership_expires_at BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 30 DAY) THEN 1 ELSE 0 END) as expiring_soon,
        SUM(CASE WHEN created_at >= DATE_SUB(CURDATE(), INTERVAL 30 DAY) THEN 1 ELSE 0 END) as new_signups,
        SUM(CASE WHEN membership_activated_at IS NULL THEN 1 ELSE 0 END) as never_activated
    FROM members";
    $member_stats = $pdo->query($member_stats_sql)->fetch();
} catch (PDOException $e) {
    error_log('Dashboard members error: ' . $e->getMessage());
}

// Application statistics
try {
    $app_stats_sql = "SELECT
        COUNT(*) as total_applications,
        SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending,
        SUM(CASE WHEN status = 'approved' THEN 1 ELSE 0 END) as approved,
        SUM(CASE WHEN status = 'rejected' THEN 1 ELSE 0 END) as rejected
    FROM membership_applications";
    $app_stats = $pdo->query($app_stats_sql)->fetch();
} catch (PDOException $e) {
    error_log('Dashboard applications error: ' . $e->getMessage());
}

// Equipment statistics
try {
    // First get total count
    $total_equip = $pdo->query("SELECT COUNT(*) as cnt FROM equipment")->fetch();
    $equip_stats['total_equipment'] = (int)$total_equip['cnt'];

    // Then get status breakdown
    $status_sql = "SELECT status, COUNT(*) as cnt FROM equipment GROUP BY status";
    $status_rows = $pdo->query($status_sql)->fetchAll();

    foreach ($status_rows as $row) {
        $status_lower = strtolower($row['status'] ?? '');
        if (strpos($status_lower, 'display') !== false) {
            $equip_stats['on_display'] = (int)$row['cnt'];
        } elseif (strpos($status_lower, 'stored') !== false) {
            $equip_stats['stored'] = (int)$row['cnt'];
        } elseif (strpos($status_lower, 'restoration') !== false) {
            $equip_stats['under_restoration'] = (int)$row['cnt'];
        }
    }
} catch (PDOException $e) {
    error_log('Dashboard equipment error: ' . $e->getMessage());
}

// Event statistics
try {
    $event_stats_sql = "SELECT
        COUNT(*) as total_events,
        SUM(CASE WHEN event_date >= CURDATE() THEN 1 ELSE 0 END) as upcoming,
        SUM(CASE WHEN event_date < CURDATE() THEN 1 ELSE 0 END) as past,
        SUM(CASE WHEN is_featured = 1 AND event_date >= CURDATE() THEN 1 ELSE 0 END) as featured
    FROM events";
    $event_stats = $pdo->query($event_stats_sql)->fetch();
} catch (PDOException $e) {
    error_log('Dashboard events error: ' . $e->getMessage());
}

// ========================================
// RECENT ACTIVITY
// ========================================

// Recent pending applications (last 5)
try {
    $recent_apps_sql = "SELECT id, name, email, membership_level, created_at, status
                        FROM membership_applications
                        WHERE status = 'pending'
                        ORDER BY created_at DESC
                        LIMIT 5";
    $recent_apps = $pdo->query($recent_apps_sql)->fetchAll();
} catch (PDOException $e) {
    error_log('Dashboard recent apps error: ' . $e->getMessage());
}

// Recent signups (last 5 registered members)
try {
    $signups_sql = "SELECT id, first_name, last_name, email, membership_status, membership_activated_at, created_at
                    FROM members
                    ORDER BY created_at DESC
                    LIMIT 5";
    $recent_signups = $pdo->query($signups_sql)->fetchAll();
} catch (PDOException $e) {
    error_log('Dashboard recent signups error: ' . $e->getMessage());
}

// Members expiring soon (next 30 days)
try {
    $expiring_sql = "SELECT id, first_name, last_name, email, membership_status, membership_expires_at
                     FROM members
                     WHERE membership_expires_at BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 30 DAY)
                     ORDER BY membership_expires_at ASC
                     LIMIT 5";
    $expiring_members = $pdo->query($expiring_sql)->fetchAll();
} catch (PDOException $e) {
    error_log('Dashboard expiring members error: ' . $e->getMessage());
}

// Upcoming events (next 5)
try {
    $upcoming_events_sql = "SELECT id, title, event_date, event_time, location, is_featured
                            FROM events
                            WHERE event_date >= CURDATE()
                            ORDER BY event_date ASC, event_time ASC
                            LIMIT 5";
    $upcoming_events = $pdo->query($upcoming_events_sql)->fetchAll();
} catch (PDOException $e) {
    error_log('Dashboard upcoming events error: ' . $e->getMessage());
}

// Recent activity logs (last 10) - this table may not exist yet
try {
    $activity_sql = "SELECT al.*, m.first_name, m.last_name
                     FROM activity_logs al
                     LEFT JOIN members m ON al.user_id = m.id
                     ORDER BY al.created_at DESC
                     LIMIT 10";
    $recent_activity = $pdo->query($activity_sql)->fetchAll();
} catch (PDOException $e) {
    // Activity logs table may not exist - that's okay
    error_log('Dashboard activity logs error: ' . $e->getMessage());
}

require_once __DIR__ . '/../assets/header.php';
?>
</div></div><!-- Close grid-container and page-content for full-width hero -->

<!-- Admin Dashboard Hero -->
<section class="arm-hero" style="margin-top: -4.5rem; padding: 2.5rem 0 2rem;">
    <div class="grid-container">
        <div class="grid-x grid-margin-x align-middle">
            <div class="small-12 cell">
                <h1 style="margin-bottom: 0.25rem;">Admin Dashboard</h1>
                <p class="lead" style="margin-bottom: 0;">
                    Welcome back! Here's an overview of the museum's status.
                </p>
            </div>
        </div>
    </div>
</section>

<div class="page-content">
<div class="grid-container" style="padding-top: 2rem; margin-bottom: 2rem;">

<style>
    .dashboard-stat-card {
        background: #ffffff;
        border-radius: 12px;
        box-shadow: 0 4px 14px rgba(0, 0, 0, 0.08);
        padding: 1.5rem;
        height: 100%;
        transition: transform 0.2s, box-shadow 0.2s;
    }
    .dashboard-stat-card:hover {
        transform: translateY(-2px);
        box-shadow: 0 6px 20px rgba(0, 0, 0, 0.12);
    }
    .stat-icon {
        width: 48px;
        height: 48px;
        border-radius: 10px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.5rem;
        margin-bottom: 1rem;
    }
    .stat-icon.blue { background: #e3f2fd; }
    .stat-icon.green { background: #e8f5e9; }
    .stat-icon.orange { background: #fff3e0; }
    .stat-icon.purple { background: #f3e5f5; }
    .stat-number {
        font-size: 2rem;
        font-weight: 700;
        color: #1a1a1a;
        line-height: 1;
        margin-bottom: 0.25rem;
    }
    .stat-label {
        font-size: 0.9rem;
        color: #666;
        margin-bottom: 0.75rem;
    }
    .stat-detail {
        font-size: 0.8rem;
        color: #888;
        display: flex;
        gap: 1rem;
        flex-wrap: wrap;
    }
    .stat-detail span {
        display: flex;
        align-items: center;
        gap: 0.25rem;
    }
    .stat-detail .dot {
        width: 8px;
        height: 8px;
        border-radius: 50%;
    }
    .dot.green { background: #4caf50; }
    .dot.gray { background: #9e9e9e; }
    .dot.orange { background: #ff9800; }
    .dot.red { background: #f44336; }
    .dot.blue { background: #2196f3; }
    .dot.purple { background: #9c27b0; }

    .admin-module-card {
        background: #ffffff;
        border-radius: 12px;
        box-shadow: 0 4px 14px rgba(0, 0, 0, 0.08);
        padding: 1.5rem;
        height: 100%;
        text-decoration: none;
        color: inherit;
        display: block;
        transition: transform 0.2s, box-shadow 0.2s;
        border: 2px solid transparent;
    }
    .admin-module-card:hover {
        transform: translateY(-4px);
        box-shadow: 0 8px 24px rgba(0, 0, 0, 0.12);
        border-color: #1779ba;
    }
    .admin-module-card h4 {
        margin: 0 0 0.5rem 0;
        color: #1779ba;
        display: flex;
        align-items: center;
        gap: 0.5rem;
    }
    .admin-module-card p {
        margin: 0;
        color: #666;
        font-size: 0.9rem;
    }
    .admin-module-card .badge-count {
        background: #ffae00;
        color: #000;
        padding: 0.15rem 0.5rem;
        border-radius: 12px;
        font-size: 0.75rem;
        font-weight: 600;
    }

    .activity-list {
        list-style: none;
        margin: 0;
        padding: 0;
    }
    .activity-list li {
        padding: 0.5rem 0;
        border-bottom: 1px solid #eee;
        display: flex;
        justify-content: space-between;
        align-items: center;
        gap: 0.75rem;
    }
    .activity-list li:last-child {
        border-bottom: none;
    }
    .activity-list .activity-main {
        flex: 1;
        min-width: 0;
    }
    .activity-list .activity-meta {
        font-size: 0.75rem;
        color: #888;
        white-space: nowrap;
    }
    .activity-list .activity-title {
        font-weight: 500;
        color: #333;
        font-size: 0.9rem;
    }
    .activity-list .activity-subtitle {
        font-size: 0.8rem;
        color: #666;
    }
    .activity-list .activity-inline {
        font-size: 0.85rem;
        color: #333;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }
    .activity-list .activity-inline .admin {
        color: #888;
        font-weight: normal;
    }

    .section-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 1rem;
    }
    .section-header h3 {
        margin: 0;
    }
    .section-header a {
        font-size: 0.85rem;
    }
</style>

<!-- Quick Stats Cards -->
<div class="grid-x grid-margin-x" style="margin-top: 1.5rem;">
    <div class="small-12 cell">
        <h2 style="margin-bottom: 1rem;">Quick Stats</h2>
    </div>
    <!-- Registered Users -->
    <div class="small-12 medium-6 large-3 cell" style="margin-bottom: 1rem;">
        <div class="dashboard-stat-card">
            <div class="stat-icon blue">
                <span>&#128101;</span>
            </div>
            <div class="stat-number"><?php echo number_format((int)$member_stats['total_members']); ?></div>
            <div class="stat-label">Registered Users</div>
            <div class="stat-detail">
                <span><span class="dot green"></span> <?php echo (int)$member_stats['active_members']; ?> active</span>
                <span><span class="dot gray"></span> <?php echo (int)$member_stats['inactive_members']; ?> inactive</span>
            </div>
            <div class="stat-detail" style="margin-top: 0.5rem;">
                <span><span class="dot blue"></span> <?php echo (int)($member_stats['new_signups'] ?? 0); ?> new (30 days)</span>
            </div>
            <?php if ((int)($member_stats['never_activated'] ?? 0) > 0): ?>
                <div class="stat-detail" style="margin-top: 0.5rem;">
                    <span><span class="dot orange"></span> <?php echo (int)$member_stats['never_activated']; ?> never activated</span>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Applications -->
    <div class="small-12 medium-6 large-3 cell" style="margin-bottom: 1rem;">
        <div class="dashboard-stat-card">
            <div class="stat-icon orange">
                <span>&#128203;</span>
            </div>
            <div class="stat-number"><?php echo number_format((int)$app_stats['pending']); ?></div>
            <div class="stat-label">Pending Applications</div>
            <div class="stat-detail">
                <span><span class="dot green"></span> <?php echo (int)$app_stats['approved']; ?> approved</span>
                <span><span class="dot red"></span> <?php echo (int)$app_stats['rejected']; ?> rejected</span>
            </div>
        </div>
    </div>

    <!-- Equipment -->
    <div class="small-12 medium-6 large-3 cell" style="margin-bottom: 1rem;">
        <div class="dashboard-stat-card">
            <div class="stat-icon green">
                <span>&#128646;</span>
            </div>
            <div class="stat-number"><?php echo number_format((int)$equip_stats['total_equipment']); ?></div>
            <div class="stat-label">Equipment Items</div>
            <div class="stat-detail">
                <span><span class="dot green"></span> <?php echo (int)$equip_stats['on_display']; ?> display</span>
                <span><span class="dot gray"></span> <?php echo (int)$equip_stats['stored']; ?> stored</span>
                <span><span class="dot blue"></span> <?php echo (int)$equip_stats['under_restoration']; ?> restoration</span>
            </div>
        </div>
    </div>

    <!-- Events -->
    <div class="small-12 medium-6 large-3 cell" style="margin-bottom: 1rem;">
        <div class="dashboard-stat-card">
            <div class="stat-icon purple">
                <span>&#128197;</span>
            </div>
            <div class="stat-number"><?php echo number_format((int)$event_stats['upcoming']); ?></div>
            <div class="stat-label">Upcoming Events</div>
            <div class="stat-detail">
                <span><span class="dot purple"></span> <?php echo (int)$event_stats['featured']; ?> featured</span>
                <span><span class="dot gray"></span> <?php echo (int)$event_stats['past']; ?> past</span>
            </div>
        </div>
    </div>
</div>

<!-- Admin Modules -->
<div class="grid-x grid-margin-x" style="margin-top: 1.5rem;">
    <div class="small-12 cell">
        <h2 style="margin-bottom: 1rem;">Management</h2>
    </div>

    <div class="small-12 medium-6 large-3 cell" style="margin-bottom: 1rem;">
        <a href="/admin/members/" class="admin-module-card">
            <h4>&#128101; Registered Members</h4>
            <p>View and manage all registered user accounts and their membership status.</p>
        </a>
    </div>

    <div class="small-12 medium-6 large-3 cell" style="margin-bottom: 1rem;">
        <a href="/admin/membership/" class="admin-module-card">
            <h4>&#128203; Membership Applications</h4>
            <p>Review and process membership applications, approve or reject submissions.</p>
        </a>
    </div>

    <div class="small-12 medium-6 large-3 cell" style="margin-bottom: 1rem;">
        <a href="/admin/equipment/" class="admin-module-card">
            <h4>&#128646; Equipment Roster</h4>
            <p>Manage the museum's rolling stock, update details, photos, and documentation.</p>
        </a>
    </div>

    <div class="small-12 medium-6 large-3 cell" style="margin-bottom: 1rem;">
        <a href="/admin/events/" class="admin-module-card">
            <h4>&#128197; Events</h4>
            <p>Create and manage museum events, set featured events, and update schedules.</p>
        </a>
    </div>

    <div class="small-12 medium-6 large-3 cell" style="margin-bottom: 1rem;">
        <a href="/admin/membership/add.php" class="admin-module-card">
            <h4>&#128221; Add Paper Application</h4>
            <p>Enter membership applications received by mail or in person.</p>
        </a>
    </div>

    <?php if ($isSuperAdmin): ?>
    <div class="small-12 medium-6 large-3 cell" style="margin-bottom: 1rem;">
        <a href="/admin/tools/" class="admin-module-card" style="border-color: #cc4b37;">
            <h4 style="color: #cc4b37;">&#128295; System Tools</h4>
            <p>Exports, cleanup, role management, and system monitoring.</p>
        </a>
    </div>
    <?php endif; ?>
</div>

<!-- Activity Panels -->
<div class="grid-x grid-margin-x" style="margin-top: 1.5rem;">
    <!-- Pending Applications -->
    <div class="small-12 large-6 cell" style="margin-bottom: 1rem;">
        <div class="card arm-card">
            <div class="card-section">
                <div class="section-header">
                    <h3>Pending Applications</h3>
                    <a href="/admin/membership/pending">View all &rarr;</a>
                </div>
                <?php if (empty($recent_apps)): ?>
                    <p style="color: #666; margin: 0;">No pending applications.</p>
                <?php else: ?>
                    <ul class="activity-list">
                        <?php foreach ($recent_apps as $app): ?>
                            <li>
                                <div class="activity-main">
                                    <div class="activity-title">
                                        <a href="/admin/membership/view/<?php echo $app['id']; ?>">
                                            <?php echo htmlspecialchars($app['name'], ENT_QUOTES, 'UTF-8'); ?>
                                        </a>
                                    </div>
                                    <div class="activity-subtitle">
                                        <?php
                                        $level = str_replace('_', ' ', $app['membership_level']);
                                        echo htmlspecialchars(ucwords($level), ENT_QUOTES, 'UTF-8');
                                        ?>
                                    </div>
                                </div>
                                <div class="activity-meta">
                                    <?php echo date('M j', strtotime($app['created_at'])); ?>
                                </div>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Recent Registrations -->
    <div class="small-12 large-6 cell" style="margin-bottom: 1rem;">
        <div class="card arm-card">
            <div class="card-section">
                <div class="section-header">
                    <h3>Recent Registrations</h3>
                    <a href="/admin/members/">View all &rarr;</a>
                </div>
                <?php if (empty($recent_signups)): ?>
                    <p style="color: #666; margin: 0;">No registered users yet.</p>
                <?php else: ?>
                    <ul class="activity-list">
                        <?php foreach ($recent_signups as $signup): ?>
                            <li>
                                <div class="activity-main">
                                    <div class="activity-title">
                                        <a href="/admin/membership/edit/<?php echo $signup['id']; ?>">
                                            <?php echo htmlspecialchars(trim($signup['first_name'] . ' ' . $signup['last_name']) ?: $signup['email'], ENT_QUOTES, 'UTF-8'); ?>
                                        </a>
                                        <?php if (empty($signup['membership_activated_at'])): ?>
                                            <span class="label warning" style="margin-left: 0.5rem; font-size: 0.65rem;">Not Activated</span>
                                        <?php endif; ?>
                                    </div>
                                    <div class="activity-subtitle">
                                        <?php echo htmlspecialchars($signup['email'], ENT_QUOTES, 'UTF-8'); ?>
                                    </div>
                                </div>
                                <div class="activity-meta">
                                    <?php echo date('M j', strtotime($signup['created_at'])); ?>
                                </div>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<div class="grid-x grid-margin-x">
    <!-- Expiring Memberships -->
    <div class="small-12 large-6 cell" style="margin-bottom: 1rem;">
        <div class="card arm-card">
            <div class="card-section">
                <div class="section-header">
                    <h3>Expiring Soon</h3>
                    <?php if ((int)$member_stats['expiring_soon'] > 0): ?>
                        <span class="label warning"><?php echo (int)$member_stats['expiring_soon']; ?> members</span>
                    <?php endif; ?>
                </div>
                <?php if (empty($expiring_members)): ?>
                    <p style="color: #666; margin: 0;">No memberships expiring in the next 30 days.</p>
                <?php else: ?>
                    <ul class="activity-list">
                        <?php foreach ($expiring_members as $member): ?>
                            <li>
                                <div class="activity-main">
                                    <div class="activity-title">
                                        <?php
                                        $name = trim($member['first_name'] . ' ' . $member['last_name']);
                                        echo htmlspecialchars($name ?: $member['email'], ENT_QUOTES, 'UTF-8');
                                        ?>
                                    </div>
                                    <div class="activity-subtitle">
                                        <?php echo htmlspecialchars(str_replace('_', ' ', $member['membership_status']), ENT_QUOTES, 'UTF-8'); ?>
                                    </div>
                                </div>
                                <div class="activity-meta">
                                    <?php
                                    $days_left = (int)((strtotime($member['membership_expires_at']) - time()) / 86400);
                                    if ($days_left <= 0) {
                                        echo '<span style="color: #cc4b37;">Expired</span>';
                                    } elseif ($days_left <= 7) {
                                        echo '<span style="color: #cc4b37;">' . $days_left . ' days</span>';
                                    } else {
                                        echo $days_left . ' days';
                                    }
                                    ?>
                                </div>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- More Activity -->
<div class="grid-x grid-margin-x">
    <!-- Upcoming Events -->
    <div class="small-12 large-6 cell" style="margin-bottom: 1rem;">
        <div class="card arm-card">
            <div class="card-section">
                <div class="section-header">
                    <h3>Upcoming Events</h3>
                    <a href="/admin/events/">View all &rarr;</a>
                </div>
                <?php if (empty($upcoming_events)): ?>
                    <p style="color: #666; margin: 0;">No upcoming events scheduled.</p>
                <?php else: ?>
                    <ul class="activity-list">
                        <?php foreach ($upcoming_events as $event): ?>
                            <li>
                                <div class="activity-main">
                                    <div class="activity-title">
                                        <a href="/admin/events/edit/<?php echo $event['id']; ?>">
                                            <?php echo htmlspecialchars($event['title'], ENT_QUOTES, 'UTF-8'); ?>
                                        </a>
                                        <?php if ($event['is_featured']): ?>
                                            <span class="label primary" style="margin-left: 0.5rem; font-size: 0.7rem;">Featured</span>
                                        <?php endif; ?>
                                    </div>
                                    <div class="activity-subtitle">
                                        <?php echo htmlspecialchars($event['location'] ?? 'No location', ENT_QUOTES, 'UTF-8'); ?>
                                    </div>
                                </div>
                                <div class="activity-meta">
                                    <?php echo date('M j', strtotime($event['event_date'])); ?>
                                    <?php if ($event['event_time']): ?>
                                        <br><?php echo date('g:i A', strtotime($event['event_time'])); ?>
                                    <?php endif; ?>
                                </div>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Recent Activity Log (Collapsible) -->
    <div class="small-12 large-6 cell" style="margin-bottom: 1rem;">
        <div class="card arm-card">
            <div class="card-section" style="padding-bottom: 0.5rem;">
                <div class="section-header" style="margin-bottom: 0; cursor: pointer;" onclick="var c=document.getElementById('activity-content'),a=document.getElementById('activity-arrow');if(c.style.display==='none'){c.style.display='block';a.innerHTML='&#9662;';}else{c.style.display='none';a.innerHTML='&#9656;';}">
                    <h3 style="display: flex; align-items: center; gap: 0.5rem;">
                        <span id="activity-arrow" style="font-size: 0.8rem; color: #888;">&#9656;</span>
                        Recent Activity
                    </h3>
                    <span style="font-size: 0.8rem; color: #888;"><?php echo count($recent_activity); ?> entries</span>
                </div>
                <div id="activity-content" style="display: none; margin-top: 0.75rem;">
                    <?php if (empty($recent_activity)): ?>
                        <p style="color: #666; margin: 0;">No recent activity logged.</p>
                    <?php else: ?>
                        <ul class="activity-list">
                            <?php foreach ($recent_activity as $activity): ?>
                                <li>
                                    <div class="activity-main">
                                        <?php
                                        $action_labels = [
                                            'application_approved' => 'Approved',
                                            'application_rejected' => 'Rejected',
                                            'application_linked' => 'Linked',
                                            'application_deleted' => 'Deleted',
                                            'application_unrejected' => 'Unrejected',
                                            'member_updated' => 'Updated',
                                            'equipment_added' => 'Added',
                                            'equipment_updated' => 'Updated',
                                            'event_added' => 'Added',
                                            'event_updated' => 'Updated',
                                            'event_deleted' => 'Deleted',
                                        ];
                                        $action = $action_labels[$activity['action_type']] ?? ucwords(str_replace('_', ' ', $activity['action_type']));
                                        $admin_name = trim(($activity['first_name'] ?? '') . ' ' . ($activity['last_name'] ?? ''));

                                        // Extract subject from description
                                        $subject = '';
                                        $desc = $activity['description'] ?? '';
                                        if (preg_match('/for ([^(]+)/', $desc, $matches)) {
                                            // Membership: "for John Smith (email)"
                                            $subject = trim($matches[1]);
                                        } elseif (preg_match('/equipment: ([^(]+)/', $desc, $matches)) {
                                            // Equipment: "Updated equipment: SP 1234"
                                            $subject = trim($matches[1]);
                                        } elseif (preg_match('/event: ([^(]+)/', $desc, $matches)) {
                                            // Events: "Added event: Holiday Train"
                                            $subject = trim($matches[1]);
                                        } elseif (preg_match('/application #(\d+)/', $desc, $matches)) {
                                            $subject = 'Application #' . $matches[1];
                                        }
                                        ?>
                                        <div class="activity-title"><?php echo htmlspecialchars($action, ENT_QUOTES, 'UTF-8'); ?><?php if ($subject): ?> &middot; <span style="font-weight: normal;"><?php echo htmlspecialchars($subject, ENT_QUOTES, 'UTF-8'); ?></span><?php endif; ?></div>
                                        <div class="activity-subtitle">by <?php echo htmlspecialchars($admin_name ?: 'Admin', ENT_QUOTES, 'UTF-8'); ?></div>
                                    </div>
                                    <div class="activity-meta">
                                        <?php
                                        $timestamp = strtotime($activity['created_at']);
                                        $diff = time() - $timestamp;
                                        if ($diff < 3600) {
                                            echo floor($diff / 60) . 'm';
                                        } elseif ($diff < 86400) {
                                            echo floor($diff / 3600) . 'h';
                                        } else {
                                            echo date('M j', $timestamp);
                                        }
                                        ?>
                                    </div>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../assets/footer.php'; ?>