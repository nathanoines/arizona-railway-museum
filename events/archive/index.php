<?php
$page_title = "Past Events Archive | Arizona Railway Museum";
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../assets/header.php';

// Fetch all past events from database
$pdo = getDbConnection();

$stmt = $pdo->prepare("
    SELECT id, title, event_date, event_time, description, location, flyer_url
    FROM events
    WHERE event_date < CURDATE()
    ORDER BY event_date DESC
");
$stmt->execute();
$pastEvents = $stmt->fetchAll();
?>

<div class="grid-x grid-margin-x">
    <div class="small-12 cell">
        <h1>Past Events Archive</h1>
        <p class="lead">Browse our complete history of events and activities</p>
        <p><a href="/events">&larr; Back to Events</a></p>
    </div>
</div>

<?php if (!empty($pastEvents)): ?>
    <div class="grid-x grid-margin-x" style="margin-top: 1.5rem;">
        <div class="small-12 cell">
            <div class="card arm-card">
                <div class="card-section">
                    <p style="margin-bottom: 1rem; color: #666;">
                        <strong><?php echo count($pastEvents); ?></strong> past events
                    </p>
                    <div class="arm-card-table-wrapper">
                        <table class="hover" style="border-radius: 8px; overflow: hidden; border: 1px solid #e0e0e0;">
                            <thead>
                                <tr>
                                    <th width="50%">Event</th>
                                    <th width="20%">Date</th>
                                    <th width="15%">Time</th>
                                    <th width="15%" class="text-center">Flyer</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php 
                                $currentYear = null;
                                foreach ($pastEvents as $event): 
                                    $eventYear = date('Y', strtotime($event['event_date']));
                                    
                                    // Add year separator
                                    if ($currentYear !== $eventYear):
                                        $currentYear = $eventYear;
                                ?>
                                    <tr style="background: #f8f9fa;">
                                        <td colspan="4" style="font-weight: 600; font-size: 1.1rem; padding: 1rem;">
                                            <?php echo $eventYear; ?>
                                        </td>
                                    </tr>
                                <?php endif; ?>
                                
                                <tr>
                                    <td>
                                        <strong><?php echo htmlspecialchars($event['title'], ENT_QUOTES, 'UTF-8'); ?></strong>
                                        <?php if ($event['description']): ?>
                                            <br><small class="text-muted"><?php echo htmlspecialchars(mb_substr($event['description'], 0, 150), ENT_QUOTES, 'UTF-8'); ?><?php echo strlen($event['description']) > 150 ? '...' : ''; ?></small>
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo date('F j, Y', strtotime($event['event_date'])); ?></td>
                                    <td><?php echo $event['event_time'] ? date('g:i A', strtotime($event['event_time'])) : '—'; ?></td>
                                    <td class="text-center">
                                        <?php if ($event['flyer_url']): ?>
                                            <a href="<?php echo htmlspecialchars($event['flyer_url'], ENT_QUOTES, 'UTF-8'); ?>" target="_blank" style="color: #1779ba; text-decoration: none; font-weight: 500;">📄 View</a>
                                        <?php else: ?>
                                            —
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
<?php else: ?>
    <div class="grid-x grid-margin-x" style="margin-top: 1.5rem;">
        <div class="small-12 cell">
            <div class="callout secondary">
                <h3>No Past Events</h3>
                <p>We don't have any past events in our archive yet.</p>
                <p><a href="/events" class="button primary" style="border-radius: 8px;">View Upcoming Events</a></p>
            </div>
        </div>
    </div>
<?php endif; ?>

<div class="grid-x grid-margin-x" style="margin-top: 2rem;">
    <div class="small-12 cell">
        <div class="card arm-card" style="background: #e3f2fd;">
            <div class="card-section text-center">
                <h3>Looking for Current Events?</h3>
                <p>Check out our upcoming events and activities schedule.</p>
                <p style="margin-top: 1rem;">
                    <a href="/events" class="button primary">View Upcoming Events</a>
                </p>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../../assets/footer.php'; ?>
