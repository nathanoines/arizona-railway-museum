<?php
/**
 * Activity Log PDF Export
 *
 * Generates a PDF report of activity log entries based on current filters.
 */

declare(strict_types=1);

session_start();
require_once __DIR__ . '/../../../config/db.php';
require_once __DIR__ . '/../../../lib/tcpdf/tcpdf.php';

// Set timezone to MST (Arizona)
date_default_timezone_set('America/Phoenix');

// Check if user is logged in as admin
if (!isset($_SESSION['user_id'])) {
    header('Location: /login.php');
    exit;
}

$userRole = $_SESSION['user_role'] ?? 'user';
if ($userRole !== 'admin') {
    header('Location: /members/index.php');
    exit;
}

$pdo = getDbConnection();

// Get filters from query string
$filter_action = $_GET['action'] ?? '';
$filter_entity = $_GET['entity'] ?? '';
$filter_user = $_GET['user'] ?? '';
$filter_date_from = $_GET['date_from'] ?? '';
$filter_date_to = $_GET['date_to'] ?? '';

// Build query with filters (same logic as index.php)
$where_clauses = [];
$params = [];

if ($filter_action !== '') {
    $where_clauses[] = "al.action_type = :action_type";
    $params[':action_type'] = $filter_action;
}

if ($filter_entity !== '') {
    $where_clauses[] = "al.entity_type = :entity_type";
    $params[':entity_type'] = $filter_entity;
}

if ($filter_user !== '' && ctype_digit($filter_user)) {
    $where_clauses[] = "al.user_id = :user_id";
    $params[':user_id'] = (int)$filter_user;
}

if ($filter_date_from !== '') {
    $where_clauses[] = "DATE(al.created_at) >= :date_from";
    $params[':date_from'] = $filter_date_from;
}

if ($filter_date_to !== '') {
    $where_clauses[] = "DATE(al.created_at) <= :date_to";
    $params[':date_to'] = $filter_date_to;
}

$where_sql = '';
if (!empty($where_clauses)) {
    $where_sql = 'WHERE ' . implode(' AND ', $where_clauses);
}

// Get all matching activity logs (no pagination for PDF)
$sql = "SELECT al.*, m.first_name, m.last_name, m.email as user_email
        FROM activity_logs al
        LEFT JOIN members m ON al.user_id = m.id
        $where_sql
        ORDER BY al.created_at DESC";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$activities = $stmt->fetchAll();

// Get user name for filter display
$filter_user_name = 'All';
if ($filter_user !== '' && ctype_digit($filter_user)) {
    $user_stmt = $pdo->prepare("SELECT first_name, last_name FROM members WHERE id = ?");
    $user_stmt->execute([(int)$filter_user]);
    $user_data = $user_stmt->fetch();
    if ($user_data) {
        $filter_user_name = trim($user_data['first_name'] . ' ' . $user_data['last_name']);
    }
}

// Create PDF
class ActivityLogPDF extends TCPDF {
    public function Header() {
        $this->SetFont('helvetica', 'B', 16);
        $this->Cell(0, 10, 'Arizona Railway Museum', 0, 1, 'C');
        $this->SetFont('helvetica', '', 12);
        $this->Cell(0, 6, 'Activity Log Report', 0, 1, 'C');
        $this->Ln(2);
    }

    public function Footer() {
        $this->SetY(-15);
        $this->SetFont('helvetica', 'I', 8);
        $this->Cell(0, 10, 'Page ' . $this->getAliasNumPage() . ' of ' . $this->getAliasNbPages(), 0, 0, 'C');
    }
}

$pdf = new ActivityLogPDF('L', 'mm', 'A4', true, 'UTF-8', false);

// Set document properties
$pdf->SetCreator('Arizona Railway Museum');
$pdf->SetAuthor('Arizona Railway Museum');
$pdf->SetTitle('Activity Log Report');
$pdf->SetSubject('Activity Log Export');

// Set margins
$pdf->SetMargins(10, 35, 10);
$pdf->SetHeaderMargin(10);
$pdf->SetFooterMargin(10);

// Set auto page breaks
$pdf->SetAutoPageBreak(true, 20);

// Add a page
$pdf->AddPage();

// Build filter summary
$filters = [];
$filters[] = 'Action: ' . ($filter_action !== '' ? ucwords(str_replace('_', ' ', $filter_action)) : 'All');
$filters[] = 'Entity: ' . ($filter_entity !== '' ? ucwords($filter_entity) : 'All');
$filters[] = 'User: ' . $filter_user_name;

