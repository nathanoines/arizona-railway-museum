<?php
/**
 * Admin API: Search Members
 *
 * AJAX endpoint for searching members by name or email.
 * Used by the manual application-to-member linking feature.
 *
 * Returns JSON array of matching members.
 */

declare(strict_types=1);

header('Content-Type: application/json');

session_start();
require_once __DIR__ . '/../../config/db.php';

// Check if user is logged in as admin
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$userRole = $_SESSION['user_role'] ?? 'user';
if ($userRole !== 'admin') {
    http_response_code(403);
    echo json_encode(['error' => 'Forbidden']);
    exit;
}

// Get search query
$query = trim($_GET['q'] ?? '');

if (strlen($query) < 2) {
    echo json_encode([]);
    exit;
}

try {
    $pdo = getDbConnection();

    // Search by name or email
    $search = '%' . $query . '%';

    $sql = "SELECT
                id,
                first_name,
                last_name,
                email,
                membership_status,
                membership_expires_at
            FROM members
            WHERE
                CONCAT(first_name, ' ', last_name) LIKE :search1
                OR email LIKE :search2
            ORDER BY
                last_name ASC,
                first_name ASC
            LIMIT 20";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        ':search1' => $search,
        ':search2' => $search
    ]);

    $members = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Format response
    $results = array_map(function($member) {
        $name = trim($member['first_name'] . ' ' . $member['last_name']);
        $status = $member['membership_status'] ?: 'inactive';
        $expires = $member['membership_expires_at']
            ? date('M j, Y', strtotime($member['membership_expires_at']))
            : null;

        return [
            'id' => (int)$member['id'],
            'name' => $name ?: '(No name)',
            'email' => $member['email'],
            'status' => $status,
            'expires' => $expires
        ];
    }, $members);

    echo json_encode($results);

} catch (PDOException $e) {
    error_log('Member search error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Database error']);
}