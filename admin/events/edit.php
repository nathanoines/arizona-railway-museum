<?php
session_start();
require_once __DIR__ . '/../../config/db.php';
$pdo = getDbConnection();

$errors  = [];
$success = null;

// Define upload directory for flyers
$uploadDir = __DIR__ . '/../../documents/flyers/';

// Validate and get ID
if (!isset($_GET['id']) || !ctype_digit($_GET['id'])) {
    http_response_code(400);
    die('Invalid event ID.');
}
$eventId = (int)$_GET['id'];

// Load existing event
$stmt = $pdo->prepare("
    SELECT id, title, event_date, event_time, description, location, is_featured, flyer_url
    FROM events
    WHERE id = :id
    LIMIT 1
");
$stmt->execute([':id' => $eventId]);
$event = $stmt->fetch();

if (!$event) {
    http_response_code(404);
    die('Event record not found.');
}

// Initialize form values from DB
$title = $event['title'];
$event_date = $event['event_date'];
$event_time = $event['event_time'] ?? '';
$description = $event['description'];
$location = $event['location'] ?? '';
$is_featured = $event['is_featured'];
$flyer_url = $event['flyer_url'] ?? '';

// Handle POST (update)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = trim($_POST['title'] ?? '');
    $event_date = trim($_POST['event_date'] ?? '');
    $event_time = trim($_POST['event_time'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $location = trim($_POST['location'] ?? '');
    $is_featured = isset($_POST['is_featured']) ? 1 : 0;

    // Store the current flyer URL from DB before processing
    $current_flyer = $flyer_url;
    $new_external_url = trim($_POST['flyer_url'] ?? '');

    if ($title === '') {
        $errors[] = 'Event title is required.';
    }
    if ($event_date === '') {
        $errors[] = 'Event date is required.';
    } else {
        // Validate date format
        $dateCheck = date_parse($event_date);
        if (!checkdate($dateCheck['month'], $dateCheck['day'], $dateCheck['year'])) {
            $errors[] = 'Event date is not valid.';
        }
    }
    if ($description === '') {
        $errors[] = 'Event description is required.';
    }

    // Handle remove flyer checkbox first
    if (isset($_POST['remove_flyer']) && $_POST['remove_flyer'] === '1') {
        $flyer_url = '';
    }
    // Handle flyer upload if a file was provided (takes priority)
    elseif (isset($_FILES['flyer_upload']) && $_FILES['flyer_upload']['error'] === UPLOAD_ERR_OK) {
        $file = $_FILES['flyer_upload'];
        $allowedTypes = ['application/pdf', 'image/jpeg', 'image/png', 'image/gif'];
        $maxSize = 10 * 1024 * 1024; // 10MB

        // Validate file type
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mimeType = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);

        if (!in_array($mimeType, $allowedTypes)) {
            $errors[] = 'Invalid file type. Allowed: PDF, JPG, PNG, GIF.';
        } elseif ($file['size'] > $maxSize) {
            $errors[] = 'File is too large. Maximum size is 10MB.';
        } else {
            // Create upload directory if it doesn't exist
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0755, true);
            }

            // Generate unique filename
            $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
            $safeName = preg_replace('/[^a-zA-Z0-9_-]/', '_', pathinfo($file['name'], PATHINFO_FILENAME));
            $filename = date('Y-m-d') . '_' . $safeName . '_' . uniqid() . '.' . $extension;
            $destination = $uploadDir . $filename;

            if (move_uploaded_file($file['tmp_name'], $destination)) {
                // Set the flyer_url to the uploaded file path
                $flyer_url = '/documents/flyers/' . $filename;
            } else {
                $errors[] = 'Failed to upload file. Please try again.';
            }
        }
    }
    // If a new external URL was provided (and it's different from current), use it
    elseif ($new_external_url !== '' && $new_external_url !== $current_flyer) {
        $flyer_url = $new_external_url;
    }
    // Otherwise keep the current flyer (don't change it)
    else {
        $flyer_url = $current_flyer;
    }

    // Handle upload errors (but ignore "no file" error)
    if (isset($_FILES['flyer_upload']) && $_FILES['flyer_upload']['error'] !== UPLOAD_ERR_OK && $_FILES['flyer_upload']['error'] !== UPLOAD_ERR_NO_FILE) {
        $uploadErrors = [
            UPLOAD_ERR_INI_SIZE => 'File exceeds server upload limit.',
            UPLOAD_ERR_FORM_SIZE => 'File exceeds form upload limit.',
            UPLOAD_ERR_PARTIAL => 'File was only partially uploaded.',
            UPLOAD_ERR_NO_TMP_DIR => 'Server missing temporary folder.',
            UPLOAD_ERR_CANT_WRITE => 'Failed to write file to disk.',
            UPLOAD_ERR_EXTENSION => 'File upload blocked by extension.',
        ];
        $errors[] = $uploadErrors[$_FILES['flyer_upload']['error']] ?? 'Unknown upload error.';
    }

    if (empty($errors)) {
        $sql = "UPDATE events
                SET title = :title,
                    event_date = :event_date,
                    event_time = :event_time,
                    description = :description,
                    location = :location,
                    is_featured = :is_featured,
                    flyer_url = :flyer_url
                WHERE id = :id";

        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            ':title' => $title,
            ':event_date' => $event_date,
            ':event_time' => $event_time !== '' ? $event_time : null,
            ':description' => $description,
            ':location' => $location !== '' ? $location : null,
            ':is_featured' => $is_featured,
            ':flyer_url' => $flyer_url !== '' ? $flyer_url : null,
            ':id' => $eventId,
        ]);

        // Log activity
        try {
            $activity_sql = "INSERT INTO activity_logs (
                                user_id, action_type, entity_type, entity_id,
                                description, ip_address, user_agent
                             ) VALUES (
                                :user_id, :action_type, :entity_type, :entity_id,
                                :description, :ip_address, :user_agent
                             )";
            $activity_stmt = $pdo->prepare($activity_sql);
            $activity_stmt->execute([
                ':user_id' => $_SESSION['user_id'] ?? null,
                ':action_type' => 'event_updated',
                ':entity_type' => 'event',
                ':entity_id' => $eventId,
                ':description' => "Updated event: {$title} (ID #{$eventId})",
                ':ip_address' => $_SERVER['REMOTE_ADDR'] ?? null,
                ':user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? null,
            ]);
        } catch (PDOException $e) {
            error_log('Activity log error: ' . $e->getMessage());
        }

        $success = 'Event updated successfully.';
    }
}

