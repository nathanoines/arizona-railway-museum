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

// Setup directories
$photoDir = __DIR__ . '/../../../images/equipment/' . $equipmentId;
if (!is_dir($photoDir)) {
    if (!mkdir($photoDir, 0755, true)) {
        http_response_code(500);
        die('Failed to create photo directory.');
    }
}

$errors = [];
$uploadedCount = 0;

// Log upload attempt for debugging
error_log("Photo upload attempt for equipment ID: $equipmentId");
if (isset($_FILES['additional_photos'])) {
    error_log("Additional photos array: " . print_r($_FILES['additional_photos'], true));
}

// Helper function to validate and save uploaded image
function saveUploadedImage($fileArray, $targetPath, &$errors, $label) {
    if ($fileArray['error'] === UPLOAD_ERR_NO_FILE) {
        return false; // No file uploaded, not an error
    }
    
    if ($fileArray['error'] !== UPLOAD_ERR_OK) {
        $errorMessages = [
            UPLOAD_ERR_INI_SIZE   => 'File exceeds server upload_max_filesize limit (' . ini_get('upload_max_filesize') . ')',
            UPLOAD_ERR_FORM_SIZE  => 'File exceeds form MAX_FILE_SIZE limit',
            UPLOAD_ERR_PARTIAL    => 'File was only partially uploaded',
            UPLOAD_ERR_NO_TMP_DIR => 'Missing temporary folder',
            UPLOAD_ERR_CANT_WRITE => 'Failed to write file to disk',
            UPLOAD_ERR_EXTENSION  => 'Upload stopped by PHP extension',
        ];
        $errorMsg = $errorMessages[$fileArray['error']] ?? "Upload error (code {$fileArray['error']})";
        $errors[] = "$label: $errorMsg";
        error_log("Upload error for $label: $errorMsg");
        return false;
    }
    
    // Validate MIME type
    $allowedMimes = ['image/jpeg', 'image/jpg'];
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mimeType = finfo_file($finfo, $fileArray['tmp_name']);
    finfo_close($finfo);
    
    if (!in_array($mimeType, $allowedMimes)) {
        $errors[] = "$label: Only JPEG images are allowed (received $mimeType).";
        return false;
    }
    
    // Validate file size (max 5MB)
    if ($fileArray['size'] > 50 * 1024 * 1024) {
        $errors[] = "$label: File too large (max 50MB).";
        return false;
    }
    
    // Move uploaded file
    if (!move_uploaded_file($fileArray['tmp_name'], $targetPath)) {
        $errors[] = "$label: Failed to save file.";
        return false;
    }
    
    // Set proper permissions
    chmod($targetPath, 0644);
    
    return true;
}

// Process main photo
if (isset($_FILES['main_photo']) && $_FILES['main_photo']['error'] !== UPLOAD_ERR_NO_FILE) {
    $mainPath = $photoDir . '/main.jpg';
    if (saveUploadedImage($_FILES['main_photo'], $mainPath, $errors, 'Main Photo')) {
        $uploadedCount++;
    }
}

// Process thumbnail
if (isset($_FILES['thumb_photo']) && $_FILES['thumb_photo']['error'] !== UPLOAD_ERR_NO_FILE) {
    $thumbPath = $photoDir . '/thumb.jpg';
    if (saveUploadedImage($_FILES['thumb_photo'], $thumbPath, $errors, 'Thumbnail')) {
        $uploadedCount++;
    }
}

// Process additional photos
if (isset($_FILES['additional_photos']) && is_array($_FILES['additional_photos']['tmp_name'])) {
    // Find next available photo number
    $nextPhotoNum = 1;
    while (file_exists($photoDir . '/photo-' . $nextPhotoNum . '.jpg')) {
        $nextPhotoNum++;
    }
    
    foreach ($_FILES['additional_photos']['tmp_name'] as $idx => $tmpName) {
        // Skip if no file uploaded for this slot
        if (!isset($_FILES['additional_photos']['error'][$idx]) || 
            $_FILES['additional_photos']['error'][$idx] === UPLOAD_ERR_NO_FILE) {
            continue;
        }
        
        $fileArray = [
            'tmp_name' => $_FILES['additional_photos']['tmp_name'][$idx],
            'error'    => $_FILES['additional_photos']['error'][$idx],
            'size'     => $_FILES['additional_photos']['size'][$idx],
        ];
        
        $photoPath = $photoDir . '/photo-' . $nextPhotoNum . '.jpg';
        if (saveUploadedImage($fileArray, $photoPath, $errors, "Additional Photo $nextPhotoNum")) {
            $uploadedCount++;
            $nextPhotoNum++;
        }
    }
}

// Redirect back with status
if (!empty($errors)) {
    $_SESSION['upload_errors'] = $errors;
    $_SESSION['upload_success_count'] = $uploadedCount;
} elseif ($uploadedCount > 0) {
    $_SESSION['upload_success'] = "$uploadedCount photo(s) uploaded successfully.";
} else {
    $_SESSION['upload_info'] = "No files were selected for upload.";
}

header('Location: /admin/equipment/edit/' . $equipmentId);
exit;
