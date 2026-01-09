<?php
session_start();

// Simple admin check
if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'admin') {
    http_response_code(403);
    die('Access denied. Admins only.');
}

// Validate equipment ID
if (!isset($_POST['equipment_id']) || !ctype_digit($_POST['equipment_id'])) {
    http_response_code(400);
    die('Invalid equipment ID.');
}
$equipmentId = (int)$_POST['equipment_id'];

// Verify equipment exists
require_once __DIR__ . '/../../../config/db.php';
$pdo = getDbConnection();
$stmt = $pdo->prepare("SELECT id FROM equipment WHERE id = :id LIMIT 1");
$stmt->execute([':id' => $equipmentId]);
if (!$stmt->fetch()) {
    http_response_code(404);
    die('Equipment not found.');
}

// Check if file was uploaded
if (!isset($_FILES['audio_file']) || $_FILES['audio_file']['error'] === UPLOAD_ERR_NO_FILE) {
    $_SESSION['audio_error'] = "No file selected for upload.";
    header('Location: /admin/equipment/edit/' . $equipmentId);
    exit;
}

$file = $_FILES['audio_file'];

// Check for upload errors
if ($file['error'] !== UPLOAD_ERR_OK) {
    $errorMessages = [
        UPLOAD_ERR_INI_SIZE   => 'File exceeds server upload_max_filesize limit (' . ini_get('upload_max_filesize') . ')',
        UPLOAD_ERR_FORM_SIZE  => 'File exceeds form MAX_FILE_SIZE limit',
        UPLOAD_ERR_PARTIAL    => 'File was only partially uploaded',
        UPLOAD_ERR_NO_TMP_DIR => 'Missing temporary folder',
        UPLOAD_ERR_CANT_WRITE => 'Failed to write file to disk',
        UPLOAD_ERR_EXTENSION  => 'Upload stopped by PHP extension',
    ];
    $errorMsg = $errorMessages[$file['error']] ?? "Upload error (code {$file['error']})";
    $_SESSION['audio_error'] = "Audio upload failed: $errorMsg";
    header('Location: /admin/equipment/edit/' . $equipmentId);
    exit;
}

// Validate MIME type
$allowedMimes = ['audio/mpeg', 'audio/mp3'];
$finfo = finfo_open(FILEINFO_MIME_TYPE);
$mimeType = finfo_file($finfo, $file['tmp_name']);
finfo_close($finfo);

if (!in_array($mimeType, $allowedMimes)) {
    $_SESSION['audio_error'] = "Only MP3 files are allowed (received $mimeType).";
    header('Location: /admin/equipment/edit/' . $equipmentId);
    exit;
}

// Validate file size (max 20MB for audio)
if ($file['size'] > 20 * 1024 * 1024) {
    $_SESSION['audio_error'] = "Audio file too large (max 20MB).";
    header('Location: /admin/equipment/edit/' . $equipmentId);
    exit;
}

// Setup audio directory
$audioDir = __DIR__ . '/../../../audio';
if (!is_dir($audioDir)) {
    if (!mkdir($audioDir, 0755, true)) {
        $_SESSION['audio_error'] = "Failed to create audio directory.";
        header('Location: /admin/equipment/edit/' . $equipmentId);
        exit;
    }
}

// Generate safe filename
$originalName = pathinfo($file['name'], PATHINFO_FILENAME);
$extension = pathinfo($file['name'], PATHINFO_EXTENSION);
$safeFilename = preg_replace('/[^a-zA-Z0-9_-]/', '-', $originalName) . '.' . $extension;

// Check if file exists, add number if needed
$targetPath = $audioDir . '/' . $safeFilename;
$counter = 1;
while (file_exists($targetPath)) {
    $safeFilename = preg_replace('/[^a-zA-Z0-9_-]/', '-', $originalName) . '-' . $counter . '.' . $extension;
    $targetPath = $audioDir . '/' . $safeFilename;
    $counter++;
}

// Move uploaded file
if (!move_uploaded_file($file['tmp_name'], $targetPath)) {
    $_SESSION['audio_error'] = "Failed to save audio file.";
    header('Location: /admin/equipment/edit/' . $equipmentId);
    exit;
}

// Set proper permissions
chmod($targetPath, 0644);

// Auto-select this audio file for the equipment
$stmt = $pdo->prepare("UPDATE equipment SET audio_file = :audio_file WHERE id = :id");
$stmt->execute([
    ':audio_file' => $safeFilename,
    ':id' => $equipmentId
]);

$_SESSION['audio_success'] = "Audio file '$safeFilename' uploaded and selected successfully.";
header('Location: /admin/equipment/edit/' . $equipmentId);
exit;
