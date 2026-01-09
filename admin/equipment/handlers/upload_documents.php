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
$documentDir = __DIR__ . '/../../../documents/equipment/' . $equipmentId;
if (!is_dir($documentDir)) {
    if (!mkdir($documentDir, 0755, true)) {
        http_response_code(500);
        die('Failed to create documents directory.');
    }
}

$errors = [];
$uploadedCount = 0;

// Allowed document types
$allowedMimes = [
    'application/pdf',
    'image/jpeg',
    'image/jpg',
    'image/png',
    'application/msword',
    'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
    'application/vnd.ms-excel',
    'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
];

$allowedExtensions = ['pdf', 'jpg', 'jpeg', 'png', 'doc', 'docx', 'xls', 'xlsx'];

// Process document uploads
if (isset($_FILES['documents']) && is_array($_FILES['documents']['tmp_name'])) {
    foreach ($_FILES['documents']['tmp_name'] as $idx => $tmpName) {
        // Skip if no file uploaded for this slot
        if (!isset($_FILES['documents']['error'][$idx]) ||
            $_FILES['documents']['error'][$idx] === UPLOAD_ERR_NO_FILE) {
            continue;
        }

        // Check for upload errors
        if ($_FILES['documents']['error'][$idx] !== UPLOAD_ERR_OK) {
            $errorMessages = [
                UPLOAD_ERR_INI_SIZE   => 'File exceeds server upload_max_filesize limit',
                UPLOAD_ERR_FORM_SIZE  => 'File exceeds form MAX_FILE_SIZE limit',
                UPLOAD_ERR_PARTIAL    => 'File was only partially uploaded',
                UPLOAD_ERR_NO_TMP_DIR => 'Missing temporary folder',
                UPLOAD_ERR_CANT_WRITE => 'Failed to write file to disk',
                UPLOAD_ERR_EXTENSION  => 'Upload stopped by PHP extension',
            ];
            $errorMsg = $errorMessages[$_FILES['documents']['error'][$idx]] ?? "Upload error";
            $errors[] = "Document " . ($idx + 1) . ": $errorMsg";
            continue;
        }

        // Get original filename and sanitize it
        $originalFilename = $_FILES['documents']['name'][$idx];
        $fileSize = $_FILES['documents']['size'][$idx];
        $tmpFilePath = $_FILES['documents']['tmp_name'][$idx];

        // Validate file extension
        $fileExtension = strtolower(pathinfo($originalFilename, PATHINFO_EXTENSION));
        if (!in_array($fileExtension, $allowedExtensions)) {
            $errors[] = "$originalFilename: File type not allowed. Allowed types: PDF, JPG, PNG, DOC, DOCX, XLS, XLSX";
            continue;
        }

        // Validate MIME type
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mimeType = finfo_file($finfo, $tmpFilePath);
        finfo_close($finfo);

        if (!in_array($mimeType, $allowedMimes)) {
            $errors[] = "$originalFilename: Invalid file type detected.";
            continue;
        }

        // Validate file size (max 50MB)
        if ($fileSize > 50 * 1024 * 1024) {
            $errors[] = "$originalFilename: File too large (max 50MB).";
            continue;
        }

        // Generate unique filename to avoid conflicts
        $safeFilename = preg_replace('/[^a-zA-Z0-9_\-\.]/', '_', $originalFilename);
        $uniqueFilename = time() . '_' . $safeFilename;
        $targetPath = $documentDir . '/' . $uniqueFilename;

        // Get display name and description from POST
        $displayName = isset($_POST['display_names'][$idx]) ? trim($_POST['display_names'][$idx]) : $originalFilename;
        $description = isset($_POST['descriptions'][$idx]) ? trim($_POST['descriptions'][$idx]) : '';

        // Default display name to original filename if empty
        if (empty($displayName)) {
            $displayName = $originalFilename;
        }

        // Move uploaded file
        if (!move_uploaded_file($tmpFilePath, $targetPath)) {
            $errors[] = "$originalFilename: Failed to save file.";
            continue;
        }

        // Set proper permissions
        chmod($targetPath, 0644);

        // Get next sort order
        $sortStmt = $pdo->prepare("SELECT COALESCE(MAX(sort_order), 0) + 1 as next_order FROM equipment_documents WHERE equipment_id = :equipment_id");
        $sortStmt->execute([':equipment_id' => $equipmentId]);
        $nextOrder = $sortStmt->fetchColumn();

        // Insert document record into database
        $insertStmt = $pdo->prepare("
            INSERT INTO equipment_documents
            (equipment_id, filename, display_name, description, file_type, file_size, sort_order)
            VALUES
            (:equipment_id, :filename, :display_name, :description, :file_type, :file_size, :sort_order)
        ");

        $insertStmt->execute([
            ':equipment_id' => $equipmentId,
            ':filename' => $uniqueFilename,
            ':display_name' => $displayName,
            ':description' => $description,
            ':file_type' => $fileExtension,
            ':file_size' => $fileSize,
            ':sort_order' => $nextOrder,
        ]);

        $uploadedCount++;
    }
}

// Redirect back with status
if (!empty($errors)) {
    $_SESSION['document_errors'] = $errors;
    if ($uploadedCount > 0) {
        $_SESSION['document_partial_success'] = "$uploadedCount document(s) uploaded, but some had errors.";
    }
} elseif ($uploadedCount > 0) {
    $_SESSION['document_success'] = "$uploadedCount document(s) uploaded successfully.";
} else {
    $_SESSION['document_info'] = "No files were selected for upload.";
}

header('Location: /admin/equipment/edit/' . $equipmentId);
exit;
