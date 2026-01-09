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

// Get captions from POST
$captions = $_POST['captions'] ?? [];

// Clean up empty captions
$cleanedCaptions = [];
foreach ($captions as $filename => $caption) {
    $caption = trim($caption);
    if ($caption !== '') {
        $cleanedCaptions[$filename] = $caption;
    }
}

// Setup directory
$photoDir = __DIR__ . '/../../../images/equipment/' . $equipmentId;
if (!is_dir($photoDir)) {
    $_SESSION['caption_error'] = "Photo directory does not exist.";
    header('Location: /admin/equipment/edit/' . $equipmentId);
    exit;
}

// Save captions to JSON file
$captionsFile = $photoDir . '/captions.json';
$jsonData = json_encode($cleanedCaptions, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

if (file_put_contents($captionsFile, $jsonData) === false) {
    $_SESSION['caption_error'] = "Failed to save captions file.";
} else {
    chmod($captionsFile, 0644);
    $_SESSION['caption_success'] = "Photo captions saved successfully.";
}

header('Location: /admin/equipment/edit/' . $equipmentId);
exit;
