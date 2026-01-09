<?php
$page_title = "Equipment Roster";
require_once __DIR__ . '/../assets/header.php';
require_once __DIR__ . '/../config/db.php';
$pdo = getDbConnection();

// Fetch equipment list grouped by category
$stmt = $pdo->query("
    SELECT
        id,
        reporting_mark,
        road_number,
        equipment_type,
        equipment_category,
        builder,
        built_year,
        status,
        pin
    FROM equipment
    ORDER BY
        FIELD(equipment_category, 'Locomotives', 'Passenger Cars', 'Mail/Baggage/Express', 'Freight Cars', 'MOW', 'Interurban'),
        reporting_mark,
        road_number
");

$allEquipment = $stmt->fetchAll();

// Group equipment by category
$equipmentByCategory = [];
foreach ($allEquipment as $item) {
    $category = $item['equipment_category'] ?? 'Miscellaneous';
    if (!isset($equipmentByCategory[$category])) {
        $equipmentByCategory[$category] = [];
    }
    $equipmentByCategory[$category][] = $item;
}

// Ensure categories are in the correct order
$orderedCategories = ['Locomotives', 'Passenger Cars', 'Mail/Baggage/Express', 'Freight Cars', 'MOW', 'Interurban'];
$equipment = [];
foreach ($orderedCategories as $category) {
    if (isset($equipmentByCategory[$category])) {
        $equipment[$category] = $equipmentByCategory[$category];
    }
}

// Background images for each category (alternating between available images)
$categoryBackgrounds = [
    'Locomotives' => '/assets/backgrounds/main.jpg',
    'Passenger Cars' => '/assets/backgrounds/photo-3.jpg',
    'Mail/Baggage/Express' => '/assets/backgrounds/main.jpg',
    'Freight Cars' => '/assets/backgrounds/photo-3.jpg',
    'MOW' => '/assets/backgrounds/main.jpg',
    'Interurban' => '/assets/backgrounds/photo-3.jpg'
];
?>
</div></div><!-- Close grid-container and page-content for full-width sections -->

<!-- Hero section with background image -->
<section class="arm-hero" style="margin-top: -4.5rem;">
    <div class="grid-container">
        <div class="grid-x grid-margin-x align-middle align-right">
            <div class="small-12 medium-8 cell text-right">
                <h1>Equipment Roster</h1>
                <p class="lead">Rolling stock and equipment currently on display in the museum yard</p>
            </div>
        </div>
    </div>
</section>

<!-- Category jump cards on white -->
<section style="background: #fff; padding: 1rem 0;">
    <div class="grid-container">
        <?php if (!empty($equipment)): ?>
            <div class="grid-x grid-margin-x small-up-2 medium-up-3 large-up-6" style="justify-content: center;">
                <?php foreach ($equipment as $category => $items): ?>
                    <?php
$displayCategory = ($category === 'Mail/Baggage/Express') ? 'Baggage/Express' : $category;
?>
<div class="cell">
                        <a href="#<?php echo strtolower(str_replace(' ', '-', $category)); ?>" class="arm-category-jump" style="text-decoration: none;">
                            <div class="card arm-card" style="height: 100%; text-align: center; padding: 0.75rem; margin-bottom: 0; transition: transform 0.2s, box-shadow 0.2s;">
                                <h4 style="font-size: 0.95rem; margin-bottom: 0.25rem; color: #1779ba;"><?php echo htmlspecialchars($displayCategory, ENT_QUOTES, 'UTF-8'); ?></h4>
                                <span style="font-size: 0.85rem; color: #666;"><?php echo count($items); ?> item<?php echo count($items) !== 1 ? 's' : ''; ?></span>
                            </div>
                        </a>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</section>

<?php if (empty($equipment)): ?>
<section style="background: #fff; padding: 2rem 0;">
    <div class="grid-container">
        <div class="grid-x grid-margin-x">
            <div class="small-12 cell">
                <div class="callout secondary">
                    <p>No equipment records found. Once entries are added in the admin dashboard, they'll appear here.</p>
                </div>
            </div>
        </div>
    </div>
</section>
<?php else: ?>
    <?php foreach ($equipment as $category => $items):
        $bgImage = $categoryBackgrounds[$category] ?? '/assets/backgrounds/main.jpg';
    ?>
    <!-- <?php echo htmlspecialchars($category); ?> section -->
    <section class="arm-links" style="
        background: linear-gradient(rgba(255,255,255,0.92), rgba(255,255,255,0.92)), url('<?php echo $bgImage; ?>') center/cover no-repeat fixed;
    " id="<?php echo strtolower(str_replace(' ', '-', $category)); ?>">
        <div class="grid-container">
            <div class="grid-x grid-margin-x">
                <div class="small-12 cell">
                    <div class="card arm-card">
                        <div class="card-section">
                            <h3><?php echo htmlspecialchars($category, ENT_QUOTES, 'UTF-8'); ?></h3>
                            <div class="arm-card-table-wrapper">
                                <table class="hover" style="border-radius: 8px; overflow: hidden; border: 1px solid #e0e0e0;">
                                    <thead>
                                    <tr>
                                        <th width="90" style="padding: 0.25rem 0.5rem;">Photo</th>
                                        <th style="padding: 0.75rem;">Company/Railroad</th>
                                        <th style="padding: 0.75rem;"><?php echo $category === 'Passenger Cars' ? 'Number/Name' : 'Number'; ?></th>
                                        <th style="padding: 0.75rem;">Type</th>
                                        <th style="padding: 0.75rem;">Builder</th>
                                        <th width="80" style="padding: 0.75rem;">Built</th>
                                        <th style="padding: 0.75rem;">Status</th>
                                        <?php if (isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'admin'): ?>
                                        <th width="100" style="padding: 0.75rem;">PIN</th>
                                        <?php endif; ?>
                                    </tr>
                                    </thead>
                                    <tbody>
                                    <?php foreach ($items as $row): ?>
                                        <tr style="cursor: pointer;" onclick="window.location='<?php echo (int)$row['id']; ?>/';">
                                            <td style="padding: 0.25rem 0.5rem;">
                                                <?php
                                                $thumbPath = __DIR__ . '/../images/equipment/' . $row['id'] . '/thumb.jpg';
                                                $thumbWebPath = '/images/equipment/' . $row['id'] . '/thumb.jpg';
                                                if (file_exists($thumbPath)):
                                                ?>
                                                    <img src="<?php echo $thumbWebPath; ?>"
                                                         alt="<?php echo htmlspecialchars($row['reporting_mark'] . ' ' . $row['road_number']); ?>"
                                                         style="width: 80px; height: 80px; object-fit: cover; border-radius: 4px; border: 2px solid #1779ba;">
                                                <?php else: ?>
                                                    <div style="width: 80px; height: 80px; background: #e6e6e6; border-radius: 4px; display: flex; align-items: center; justify-content: center; font-size: 1.75rem;">
                                                        📷
                                                    </div>
                                                <?php endif; ?>
                                            </td>
                                            <td style="padding: 0.75rem;"><strong style="color: #1779ba;"><?php echo htmlspecialchars($row['reporting_mark'] ?? '', ENT_QUOTES, 'UTF-8'); ?></strong></td>
                                            <td style="padding: 0.75rem;"><?php echo htmlspecialchars($row['road_number'] ?? '', ENT_QUOTES, 'UTF-8'); ?></td>
                                            <td style="padding: 0.75rem;"><?php echo htmlspecialchars($row['equipment_type'] ?? '', ENT_QUOTES, 'UTF-8'); ?></td>
                                            <td style="padding: 0.75rem;"><?php echo htmlspecialchars($row['builder'] ?? '', ENT_QUOTES, 'UTF-8'); ?></td>
                                            <td style="padding: 0.75rem;"><?php echo htmlspecialchars($row['built_year'] ?? '', ENT_QUOTES, 'UTF-8'); ?></td>
                                            <td style="padding: 0.75rem;"><?php echo htmlspecialchars($row['status'] ?? '', ENT_QUOTES, 'UTF-8'); ?></td>
                                            <?php if (isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'admin'): ?>
                                            <td style="padding: 0.75rem;<?php if (!empty($row['pin'])): ?> cursor: pointer; user-select: none;<?php endif; ?>" onclick="event.stopPropagation();<?php if (!empty($row['pin'])): ?> var t=this.querySelector('.pin-toggle'), v=this.querySelector('.pin-value'); if(t.style.display!=='none'){t.style.display='none';v.style.display='inline';}else{t.style.display='inline';v.style.display='none';}"<?php endif; ?>>
                                                <?php if (!empty($row['pin'])): ?>
                                                    <span class="pin-toggle" style="color: #1779ba;">Show</span>
                                                    <span class="pin-value" style="display: none; font-family: monospace;"><?php echo htmlspecialchars($row['pin'], ENT_QUOTES, 'UTF-8'); ?></span>
                                                <?php else: ?>
                                                    <span style="color: #999;">—</span>
                                                <?php endif; ?>
                                            </td>
                                            <?php endif; ?>
                                        </tr>
                                    <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>
    <?php endforeach; ?>
<?php endif; ?>

<!-- CTA section -->
<section class="arm-hero">
    <div class="grid-container">
        <div class="grid-x grid-margin-x align-center text-center">
            <div class="small-12 medium-10 large-8 cell">
                <h2>Help Preserve History</h2>
                <p class="lead">Your support helps us restore and maintain these historic pieces of Arizona's railroad heritage.</p>
                <p style="margin-top: 1.5rem;">
                    <a href="/donations" class="button large" style="border-radius: 8px; background: #fff; color: #1779ba;">
                        Make a Donation
                    </a>
                    <a href="/membership" class="button large secondary" style="border-radius: 8px;">
                        Become a Member
                    </a>
                </p>
            </div>
        </div>
    </div>
</section>

<div class="page-content">
<div class="grid-container" style="padding-top: 2.5rem; margin-bottom: -2.25rem;">

<?php require_once __DIR__ . '/../assets/footer.php'; ?>
