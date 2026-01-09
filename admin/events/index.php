<?php
require_once __DIR__ . '/../../config/db.php';
$pdo = getDbConnection();

// Fetch all events from database
$stmt = $pdo->query("
    SELECT id, title, event_date, event_time, location, is_featured
    FROM events
    ORDER BY event_date DESC, event_time DESC
");
$eventRows = $stmt->fetchAll();

// Separate into upcoming and past
$upcomingEvents = [];
$pastEvents = [];
$today = date('Y-m-d');

foreach ($eventRows as $event) {
    if ($event['event_date'] >= $today) {
        $upcomingEvents[] = $event;
    } else {
        $pastEvents[] = $event;
    }
}

$page_title = 'Events Management | Arizona Railway Museum';
require_once __DIR__ . '/../../assets/header.php';

// Check for delete success message
$deleteSuccess = null;
if (isset($_SESSION['delete_success'])) {
    $deleteSuccess = $_SESSION['delete_success'];
    unset($_SESSION['delete_success']);
}
?>

</div></div><!-- Close grid-container and page-content for full-width hero -->

<section class="arm-hero" style="margin-top: -4.5rem; padding: 2.5rem 0 2rem;">
    <div class="grid-container">
        <div class="grid-x grid-margin-x align-middle">
            <div class="small-12 cell">
                <h1 style="margin-bottom: 0.25rem;">Events Management</h1>
                <p class="lead" style="margin-bottom: 0;">View and manage museum events and activities.</p>
            </div>
        </div>
    </div>
</section>

<div class="page-content">
<div class="grid-container" style="padding-top: 2rem; margin-bottom: 2rem;">

<?php if ($deleteSuccess): ?>
    <div class="grid-x grid-margin-x">
        <div class="small-12 cell">
            <div class="callout success">
                <?php echo htmlspecialchars($deleteSuccess, ENT_QUOTES, 'UTF-8'); ?>
            </div>
        </div>
    </div>
<?php endif; ?>

<!-- Upcoming Events -->
<div class="grid-x grid-margin-x">
    <div class="small-12 cell">
        <div class="card arm-card">
            <div class="card-section">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem;">
                    <h3 style="margin: 0;">Upcoming Events</h3>
                    <a href="/admin/events/add.php" class="button primary small" style="border-radius: 8px; margin: 0;">Add Event</a>
                </div>

                <?php if (empty($upcomingEvents)): ?>
                    <div class="callout secondary">
                        <p>No upcoming events scheduled yet.</p>
                    </div>
                <?php else: ?>
                    <div class="arm-card-table-wrapper">
                        <table class="hover" style="border-radius: 8px; overflow: hidden; border: 1px solid #e0e0e0;">
                            <thead>
                            <tr>
                                <th width="50" style="padding: 0.75rem;">ID</th>
                                <th width="280" style="padding: 0.75rem;">Event Title</th>
                                <th width="120" style="padding: 0.75rem;">Date</th>
                                <th width="90" style="padding: 0.75rem;">Time</th>
                                <th width="180" style="padding: 0.75rem;">Location</th>
                                <th width="90" class="text-center" style="padding: 0.75rem;">Featured</th>
                                <th width="150" class="text-center" style="padding: 0.75rem;">Actions</th>
                            </tr>
                            </thead>
                            <tbody>
                            <?php foreach ($upcomingEvents as $row): ?>
                                <tr>
                                    <td style="padding: 0.75rem; color: #666;"><?php echo (int)$row['id']; ?></td>
                                    <td style="padding: 0.75rem;"><strong style="color: #1779ba;"><?php echo htmlspecialchars($row['title'] ?? '', ENT_QUOTES, 'UTF-8'); ?></strong></td>
                                    <td style="padding: 0.75rem;"><?php echo date('M j, Y', strtotime($row['event_date'])); ?></td>
                                    <td style="padding: 0.75rem;"><?php echo $row['event_time'] ? date('g:i A', strtotime($row['event_time'])) : '—'; ?></td>
                                    <td style="padding: 0.75rem;"><?php echo htmlspecialchars($row['location'] ?? '—', ENT_QUOTES, 'UTF-8'); ?></td>
                                    <td class="text-center" style="padding: 0.75rem;">
                                        <?php if ($row['is_featured']): ?>
                                            <span class="label primary">Featured</span>
                                        <?php else: ?>
                                            —
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-center" style="padding: 0.75rem;">
                                        <a href="/admin/events/edit/<?php echo (int)$row['id']; ?>" style="color: #1779ba; text-decoration: none; font-weight: 500;">✏️ Edit</a>
                                        <span style="color: #ccc; margin: 0 0.25rem;">|</span>
                                        <a href="/admin/events/handlers/delete.php?id=<?php echo (int)$row['id']; ?>"
                                           style="color: #cc4b37; text-decoration: none; font-weight: 500;"
                                           onclick="return confirm('Are you sure you want to delete this event? This action cannot be undone.');">🗑️ Delete</a>
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

<!-- Past Events -->
<div class="grid-x grid-margin-x" style="margin-top: 2rem;">
    <div class="small-12 cell">
        <div class="card arm-card">
            <div class="card-section">
                <h3>Past Events</h3>

                <?php if (empty($pastEvents)): ?>
                    <div class="callout secondary">
                        <p>No past events yet.</p>
                    </div>
                <?php else: ?>
                    <p style="margin-bottom: 1rem; color: #666;">
                        <strong><?php echo count($pastEvents); ?></strong> past events
                    </p>
                    <div class="arm-card-table-wrapper">
                        <table class="hover" style="border-radius: 8px; overflow: hidden; border: 1px solid #e0e0e0;">
                            <thead>
                            <tr>
                                <th width="50" style="padding: 0.75rem;">ID</th>
                                <th width="280" style="padding: 0.75rem;">Event Title</th>
                                <th width="120" style="padding: 0.75rem;">Date</th>
                                <th width="90" style="padding: 0.75rem;">Time</th>
                                <th width="180" style="padding: 0.75rem;">Location</th>
                                <th width="150" class="text-center" style="padding: 0.75rem;">Actions</th>
                            </tr>
                            </thead>
                            <tbody>
                            <?php foreach ($pastEvents as $row): ?>
                                <tr style="opacity: 0.7;">
                                    <td style="padding: 0.75rem; color: #666;"><?php echo (int)$row['id']; ?></td>
                                    <td style="padding: 0.75rem;"><strong><?php echo htmlspecialchars($row['title'] ?? '', ENT_QUOTES, 'UTF-8'); ?></strong></td>
                                    <td style="padding: 0.75rem;"><?php echo date('M j, Y', strtotime($row['event_date'])); ?></td>
                                    <td style="padding: 0.75rem;"><?php echo $row['event_time'] ? date('g:i A', strtotime($row['event_time'])) : '—'; ?></td>
                                    <td style="padding: 0.75rem;"><?php echo htmlspecialchars($row['location'] ?? '—', ENT_QUOTES, 'UTF-8'); ?></td>
                                    <td class="text-center" style="padding: 0.75rem;">
                                        <a href="/admin/events/edit/<?php echo (int)$row['id']; ?>" style="color: #1779ba; text-decoration: none; font-weight: 500;">✏️ Edit</a>
                                        <span style="color: #ccc; margin: 0 0.25rem;">|</span>
                                        <a href="/admin/events/handlers/delete.php?id=<?php echo (int)$row['id']; ?>"
                                           style="color: #cc4b37; text-decoration: none; font-weight: 500;"
                                           onclick="return confirm('Are you sure you want to delete this event? This action cannot be undone.');">🗑️ Delete</a>
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