$date_range = 'All Time';
if ($filter_date_from !== '' && $filter_date_to !== '') {
    $date_range = date('M j, Y', strtotime($filter_date_from)) . ' - ' . date('M j, Y', strtotime($filter_date_to));
} elseif ($filter_date_from !== '') {
    $date_range = 'From ' . date('M j, Y', strtotime($filter_date_from));
} elseif ($filter_date_to !== '') {
    $date_range = 'Through ' . date('M j, Y', strtotime($filter_date_to));
}
$filters[] = 'Date Range: ' . $date_range;

// Print generation info and filters
$pdf->SetFont('helvetica', '', 9);
$pdf->Cell(0, 5, 'Generated: ' . date('F j, Y \a\t g:i A') . ' MST', 0, 1, 'C');
$pdf->Cell(0, 5, implode(' | ', $filters), 0, 1, 'C');
$pdf->Cell(0, 5, 'Total Records: ' . number_format(count($activities)), 0, 1, 'C');
$pdf->Ln(5);

// Table header
$pdf->SetFont('helvetica', 'B', 9);
$pdf->SetFillColor(240, 240, 240);

// Column widths (total = 277 for A4 landscape with 10mm margins on each side)
$col_date = 38;
$col_action = 30;
$col_entity = 36;
$col_description = 128;
$col_user = 45;
$row_height = 6;

$pdf->Cell($col_date, 7, 'Date/Time', 1, 0, 'C', true);
$pdf->Cell($col_action, 7, 'Action', 1, 0, 'C', true);
$pdf->Cell($col_entity, 7, 'Entity', 1, 0, 'C', true);
$pdf->Cell($col_description, 7, 'Description', 1, 0, 'C', true);
$pdf->Cell($col_user, 7, 'User', 1, 1, 'C', true);

// Table data
$pdf->SetFont('helvetica', '', 8);

if (empty($activities)) {
    $pdf->Cell($col_date + $col_action + $col_entity + $col_description + $col_user, 10, 'No activity records found.', 1, 1, 'C');
} else {
    foreach ($activities as $activity) {
        // Check if we need a new page
        if ($pdf->GetY() + $row_height > $pdf->getPageHeight() - 25) {
            $pdf->AddPage();
            // Reprint header
            $pdf->SetFont('helvetica', 'B', 9);
            $pdf->SetFillColor(240, 240, 240);
            $pdf->Cell($col_date, 7, 'Date/Time', 1, 0, 'C', true);
            $pdf->Cell($col_action, 7, 'Action', 1, 0, 'C', true);
            $pdf->Cell($col_entity, 7, 'Entity', 1, 0, 'C', true);
            $pdf->Cell($col_description, 7, 'Description', 1, 0, 'C', true);
            $pdf->Cell($col_user, 7, 'User', 1, 1, 'C', true);
            $pdf->SetFont('helvetica', '', 8);
        }

        // Date/Time - single line format
        $date_time = date('M j, Y g:i A', strtotime($activity['created_at']));
        $pdf->Cell($col_date, $row_height, $date_time, 1, 0, 'C');

        // Action
        $action_text = ucwords(str_replace('_', ' ', $activity['action_type']));
        $pdf->Cell($col_action, $row_height, $action_text, 1, 0, 'C');

        // Entity
        $entity_text = ucwords($activity['entity_type'] ?? '-');
        if ($activity['entity_id']) {
            $entity_text .= ' #' . $activity['entity_id'];
        }
        $pdf->Cell($col_entity, $row_height, $entity_text, 1, 0, 'C');

        // Description - truncate if too long
        $description = $activity['description'] ?? '-';
        if (strlen($description) > 95) {
            $description = substr($description, 0, 92) . '...';
        }
        $pdf->Cell($col_description, $row_height, $description, 1, 0, 'L');

        // User - full name
        $user_name = trim(($activity['first_name'] ?? '') . ' ' . ($activity['last_name'] ?? ''));
        if (empty($user_name)) {
            $user_name = 'Unknown';
        }
        $pdf->Cell($col_user, $row_height, $user_name, 1, 1, 'C');
    }
}

// Generate filename
$filename = 'activity_log_' . date('Y-m-d_His') . '.pdf';

// Output PDF
$pdf->Output($filename, 'D');
exit;
