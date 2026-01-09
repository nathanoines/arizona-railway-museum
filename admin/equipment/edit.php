<?php
session_start();
require_once __DIR__ . '/../../config/db.php';
$pdo = getDbConnection();

$isAdmin = ($_SESSION['user_role'] ?? '') === 'admin';
$isKeyHolder = !empty($_SESSION['is_key_holder']);
$canViewPins = $isAdmin || $isKeyHolder;

$errors  = [];
$success = null;

// Validate and get ID
if (!isset($_GET['id']) || !ctype_digit($_GET['id'])) {
    http_response_code(400);
    die('Invalid equipment ID.');
}
$equipmentId = (int)$_GET['id'];

// Load existing row
$stmt = $pdo->prepare("
    SELECT id, reporting_mark, road_number, equipment_type, equipment_category, builder, built_year, status, pin, notes, audio_file
    FROM equipment
    WHERE id = :id
    LIMIT 1
");
$stmt->execute([':id' => $equipmentId]);
$item = $stmt->fetch();

if (!$item) {
    http_response_code(404);
    die('Equipment record not found.');
}

// Initialize form values from DB
$reporting_mark = $item['reporting_mark'];
$road_number    = $item['road_number'];
$equipment_type = $item['equipment_type'];
$equipment_category = $item['equipment_category'] ?? '';
$builder        = $item['builder'] ?? '';
$built_year     = $item['built_year'];
$status         = $item['status'];
$pin            = $item['pin'] ?? '';
$notes          = $item['notes'] ?? '';
$audio_file     = $item['audio_file'] ?? '';

// Handle POST (update)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $reporting_mark = trim($_POST['reporting_mark'] ?? '');
    $road_number    = trim($_POST['road_number'] ?? '');
    $equipment_type = trim($_POST['equipment_type'] ?? '');
    $equipment_category = trim($_POST['equipment_category'] ?? '');
    $builder        = trim($_POST['builder'] ?? '');
    $built_year     = trim($_POST['built_year'] ?? '');
    $status         = trim($_POST['status'] ?? '');
    $pin            = trim($_POST['pin'] ?? '');
    $notes          = trim($_POST['notes'] ?? '');
    $audio_file     = trim($_POST['audio_file'] ?? '');

    if ($reporting_mark === '') {
        $errors[] = 'Reporting mark is required.';
    }
    if ($road_number === '') {
        $errors[] = 'Road number is required.';
    }
    if ($equipment_type === '') {
        $errors[] = 'Equipment type is required.';
    }

    if ($built_year !== '' && !ctype_digit($built_year)) {
        $errors[] = 'Built year must be a number (or left blank).';
    }

    if (empty($errors)) {
        $sql = "UPDATE equipment
                SET reporting_mark = :reporting_mark,
                    road_number    = :road_number,
                    equipment_type = :equipment_type,
                    equipment_category = :equipment_category,
                    builder        = :builder,
                    built_year     = :built_year,
                    status         = :status,
                    pin            = :pin,
                    notes          = :notes,
                    audio_file     = :audio_file
                WHERE id = :id";

        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            ':reporting_mark' => $reporting_mark,
            ':road_number'    => $road_number,
            ':equipment_type' => $equipment_type,
            ':equipment_category' => $equipment_category !== '' ? $equipment_category : null,
            ':builder'        => $builder !== '' ? $builder : null,
            ':built_year'     => $built_year !== '' ? (int)$built_year : null,
            ':status'         => $status !== '' ? $status : null,
            ':pin'            => $pin !== '' ? $pin : null,
            ':notes'          => $notes !== '' ? $notes : null,
            ':audio_file'     => $audio_file !== '' ? $audio_file : null,
            ':id'             => $equipmentId,
        ]);

        // Log activity
        try {
            $equipment_name = $reporting_mark . ' ' . $road_number;
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
                ':action_type' => 'equipment_updated',
                ':entity_type' => 'equipment',
                ':entity_id' => $equipmentId,
                ':description' => "Updated equipment: {$equipment_name} (ID #{$equipmentId})",
                ':ip_address' => $_SERVER['REMOTE_ADDR'] ?? null,
                ':user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? null,
            ]);
        } catch (PDOException $e) {
            // Activity logging failure shouldn't break the main operation
            error_log('Activity log error: ' . $e->getMessage());
        }

        $success = 'Equipment entry updated successfully.';
    }
}

