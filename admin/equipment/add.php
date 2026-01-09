<?php
session_start();
require_once __DIR__ . '/../../config/db.php';
$pdo = getDbConnection();

$isAdmin = ($_SESSION['user_role'] ?? '') === 'admin';
$isKeyHolder = !empty($_SESSION['is_key_holder']);
$canViewPins = $isAdmin || $isKeyHolder;

$errors  = [];
$success = null;

// Default form values
$reporting_mark = '';
$road_number    = '';
$equipment_type = '';
$equipment_category = '';
$builder        = '';
$built_year     = '';
$status         = '';
$pin            = '';
$notes          = '';
$audio_file     = '';

// Handle form submit
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
        $sql = "INSERT INTO equipment
                    (reporting_mark, road_number, equipment_type, equipment_category, builder, built_year, status, pin, notes, audio_file)
                VALUES
                    (:reporting_mark, :road_number, :equipment_type, :equipment_category, :builder, :built_year, :status, :pin, :notes, :audio_file)";

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
        ]);

        $success = 'Equipment entry added successfully.';
        // Clear form after success
        $reporting_mark = $road_number = $equipment_type = $equipment_category = $builder =
        $built_year = $status = $pin = $notes = $audio_file = '';
    }
}

$page_title = 'Add Equipment | Arizona Railway Museum';
require_once __DIR__ . '/../../assets/header.php';

// Simple admin gate
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
                <h1 style="margin-bottom: 0.25rem;">Add Equipment</h1>
                <p class="lead" style="margin-bottom: 0;">Add a new piece of rolling stock to the museum roster.</p>
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
    </div>
</div>

<div class="grid-x grid-margin-x">
    <div class="small-12 medium-8 cell">
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
                                        value="<?php echo htmlspecialchars($reporting_mark, ENT_QUOTES, 'UTF-8'); ?>">
                                    <p class="help-text">Full company or railroad name</p>
                                </label>
                            </div>
                            <div class="small-12 medium-6 cell">
                                <label>Number/Identifier *
                                    <input type="text" name="road_number" required
                                        placeholder="e.g. SP 1234, ATSF-405, Plaza Taos"
                                        value="<?php echo htmlspecialchars($road_number, ENT_QUOTES, 'UTF-8'); ?>">
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
                                        value="<?php echo htmlspecialchars($built_year, ENT_QUOTES, 'UTF-8'); ?>">
                                </label>
                            </div>
                            <div class="small-12 medium-4 cell">
                                <label>Status
                                    <input type="text" name="status"
                                        placeholder="On display, Stored, Under restoration"
                                        value="<?php echo htmlspecialchars($status, ENT_QUOTES, 'UTF-8'); ?>">
                                </label>
                            </div>
                        </div>
                        <?php if ($canViewPins): ?>
                        <div class="grid-x grid-margin-x" style="margin-top: 1rem;">
                            <div class="small-12 medium-4 cell">
                                <label>PIN
                                    <input type="text" name="pin"
                                        placeholder="Padlock code"
                                        value="<?php echo htmlspecialchars($pin, ENT_QUOTES, 'UTF-8'); ?>">
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
                            <select name="audio_file">
                                <option value="">-- No Audio --</option>
                                <?php foreach ($audioFiles as $file): ?>
                                <option value="<?php echo htmlspecialchars($file, ENT_QUOTES, 'UTF-8'); ?>">
                                    <?php echo htmlspecialchars($file, ENT_QUOTES, 'UTF-8'); ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                            <p class="help-text">Select an audio file for equipment narration.</p>
                        </label>
                    </fieldset>

                    <div style="margin-top: 1.5rem;">
                        <button type="submit" class="button primary large" style="border-radius: 8px;">Save Equipment</button>
                        <a href="/admin/equipment/" class="button secondary large" style="border-radius: 8px;">Cancel</a>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="small-12 medium-4 cell">
        <div class="card arm-card" style="background: #e7f4f9;">
            <div class="card-section">
                <h5 style="margin-top: 0;">ℹ️ Note</h5>
                <p style="font-size: 0.9rem; margin: 0;">
                    After creating this equipment entry, you can add photos and documents by editing the entry.
                </p>
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
