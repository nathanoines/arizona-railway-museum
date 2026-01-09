<?php
session_start();
require_once __DIR__ . '/../../config/db.php';

// Access check: must be admin OR key holder
$isAdmin = ($_SESSION['user_role'] ?? '') === 'admin';
$isKeyHolder = !empty($_SESSION['is_key_holder']);
$canViewPins = $isAdmin || $isKeyHolder;

if (!$isAdmin && !$isKeyHolder) {
    header('Location: /members/');
    exit;
}

$pdo = getDbConnection();

// Fetch existing equipment for display
$stmt = $pdo->query("
    SELECT id, reporting_mark, road_number, equipment_type, built_year, status, pin
    FROM equipment
    ORDER BY reporting_mark, road_number
");
$equipmentRows = $stmt->fetchAll();

$page_title = 'Equipment Management | Arizona Railway Museum';
require_once __DIR__ . '/../../assets/header.php';
?>

</div></div><!-- Close grid-container and page-content for full-width hero -->

<section class="arm-hero" style="margin-top: -4.5rem; padding: 2.5rem 0 2rem;">
    <div class="grid-container">
        <div class="grid-x grid-margin-x align-middle">
            <div class="small-12 cell">
                <h1 style="margin-bottom: 0.25rem;">Equipment Management</h1>
                <p class="lead" style="margin-bottom: 0;">View and manage equipment entries in the museum roster.</p>
            </div>
        </div>
    </div>
</section>

<div class="page-content">
<div class="grid-container" style="padding-top: 2rem; margin-bottom: 2rem;">

<div class="grid-x grid-margin-x">
    <div class="small-12 cell">
        <div class="card arm-card">
            <div class="card-section">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem;">
                    <h3 style="margin: 0;">Current Equipment</h3>
                    <?php if ($isAdmin): ?>
                        <a href="/admin/equipment/add.php" class="button primary small" style="border-radius: 8px; margin: 0;">Add Equipment</a>
                    <?php endif; ?>
                </div>

                <?php if (!$equipmentRows): ?>
                    <div class="callout secondary">
                        <p>No equipment records found yet.</p>
                    </div>
                <?php else: ?>
                    <div class="arm-card-table-wrapper">
                        <table class="hover" style="border-radius: 8px; overflow: hidden; border: 1px solid #e0e0e0;">
                            <thead>
                            <tr>
                                <th style="padding: 0.75rem;">Reporting Mark</th>
                                <th style="padding: 0.75rem;">Number</th>
                                <th style="padding: 0.75rem;">Type</th>
                                <th width="80" style="padding: 0.75rem;">Built</th>
                                <th style="padding: 0.75rem;">Status</th>
                                <?php if ($canViewPins): ?>
                                    <th width="100" style="padding: 0.75rem;">PIN</th>
                                <?php endif; ?>
                                <?php if ($isAdmin): ?>
                                    <th width="100" class="text-center" style="padding: 0.75rem;">Actions</th>
                                <?php endif; ?>
                            </tr>
                            </thead>
                            <tbody>
                            <?php foreach ($equipmentRows as $row): ?>
                                <tr>
                                    <td style="padding: 0.75rem;"><strong style="color: #1779ba;"><?php echo htmlspecialchars($row['reporting_mark'] ?? '', ENT_QUOTES, 'UTF-8'); ?></strong></td>
                                    <td style="padding: 0.75rem;"><?php echo htmlspecialchars($row['road_number'] ?? '', ENT_QUOTES, 'UTF-8'); ?></td>
                                    <td style="padding: 0.75rem;"><?php echo htmlspecialchars($row['equipment_type'] ?? '', ENT_QUOTES, 'UTF-8'); ?></td>
                                    <td style="padding: 0.75rem;"><?php echo htmlspecialchars($row['built_year'] ?? '', ENT_QUOTES, 'UTF-8'); ?></td>
                                    <td style="padding: 0.75rem;"><?php echo htmlspecialchars($row['status'] ?? '', ENT_QUOTES, 'UTF-8'); ?></td>
                                    <?php if ($canViewPins): ?>
                                        <td style="padding: 0.75rem;<?php if (!empty($row['pin'])): ?> cursor: pointer; user-select: none;<?php endif; ?>" <?php if (!empty($row['pin'])): ?>onclick="var t=this.querySelector('.pin-toggle'), v=this.querySelector('.pin-value'); if(t.style.display!=='none'){t.style.display='none';v.style.display='inline';}else{t.style.display='inline';v.style.display='none';}"<?php endif; ?>>
                                            <?php if (!empty($row['pin'])): ?>
                                                <span class="pin-toggle" style="color: #1779ba;">Show</span>
                                                <span class="pin-value" style="display: none; font-family: monospace;"><?php echo htmlspecialchars($row['pin'], ENT_QUOTES, 'UTF-8'); ?></span>
                                            <?php else: ?>
                                                <span style="color: #999;">—</span>
                                            <?php endif; ?>
                                        </td>
                                    <?php endif; ?>
                                    <?php if ($isAdmin): ?>
                                        <td class="text-center" style="padding: 0.75rem;">
                                            <a href="/admin/equipment/edit/<?php echo (int)$row['id']; ?>" style="color: #1779ba; text-decoration: none; font-weight: 500;">Edit</a>
                                        </td>
                                    <?php endif; ?>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../../assets/footer.php'; ?>