$page_title = 'Edit Equipment | Arizona Railway Museum';
require_once __DIR__ . '/../../assets/header.php';

// Check for upload messages
$uploadSuccess = null;
$uploadErrors = [];
$uploadInfo = null;
if (isset($_SESSION['upload_success'])) {
    $uploadSuccess = $_SESSION['upload_success'];
    unset($_SESSION['upload_success']);
}
if (isset($_SESSION['upload_errors'])) {
    $uploadErrors = $_SESSION['upload_errors'];
    unset($_SESSION['upload_errors']);
}
if (isset($_SESSION['upload_success_count'])) {
    unset($_SESSION['upload_success_count']);
}
if (isset($_SESSION['upload_info'])) {
    $uploadInfo = $_SESSION['upload_info'];
    unset($_SESSION['upload_info']);
}

// Check for caption messages
$captionSuccess = null;
$captionError = null;
if (isset($_SESSION['caption_success'])) {
    $captionSuccess = $_SESSION['caption_success'];
    unset($_SESSION['caption_success']);
}
if (isset($_SESSION['caption_error'])) {
    $captionError = $_SESSION['caption_error'];
    unset($_SESSION['caption_error']);
}

// Check for audio messages
$audioSuccess = null;
$audioError = null;
if (isset($_SESSION['audio_success'])) {
    $audioSuccess = $_SESSION['audio_success'];
    unset($_SESSION['audio_success']);
}
if (isset($_SESSION['audio_error'])) {
    $audioError = $_SESSION['audio_error'];
    unset($_SESSION['audio_error']);
}

