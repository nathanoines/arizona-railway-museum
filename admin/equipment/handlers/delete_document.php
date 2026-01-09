<?php
session_start();

// Simple admin check
if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'admin') {
    http_response_code(403);
    die('Access denied. Admins only.');
}

// Validate document ID
if (!isset($_POST['document_id']) || !ctype_digit($_POST['document_id'])) {
    http_response_code(400);
    die('Invalid document ID.');
}
$documentId = (int)$_POST['document_id'];

// Validate equipment ID for redirect
if (!isset($_POST['equipment_id']) || !ctype_digit($_POST['equipment_id'])) {
    http_response_code(400);
    die('Invalid equipment ID.');
}
$equipmentId = (int)$_POST['equipment_id'];

require_once __DIR__ . '/../../../config/db.php';
$pdo = getDbConnection();

// Get document info
$stmt = $pdo->prepare("
    SELECT equipment_id, filename
    FROM equipment_documents
    WHERE id = :id
    LIMIT 1
");
$stmt->execute([':id' => $documentId]);
$document = $stmt->fetch();

if (!$document) {
    $_SESSION['document_error'] = "Document not found.";
    header('Location: /admin/equipment/edit/' . $equipmentId);
    exit;
}

// Verify equipment_id matches
if ($document['equipment_id'] != $equipmentId) {
    $_SESSION['document_error'] = "Document does not belong to this equipment.";
    header('Location: /admin/equipment/edit/' . $equipmentId);
    exit;
}

// Delete physical file
$documentPath = __DIR__ . '/../../../documents/equipment/' . $equipmentId . '/' . $document['filename'];
if (file_exists($documentPath)) {
    unlink($documentPath);
}

// Delete database record
$deleteStmt = $pdo->prepare("DELETE FROM equipment_documents WHERE id = :id");
$deleteStmt->execute([':id' => $documentId]);

$_SESSION['document_success'] = "Document deleted successfully.";
header('Location: /admin/equipment/edit/' . $equipmentId);
exit;
