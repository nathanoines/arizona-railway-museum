<?php
$page_title = "Events & Activities | Arizona Railway Museum";
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../assets/header.php';

// Fetch upcoming and past events from database
$pdo = getDbConnection();

// Get upcoming events (future dates)
$stmtUpcoming = $pdo->prepare("
    SELECT id, title, event_date, event_time, description, location, is_featured, flyer_url
    FROM events
    WHERE event_date >= CURDATE()
    ORDER BY event_date ASC, event_time ASC
");
$stmtUpcoming->execute();
$upcomingEvents = $stmtUpcoming->fetchAll();

// Get past events (last 3 years)
$stmtPast = $pdo->prepare("
    SELECT id, title, event_date, event_time, description, location, flyer_url
    FROM events
    WHERE event_date < CURDATE() AND event_date >= DATE_SUB(CURDATE(), INTERVAL 3 YEAR)
    ORDER BY event_date DESC
");
$stmtPast->execute();
$pastEvents = $stmtPast->fetchAll();
?>
</div></div><!-- Close grid-container and page-content for full-width sections -->

<!-- Hero section with background image -->
<section class="arm-hero" style="margin-top: -4.5rem;">
    <div class="grid-container">
        <div class="grid-x grid-margin-x align-middle align-right">
            <div class="small-12 medium-8 cell text-right">
                <h1>Events & Activities</h1>
                <p class="lead">Join us for special events and activities at the museum</p>
            </div>
        </div>
    </div>
</section>

<!-- Upcoming Events section -->
<section style="background: #fff; padding: 2rem 0;">
    <div class="grid-container">

<?php if (!empty($upcomingEvents)): ?>
    <div class="grid-x grid-margin-x">
        <div class="small-12 cell">
            <h2>Upcoming Events</h2>
        </div>
    </div>

    <div class="grid-x grid-margin-x">
        <?php foreach ($upcomingEvents as $event): ?>
            <div class="small-12 <?php echo $event['is_featured'] ? 'cell' : 'medium-6 cell'; ?>">
                <div class="card arm-card<?php echo $event['is_featured'] ? ' featured-event' : ''; ?>">
                    <div class="card-section">
                        <?php if ($event['is_featured']): ?>
                            <span class="label primary" style="margin-bottom: 0.5rem;">Featured Event</span>
                        <?php endif; ?>
                        
                        <h3><?php echo htmlspecialchars($event['title'], ENT_QUOTES, 'UTF-8'); ?></h3>
                        
                        <p style="color: #1779ba; font-weight: 600; margin-bottom: 1rem;">
                            📅 <?php echo date('l, F j, Y', strtotime($event['event_date'])); ?>
                            <?php if ($event['event_time']): ?>
                                <br>🕐 <?php echo date('g:i A', strtotime($event['event_time'])); ?>
                            <?php endif; ?>
                        </p>
                        
                        <?php if ($event['location']): ?>
                            <p style="margin-bottom: 1rem;">
                                <strong>📍 Location:</strong> <?php echo htmlspecialchars($event['location'], ENT_QUOTES, 'UTF-8'); ?>
                            </p>
                        <?php endif; ?>
                        
                        <?php if ($event['description']): ?>
                            <p><?php echo nl2br(htmlspecialchars($event['description'], ENT_QUOTES, 'UTF-8')); ?></p>
                        <?php endif; ?>
                        
                        <?php if ($event['flyer_url']): ?>
                            <p style="margin-top: 1rem;">
                                <a href="<?php echo htmlspecialchars($event['flyer_url'], ENT_QUOTES, 'UTF-8'); ?>" target="_blank" class="button small secondary" style="border-radius: 8px;">
                                    📄 View Event Flyer
                                </a>
                            </p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
<?php else: ?>
    <div class="grid-x grid-margin-x">
        <div class="small-12 cell">
            <div class="callout secondary">
                <h3>No Upcoming Events</h3>
                <p>We don't have any events scheduled at this time. Check back later or follow us on social media for announcements!</p>
                <p>The museum is open for regular visiting hours on weekends during our season (September through May).</p>
                <p><a href="/information" class="button primary" style="border-radius: 8px;">View Hours & Admission</a></p>
            </div>
        </div>
    </div>
<?php endif; ?>

    </div>
</section>

<?php if (!empty($pastEvents)): ?>
<!-- Past Events section with background image -->
<section class="arm-links" style="
    background: linear-gradient(rgba(255,255,255,0.92), rgba(255,255,255,0.92)), url('/assets/backgrounds/main.jpg') center/cover no-repeat fixed;
">
    <div class="grid-container">
        <div class="grid-x grid-margin-x">
            <div class="small-12 cell">
                <h2 style="display: inline-block;">Past Events</h2>
                <span style="float: right;">
                    <div class="arm-nav-btn-container">
                        <a href="/events/archive" class="arm-nav-link primary">📜 View All Past Events</a>
                    </div>
                </span>
            </div>
        </div>

        <div class="grid-x grid-margin-x">
            <div class="small-12 cell">
                <div class="card arm-card">
                    <div class="card-section">
                        <div class="arm-card-table-wrapper">
                            <table class="hover" style="border-radius: 8px; overflow: hidden; border: 1px solid #e0e0e0;">
                                <thead>
                                    <tr>
                                        <th width="50%">Event</th>
                                        <th width="20%">Date</th>
                                        <th width="8%">Time</th>
                                        <th width="15%" class="text-center">Flyer</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($pastEvents as $event): ?>
                                        <tr>
                                            <td>
                                                <strong><?php echo htmlspecialchars($event['title'], ENT_QUOTES, 'UTF-8'); ?></strong>
                                                <?php if ($event['description']): ?>
                                                    <br><small class="text-muted"><?php echo htmlspecialchars(mb_substr($event['description'], 0, 100), ENT_QUOTES, 'UTF-8'); ?><?php echo strlen($event['description']) > 100 ? '...' : ''; ?></small>
                                                <?php endif; ?>
                                            </td>
                                            <td><?php echo date('M j, Y', strtotime($event['event_date'])); ?></td>
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
    </div>
</section>
<?php endif; ?>

<!-- Stay Connected CTA -->
<section class="arm-hero">
    <div class="grid-container">
        <div class="grid-x grid-margin-x align-center text-center">
            <div class="small-12 medium-10 large-8 cell">
                <h2>Stay Connected</h2>
                <p class="lead">Want to be notified about upcoming events and activities? Follow us on social media or become a member!</p>
                <p style="margin-top: 1.5rem;">
                    <a href="https://www.facebook.com/ArizonaRailwayMuseum" target="_blank" class="button large" style="border-radius: 8px; background: #fff; color: #1779ba;">
                        Follow on Facebook
                    </a>
                    <a href="/membership" class="button large secondary" style="border-radius: 8px;">
                        Become a Member
                    </a>
                </p>
            </div>
        </div>
    </div>
</section>

<div class="page-content">
<div class="grid-container" style="padding-top: 2.5rem; margin-bottom: -2.25rem;">

<style>
.featured-event {
    background: linear-gradient(135deg, #e3f2fd 0%, #fff 100%);
    border-left: 4px solid #1779ba;
}
</style>

<?php require_once __DIR__ . '/../assets/footer.php'; ?>