$page_title = 'Edit Event | Arizona Railway Museum';
require_once __DIR__ . '/../../assets/header.php';

// Simple admin check
if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'admin') : ?>
    <div class="grid-x grid-margin-x">
        <div class="small-12 cell">
            <div class="callout alert">
                <h5>Access denied</h5>
                <p>You must be an administrator to access this page.</p>
            </div>
        </div>
    </div>
    <?php require_once __DIR__ . '/../../assets/footer.php'; exit; ?>
<?php endif; ?>

</div></div><!-- Close grid-container and page-content for full-width hero -->

<section class="arm-hero" style="margin-top: -4.5rem; padding: 2.5rem 0 2rem;">
    <div class="grid-container">
        <div class="grid-x grid-margin-x align-middle">
            <div class="small-12 cell">
                <h1 style="margin-bottom: 0.25rem;">Edit Event</h1>
                <p class="lead" style="margin-bottom: 0;"><?php echo htmlspecialchars($title, ENT_QUOTES, 'UTF-8'); ?></p>
            </div>
        </div>
    </div>
</section>

<div class="page-content">
<div class="grid-container" style="padding-top: 2rem; margin-bottom: 2rem;">

<div class="grid-x grid-margin-x">
    <div class="small-12 cell">
        <?php if (!empty($errors)): ?>
            <div class="callout alert">
                <h5>There were some problems:</h5>
                <ul>
                    <?php foreach ($errors as $error): ?>
                        <li><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php elseif ($success): ?>
            <div class="callout success">
                <?php echo htmlspecialchars($success, ENT_QUOTES, 'UTF-8'); ?>
                <p style="margin-top: 0.5rem;">
                    <a href="/admin/events/" class="button small primary" style="border-radius: 8px;">Back to Events Management</a>
                    <a href="/events/" class="button small secondary" style="border-radius: 8px;" target="_blank">View Public Events Page</a>
                </p>
            </div>
        <?php endif; ?>

        <div class="card arm-card">
            <div class="card-section">
                <form method="post" action="" enctype="multipart/form-data">
                <label>Event Title *
                    <input type="text" name="title" required
                        placeholder="e.g. Holiday Train Display, Volunteer Workday"
                        value="<?php echo htmlspecialchars($title, ENT_QUOTES, 'UTF-8'); ?>">
                    <p class="help-text">The name of the event or activity</p>
                </label>

                <div class="grid-x grid-margin-x">
                    <div class="small-12 medium-6 cell">
                        <label>Event Date *
                            <input type="date" name="event_date" required
                                value="<?php echo htmlspecialchars($event_date, ENT_QUOTES, 'UTF-8'); ?>">
                            <p class="help-text">The date when the event occurs</p>
                        </label>
                    </div>
                    <div class="small-12 medium-6 cell">
                        <label>Event Time
                            <input type="time" name="event_time"
                                value="<?php echo htmlspecialchars($event_time, ENT_QUOTES, 'UTF-8'); ?>">
                            <p class="help-text">Optional start time (leave blank if all-day)</p>
                        </label>
                    </div>
                </div>

                <label>Description *
                    <textarea name="description" rows="5" required
                        placeholder="Describe the event details, activities, and what visitors should expect..."><?php echo htmlspecialchars($description, ENT_QUOTES, 'UTF-8'); ?></textarea>
                    <p class="help-text">Full event description (plain text)</p>
                </label>

                <label>Location
                    <input type="text" name="location"
                        placeholder="e.g. Museum Grounds, Main Exhibit Hall"
                        value="<?php echo htmlspecialchars($location, ENT_QUOTES, 'UTF-8'); ?>">
                    <p class="help-text">Where the event takes place (optional)</p>
                </label>

                <?php
                // Determine if current flyer is uploaded or external
                $is_uploaded_flyer = !empty($flyer_url) && strpos($flyer_url, '/documents/flyers/') === 0;
                $external_url_value = $is_uploaded_flyer ? '' : $flyer_url;
                ?>
                <fieldset style="border: 1px solid #e0e0e0; border-radius: 8px; padding: 1rem; margin-bottom: 1rem;">
                    <legend style="font-weight: 600; padding: 0 0.5rem;">Event Flyer (optional)</legend>

                    <?php if (!empty($flyer_url)): ?>
                        <div style="background: #e8f5e9; padding: 0.75rem; border-radius: 4px; margin-bottom: 1rem;">
                            <strong>Current Flyer:</strong>
                            <a href="<?php echo htmlspecialchars($flyer_url, ENT_QUOTES, 'UTF-8'); ?>" target="_blank">
                                <?php echo htmlspecialchars(basename($flyer_url), ENT_QUOTES, 'UTF-8'); ?>
                            </a>
                            <?php if ($is_uploaded_flyer): ?>
                                <span style="color: #666; font-size: 0.85rem;">(uploaded file)</span>
                            <?php else: ?>
                                <span style="color: #666; font-size: 0.85rem;">(external link)</span>
                            <?php endif; ?>
                            <div style="margin-top: 0.5rem;">
                                <label style="color: #c62828;">
                                    <input type="checkbox" name="remove_flyer" value="1">
                                    Remove current flyer
                                </label>
                            </div>
                        </div>
                    <?php endif; ?>

                    <label>Upload <?php echo !empty($flyer_url) ? 'New ' : ''; ?>Flyer
                        <input type="file" name="flyer_upload" accept=".pdf,.jpg,.jpeg,.png,.gif">
                        <p class="help-text">Upload a PDF or image file (max 10MB)</p>
                    </label>

                    <div style="text-align: center; margin: 1rem 0; color: #666;">— OR —</div>

                    <label>Link to External Flyer
                        <input type="url" name="flyer_url"
                            placeholder="https://example.com/flyer.pdf"
                            value="<?php echo htmlspecialchars($external_url_value, ENT_QUOTES, 'UTF-8'); ?>">
                        <p class="help-text">Enter a URL to replace with an external link</p>
                    </label>

                    <p class="help-text" style="margin-top: 0.5rem; font-style: italic;">
                        <?php if (!empty($flyer_url)): ?>
                            Leave both fields empty to keep the current flyer. Use "Remove current flyer" to delete it.
                        <?php else: ?>
                            Upload a file or enter an external URL.
                        <?php endif; ?>
                    </p>
                </fieldset>

                <div style="margin-top: 1.5rem; padding: 1rem; background: #e3f2fd; border-radius: 4px;">
                    <label>
                        <input type="checkbox" name="is_featured" value="1" <?php echo $is_featured ? 'checked' : ''; ?>>
                        <strong>Feature this event</strong>
                    </label>
                    <p class="help-text" style="margin: 0.5rem 0 0 1.75rem;">
                        Featured events are displayed prominently at full width on the public events page
                    </p>
                </div>

                <button type="submit" class="button primary expanded" style="margin-top: 1.5rem; border-radius: 8px;">Save Changes</button>
                </form>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../../assets/footer.php'; ?>