// Check for document messages
$documentSuccess = null;
$documentError = null;
$documentErrors = [];
$documentPartialSuccess = null;
$documentInfo = null;
if (isset($_SESSION['document_success'])) {
    $documentSuccess = $_SESSION['document_success'];
    unset($_SESSION['document_success']);
}
if (isset($_SESSION['document_error'])) {
    $documentError = $_SESSION['document_error'];
    unset($_SESSION['document_error']);
}
if (isset($_SESSION['document_errors'])) {
    $documentErrors = $_SESSION['document_errors'];
    unset($_SESSION['document_errors']);
}
if (isset($_SESSION['document_partial_success'])) {
    $documentPartialSuccess = $_SESSION['document_partial_success'];
    unset($_SESSION['document_partial_success']);
}
if (isset($_SESSION['document_info'])) {
    $documentInfo = $_SESSION['document_info'];
    unset($_SESSION['document_info']);
}

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
                <p style="margin-bottom: 0.25rem; opacity: 0.8; font-size: 0.9rem; text-transform: uppercase; letter-spacing: 0.05em;">Edit Equipment</p>
                <h1 style="margin-bottom: 0.25rem;"><?php echo htmlspecialchars($reporting_mark, ENT_QUOTES, 'UTF-8'); ?></h1>
                <p class="lead" style="margin-bottom: 0;">
                    <?php echo htmlspecialchars($road_number, ENT_QUOTES, 'UTF-8'); ?>
                </p>
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
            </div>
        <?php endif; ?>

        <?php if ($uploadSuccess): ?>
            <div class="callout success">
                <?php echo htmlspecialchars($uploadSuccess, ENT_QUOTES, 'UTF-8'); ?>
            </div>
        <?php endif; ?>

        <?php if (!empty($uploadErrors)): ?>
            <div class="callout alert">
                <h5>Photo upload issues:</h5>
                <ul>
                    <?php foreach ($uploadErrors as $error): ?>
                        <li><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <?php if ($uploadInfo): ?>
            <div class="callout warning">
                <?php echo htmlspecialchars($uploadInfo, ENT_QUOTES, 'UTF-8'); ?>
            </div>
        <?php endif; ?>

        <?php if ($captionSuccess): ?>
            <div class="callout success">
                <?php echo htmlspecialchars($captionSuccess, ENT_QUOTES, 'UTF-8'); ?>
            </div>
        <?php endif; ?>

        <?php if ($captionError): ?>
            <div class="callout alert">
                <?php echo htmlspecialchars($captionError, ENT_QUOTES, 'UTF-8'); ?>
            </div>
        <?php endif; ?>

        <?php if ($audioSuccess): ?>
            <div class="callout success">
                <?php echo htmlspecialchars($audioSuccess, ENT_QUOTES, 'UTF-8'); ?>
            </div>
        <?php endif; ?>

        <?php if ($audioError): ?>
            <div class="callout alert">
                <?php echo htmlspecialchars($audioError, ENT_QUOTES, 'UTF-8'); ?>
            </div>
        <?php endif; ?>

        <?php if ($documentSuccess): ?>
            <div class="callout success">
                <?php echo htmlspecialchars($documentSuccess, ENT_QUOTES, 'UTF-8'); ?>
            </div>
        <?php endif; ?>

        <?php if ($documentError): ?>
            <div class="callout alert">
                <?php echo htmlspecialchars($documentError, ENT_QUOTES, 'UTF-8'); ?>
            </div>
        <?php endif; ?>

        <?php if (!empty($documentErrors)): ?>
            <div class="callout alert">
                <h5>Document upload issues:</h5>
                <ul>
                    <?php foreach ($documentErrors as $error): ?>
                        <li><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <?php if ($documentPartialSuccess): ?>
            <div class="callout warning">
                <?php echo htmlspecialchars($documentPartialSuccess, ENT_QUOTES, 'UTF-8'); ?>
            </div>
        <?php endif; ?>

        <?php if ($documentInfo): ?>
            <div class="callout warning">
                <?php echo htmlspecialchars($documentInfo, ENT_QUOTES, 'UTF-8'); ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<div class="grid-x grid-margin-x">
    <div class="small-12 medium-8 cell">
        <!-- Equipment Information Card -->
        <div class="card arm-card">
            <div class="card-section">
                <h3 style="margin-top: 0;">Equipment Information</h3>

                <form method="post" action="">
                    <fieldset>
                        <legend>Identification</legend>
                        <div class="grid-x grid-margin-x">
                            <div class="small-12 medium-6 cell">
                                <label>Company/Railroad Name *
                                    <input type="text" name="reporting_mark" required
                                           placeholder="e.g. Southern Pacific, Santa Fe"
                                           value="<?php echo htmlspecialchars($item['reporting_mark'], ENT_QUOTES, 'UTF-8'); ?>">
                                    <p class="help-text">Full company or railroad name</p>
                                </label>
                            </div>
                            <div class="small-12 medium-6 cell">
                                <label>Number/Identifier *
                                    <input type="text" name="road_number" required
                                           placeholder="e.g. SP 1234, ATSF-405, Plaza Taos"
                                           value="<?php echo htmlspecialchars($item['road_number'], ENT_QUOTES, 'UTF-8'); ?>">
                                    <p class="help-text">Road number, car name, or reporting mark + number</p>
                                </label>
                            </div>
                        </div>
                    </fieldset>

                    <fieldset style="margin-top: 1.5rem;">
                        <legend>Classification</legend>
                        <div class="grid-x grid-margin-x">
                            <div class="small-12 medium-6 cell">
                                <label>Equipment Type *
                                    <input type="text" name="equipment_type" required
                                        placeholder="e.g. Passenger Coach, Caboose"
                                        value="<?php echo htmlspecialchars($equipment_type, ENT_QUOTES, 'UTF-8'); ?>">
                                    <p class="help-text">Specific type of rolling stock</p>
                                </label>
                            </div>
                            <div class="small-12 medium-6 cell">
                                <label>Category
                                    <select name="equipment_category">
                                        <option value="">-- Select Category --</option>
                                        <option value="Locomotives" <?php echo $equipment_category === 'Locomotives' ? 'selected' : ''; ?>>Locomotives</option>
                                        <option value="Passenger Cars" <?php echo $equipment_category === 'Passenger Cars' ? 'selected' : ''; ?>>Passenger Cars</option>
                                        <option value="Freight Cars" <?php echo $equipment_category === 'Freight Cars' ? 'selected' : ''; ?>>Freight Cars</option>
                                        <option value="Mail/Baggage/Express" <?php echo $equipment_category === 'Mail/Baggage/Express' ? 'selected' : ''; ?>>Mail/Baggage/Express</option>
                                        <option value="MOW" <?php echo $equipment_category === 'MOW' ? 'selected' : ''; ?>>MOW</option>
                                        <option value="Interurban" <?php echo $equipment_category === 'Interurban' ? 'selected' : ''; ?>>Interurban</option>
                                    </select>
                                    <p class="help-text">High-level category for grouping</p>
                                </label>
                            </div>
                        </div>
                    </fieldset>

                    <fieldset style="margin-top: 1.5rem;">
                        <legend>Details</legend>
                        <div class="grid-x grid-margin-x">
                            <div class="small-12 medium-4 cell">
                                <label>Builder
                                    <input type="text" name="builder"
                                           placeholder="e.g. Pullman, Baldwin"
                                           value="<?php echo htmlspecialchars($builder, ENT_QUOTES, 'UTF-8'); ?>">
                                    <p class="help-text">Manufacturer of the equipment</p>
                                </label>
                            </div>
                            <div class="small-12 medium-4 cell">
                                <label>Built Year
                                    <input type="text" name="built_year"
                                           placeholder="e.g. 1953"
                                           value="<?php echo htmlspecialchars((string)$built_year, ENT_QUOTES, 'UTF-8'); ?>">
                                </label>
                            </div>
                            <div class="small-12 medium-4 cell">
                                <label>Status
                                    <input type="text" name="status"
                                           placeholder="On display, Stored, Under restoration"
                                           value="<?php echo htmlspecialchars($status ?? '', ENT_QUOTES, 'UTF-8'); ?>">
                                </label>
                            </div>
                        </div>
                        <?php if ($canViewPins): ?>
                        <div class="grid-x grid-margin-x" style="margin-top: 1rem;">
                            <div class="small-12 medium-4 cell">
                                <label>PIN
                                    <input type="text" name="pin"
                                           placeholder="Padlock code"
                                           value="<?php echo htmlspecialchars($pin ?? '', ENT_QUOTES, 'UTF-8'); ?>">
                                    <p class="help-text">Padlock PIN (admins and key holders)</p>
                                </label>
                            </div>
                        </div>
                        <?php endif; ?>
                    </fieldset>

                    <fieldset style="margin-top: 1.5rem;">
                        <legend>Notes / History</legend>
                        <textarea name="notes" class="arm-notes-editor" rows="10"><?php echo htmlspecialchars($notes, ENT_QUOTES, 'UTF-8'); ?></textarea>
                    </fieldset>

                    <fieldset style="margin-top: 1.5rem;">
                        <legend>Audio</legend>
                        <?php
                        // Get list of available audio files
                        $audioDir = __DIR__ . '/../../audio';
                        $audioFiles = [];
                        if (is_dir($audioDir)) {
                            $files = scandir($audioDir);
                            foreach ($files as $file) {
                                if (pathinfo($file, PATHINFO_EXTENSION) === 'mp3') {
                                    $audioFiles[] = $file;
                                }
                            }
                        }
                        ?>
                        <label>Audio File
                            <input type="text" id="audioFileFilter" placeholder="Type to filter..." style="margin-bottom: 0.5rem;">
                            <select name="audio_file" id="audioFileSelect" size="6" style="height: auto;">
                                <option value="">-- No Audio --</option>
                                <?php foreach ($audioFiles as $file): ?>
                                <option value="<?php echo htmlspecialchars($file, ENT_QUOTES, 'UTF-8'); ?>"
                                        <?php echo $audio_file === $file ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($file, ENT_QUOTES, 'UTF-8'); ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                            <p class="help-text">Type to filter, then select an audio file. (<?php echo count($audioFiles); ?> files available)</p>
                        </label>
                        <script>
                        (function() {
                            var filter = document.getElementById('audioFileFilter');
                            var select = document.getElementById('audioFileSelect');
                            var options = Array.from(select.options);

                            filter.addEventListener('input', function() {
                                var term = this.value.toLowerCase();
                                options.forEach(function(opt) {
                                    if (opt.value === '' || opt.text.toLowerCase().includes(term)) {
                                        opt.style.display = '';
                                    } else {
                                        opt.style.display = 'none';
                                    }
                                });
                            });
                        })();
                        </script>
                    </fieldset>

                    <div style="margin-top: 1.5rem;">
                        <button type="submit" class="button primary large" style="border-radius: 8px;">Save Changes</button>
                        <a href="/admin/equipment/" class="button secondary large" style="border-radius: 8px;">Cancel</a>
                    </div>
                </form>
            </div>
        </div>

        <!-- Audio Upload Card -->
        <div class="card arm-card" style="margin-top: 1.5rem;">
            <div class="card-section">
                <h3 style="margin-top: 0;">Audio File Upload</h3>
                <p class="help-text">Upload MP3 audio files for equipment narration.</p>

                <form method="post" action="/admin/equipment/handlers/upload_audio.php" enctype="multipart/form-data">
                    <input type="hidden" name="equipment_id" value="<?php echo $equipmentId; ?>">

                    <label>Upload Audio File (MP3)
                        <input type="file" name="audio_file" accept="audio/mpeg,audio/mp3" required>
                        <p class="help-text">File will be uploaded to /audio/ directory and automatically selected above.</p>
                    </label>

                    <button type="submit" class="button success" style="border-radius: 8px;">Upload Audio File</button>
                </form>

                <?php if (!empty($audioFiles)): ?>
                <div style="margin-top: 1rem; padding: 1rem; background: #f8f9fa; border-radius: 5px;">
                    <p style="margin-bottom: 0; cursor: pointer; user-select: none;" onclick="var list = this.nextElementSibling; var arrow = this.querySelector('.toggle-arrow'); if (list.style.display === 'none') { list.style.display = 'block'; arrow.textContent = '▼'; } else { list.style.display = 'none'; arrow.textContent = '▶'; }">
                        <span class="toggle-arrow" style="display: inline-block; width: 1rem;">▶</span>
                        <strong>Available Audio Files (<?php echo count($audioFiles); ?>)</strong>
                    </p>
                    <ul style="margin: 0; margin-left: 1.5rem; margin-top: 0.5rem; display: none;">
                        <?php foreach ($audioFiles as $file): ?>
                        <li style="margin-bottom: 0.25rem;">
                            <?php echo htmlspecialchars($file, ENT_QUOTES, 'UTF-8'); ?>
                            <?php if ($audio_file === $file): ?>
                            <span style="color: #1779ba; font-weight: bold;">✓ Currently Selected</span>
                            <?php endif; ?>
                        </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Photo Management Card -->
        <div class="card arm-card" style="margin-top: 1.5rem;">
            <div class="card-section">
                <h3 style="margin-top: 0;">Photo Management</h3>
            <p class="help-text">Upload photos for this equipment. Files will be stored in <code>/images/equipment/<?php echo $equipmentId; ?>/</code></p>
            
            <?php
            // Check for existing photos
            $photoDir = __DIR__ . '/../../images/equipment/' . $equipmentId;
            $photoWebPath = '\images/equipment/' . $equipmentId;
            $mainPhoto = $photoDir . '/main.jpg';
            $thumbPhoto = $photoDir . '/thumb.jpg';
            
            // Count additional photos
            $additionalPhotos = [];
            $photoIndex = 1;
            while (file_exists($photoDir . '/photo-' . $photoIndex . '.jpg')) {
                $additionalPhotos[] = $photoIndex;
                $photoIndex++;
            }
            ?>
            
            <form method="post" action="/admin/equipment/handlers/upload_photos.php" enctype="multipart/form-data">
                <input type="hidden" name="equipment_id" value="<?php echo $equipmentId; ?>">
                
                <div class="grid-x grid-margin-x">
                    <div class="small-12 medium-6 cell">
                        <label>Main Photo
                            <input type="file" name="main_photo" accept="image/jpeg,image/jpg">
                            <p class="help-text">Primary display photo (saved as main.jpg)</p>
                        </label>
                        <?php if (file_exists($mainPhoto)): ?>
                            <div style="margin-bottom: 1rem;">
                                <img src="<?php echo $photoWebPath . '/main.jpg?v=' . time(); ?>" alt="Main photo" style="max-width: 200px; border-radius: 4px; border: 2px solid #1779ba;">
                                <p style="font-size: 0.85rem; margin: 0.25rem 0 0 0;">✓ Current main photo</p>
                            </div>
                        <?php endif; ?>
                    </div>
                    
                    <div class="small-12 medium-6 cell">
                        <label>Thumbnail Photo
                            <input type="file" name="thumb_photo" accept="image/jpeg,image/jpg">
                            <p class="help-text">Thumbnail for listings (saved as thumb.jpg)</p>
                        </label>
                        <?php if (file_exists($thumbPhoto)): ?>
                            <div style="margin-bottom: 1rem;">
                                <img src="<?php echo $photoWebPath . '/thumb.jpg?v=' . time(); ?>" alt="Thumbnail" style="max-width: 100px; border-radius: 4px; border: 2px solid #1779ba;">
                                <p style="font-size: 0.85rem; margin: 0.25rem 0 0 0;">✓ Current thumbnail</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
                
                <label>Additional Photos
                    <input type="file" name="additional_photos[]" accept="image/jpeg,image/jpg" multiple>
                    <p class="help-text">Upload multiple photos (saved as photo-1.jpg, photo-2.jpg, etc.)</p>
                </label>
                
                <?php if (!empty($additionalPhotos)): ?>
                    <div style="margin-bottom: 1rem;">
                        <p><strong>Current Additional Photos:</strong></p>
                        <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(100px, 1fr)); gap: 0.5rem;">
                            <?php foreach ($additionalPhotos as $idx): ?>
                                <div>
                                    <img src="<?php echo $photoWebPath . '/photo-' . $idx . '.jpg?v=' . time(); ?>" 
                                         alt="Photo <?php echo $idx; ?>" 
                                         style="width: 100%; height: 100px; object-fit: cover; border-radius: 4px; border: 2px solid #1779ba;">
                                    <p style="font-size: 0.75rem; margin: 0.25rem 0 0 0; text-align: center;">photo-<?php echo $idx; ?>.jpg</p>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endif; ?>
                
                <button type="submit" class="button success" style="border-radius: 8px;">Upload Photos</button>
            </form>
            </div>
        </div>

        <!-- Photo Captions Card -->
        <div class="card arm-card" style="margin-top: 1.5rem;">
            <div class="card-section">
                <h3 style="margin-top: 0;">Photo Captions</h3>
            <p class="help-text">Add descriptions for each photo. Leave blank if no caption needed.</p>
            
            <?php
            // Load existing captions
            $captionsFile = $photoDir . '/captions.json';
            $existingCaptions = [];
            if (file_exists($captionsFile)) {
                $captionsJson = file_get_contents($captionsFile);
                $existingCaptions = json_decode($captionsJson, true) ?? [];
            }
            
            // Collect all photo files for caption editing
            $allPhotoFiles = [];
            if (file_exists($mainPhoto)) $allPhotoFiles[] = 'main.jpg';
            if (file_exists($thumbPhoto)) $allPhotoFiles[] = 'thumb.jpg';
            foreach ($additionalPhotos as $idx) {
                $allPhotoFiles[] = "photo-{$idx}.jpg";
            }
            ?>
            
            <?php if (!empty($allPhotoFiles)): ?>
            <form method="post" action="/admin/equipment/handlers/save_captions.php">
                <input type="hidden" name="equipment_id" value="<?php echo $equipmentId; ?>">
                
                <?php foreach ($allPhotoFiles as $photoFile): ?>
                <div style="margin-bottom: 1.5rem; padding: 1rem; background: #f9f9f9; border-radius: 4px;">
                    <div style="margin-bottom: 0.5rem;">
                        <strong><?php echo htmlspecialchars($photoFile, ENT_QUOTES, 'UTF-8'); ?></strong>
                        <?php if (file_exists($photoDir . '/' . $photoFile)): ?>
                        <img src="<?php echo $photoWebPath . '/' . $photoFile . '?v=' . time(); ?>" 
                             alt="<?php echo $photoFile; ?>" 
                             style="max-width: 150px; display: block; margin-top: 0.5rem; border-radius: 4px; border: 2px solid #ddd;">
                        <?php endif; ?>
                    </div>
                    <label>Caption / Description
                        <textarea name="captions[<?php echo htmlspecialchars($photoFile, ENT_QUOTES, 'UTF-8'); ?>]" 
                                  rows="2" 
                                  placeholder="Enter a description for this photo..."><?php echo htmlspecialchars($existingCaptions[$photoFile] ?? '', ENT_QUOTES, 'UTF-8'); ?></textarea>
                    </label>
                </div>
                <?php endforeach; ?>
                
                <button type="submit" class="button primary" style="border-radius: 8px;">Save All Captions</button>
            </form>
            <?php else: ?>
            <div class="callout secondary">
                <p>No photos uploaded yet. Upload photos first, then you can add captions.</p>
            </div>
            <?php endif; ?>
            </div>
        </div>

        <!-- Document Management Card -->
        <div class="card arm-card" style="margin-top: 1.5rem;">
            <div class="card-section">
                <h3 style="margin-top: 0;">Document Management</h3>
            <p class="help-text">Upload documents such as flyers, brochures, manuals, and specifications for this equipment.</p>

            <?php
            // Load existing documents from database
            $docStmt = $pdo->prepare("
                SELECT id, filename, display_name, description, file_type, file_size, upload_date, sort_order
                FROM equipment_documents
                WHERE equipment_id = :equipment_id
                ORDER BY sort_order ASC, upload_date DESC
            ");
            $docStmt->execute([':equipment_id' => $equipmentId]);
            $existingDocuments = $docStmt->fetchAll();
            ?>

            <?php if (!empty($existingDocuments)): ?>
            <div style="margin-bottom: 1.5rem;">
                <h4>Current Documents</h4>
                <div style="display: grid; gap: 1rem;">
                    <?php foreach ($existingDocuments as $doc): ?>
                    <div style="padding: 1rem; background: #f9f9f9; border-radius: 4px; border-left: 4px solid #1779ba;">
                        <div style="display: flex; justify-content: space-between; align-items: start; gap: 1rem;">
                            <div style="flex: 1;">
                                <div style="margin-bottom: 0.5rem;">
                                    <strong style="font-size: 1.1rem;">
                                        <?php echo htmlspecialchars($doc['display_name'], ENT_QUOTES, 'UTF-8'); ?>
                                    </strong>
                                    <span style="display: inline-block; margin-left: 0.5rem; padding: 2px 8px; background: #e3f2fd; color: #1779ba; border-radius: 3px; font-size: 0.75rem; font-weight: 600; text-transform: uppercase;">
                                        <?php echo htmlspecialchars($doc['file_type'], ENT_QUOTES, 'UTF-8'); ?>
                                    </span>
                                </div>

                                <?php if (!empty($doc['description'])): ?>
                                <p style="margin: 0.5rem 0; color: #666; font-size: 0.9rem;">
                                    <?php echo htmlspecialchars($doc['description'], ENT_QUOTES, 'UTF-8'); ?>
                                </p>
                                <?php endif; ?>

                                <div style="margin-top: 0.5rem; font-size: 0.85rem; color: #888;">
                                    <span>Size: <?php echo number_format($doc['file_size'] / 1024, 1); ?> KB</span>
                                    <span style="margin-left: 1rem;">Uploaded: <?php echo date('M j, Y', strtotime($doc['upload_date'])); ?></span>
                                </div>

                                <div style="margin-top: 0.75rem;">
                                    <a href="/documents/equipment/<?php echo $equipmentId; ?>/<?php echo htmlspecialchars($doc['filename'], ENT_QUOTES, 'UTF-8'); ?>"
                                       target="_blank"
                                       class="button tiny primary"
                                       style="margin: 0 0.5rem 0 0;">
                                        View Document
                                    </a>
                                </div>
                            </div>

                            <div>
                                <form method="post" action="/admin/equipment/handlers/delete_document.php" style="margin: 0;" onsubmit="return confirm('Are you sure you want to delete this document?');">
                                    <input type="hidden" name="document_id" value="<?php echo $doc['id']; ?>">
                                    <input type="hidden" name="equipment_id" value="<?php echo $equipmentId; ?>">
                                    <button type="submit" class="button tiny alert" style="margin: 0; border-radius: 8px;">Delete</button>
                                </form>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>

            <form method="post" action="/admin/equipment/handlers/upload_documents.php" enctype="multipart/form-data">
                <input type="hidden" name="equipment_id" value="<?php echo $equipmentId; ?>">

                <div id="documentUploadContainer">
                    <div class="document-upload-row" style="margin-bottom: 1.5rem; padding: 1rem; background: #fff; border: 2px dashed #ccc; border-radius: 4px;">
                        <label>Select Document File(s) *
                            <input type="file" name="documents[]" accept=".pdf,.jpg,.jpeg,.png,.doc,.docx,.xls,.xlsx" multiple required>
                            <p class="help-text">Allowed types: PDF, JPG, PNG, DOC, DOCX, XLS, XLSX (max 50MB each)</p>
                        </label>

                        <label>Display Name (optional)
                            <input type="text" name="display_names[]" placeholder="e.g. Equipment Manual, Restoration Photos">
                            <p class="help-text">Leave blank to use the original filename</p>
                        </label>

                        <label>Description (optional)
                            <textarea name="descriptions[]" rows="2" placeholder="Brief description of this document..."></textarea>
                        </label>
                    </div>
                </div>

                <button type="submit" class="button success" style="border-radius: 8px;">Upload Documents</button>
            </form>

            <div style="margin-top: 1rem; padding: 1rem; background: #e7f4f9; border-radius: 4px;">
                <p style="margin: 0; font-size: 0.9rem; color: #0c5460;">
                    <strong>Tip:</strong> You can select multiple files at once. Each file can have its own display name and description, but you'll need to upload them one batch at a time if you want different descriptions.
                </p>
            </div>
            </div>
        </div>
    </div>

    <!-- Sidebar -->
    <div class="small-12 medium-4 cell">
        <div class="card arm-card" style="background: #e7f4f9;">
            <div class="card-section">
                <h5 style="margin-top: 0;">ℹ️ Equipment ID</h5>
                <p style="font-size: 0.9rem; margin: 0;">
                    <strong>#<?php echo $equipmentId; ?></strong>
                </p>
            </div>
        </div>

        <div class="card arm-card" style="margin-top: 1rem;">
            <div class="card-section">
                <h5 style="margin-top: 0;">Quick Links</h5>
                <ul style="margin: 0; list-style: none;">
                    <li style="margin-bottom: 0.5rem;">
                        <a href="/equipment/<?php echo $equipmentId; ?>/" target="_blank" style="color: #1779ba;">View Public Page →</a>
                    </li>
                    <li>
                        <a href="/admin/equipment/" style="color: #1779ba;">Back to Equipment List</a>
                    </li>
                </ul>
            </div>
        </div>
    </div>
</div>

<!-- CKEditor 4 WYSIWYG editor -->
<script src="https://cdn.ckeditor.com/4.22.1/standard/ckeditor.js"></script>
<script>
  CKEDITOR.replace('notes', {
    height: 300,
    removeButtons: 'Subscript,Superscript',
    format_tags: 'p;h1;h2;h3;pre',
    removeDialogTabs: 'image:advanced;link:advanced',
    versionCheck: false
  });
</script>

<?php require_once __DIR__ . '/../../assets/footer.php'; ?>
