<?php
/**
 * Admin Interface: Title Management
 *
 * Allows admins to create, edit, and manage organizational titles
 */

session_start();
require_once __DIR__ . '/../../config/db.php';
$pdo = getDbConnection();

// Admin gate
if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'admin') {
    header('Location: /login.php');
    exit;
}

// Check for session messages
$successMessage = $_SESSION['success_message'] ?? null;
$errorMessage = $_SESSION['error_message'] ?? null;
unset($_SESSION['success_message'], $_SESSION['error_message']);

// Fetch all titles with assignment counts
$stmt = $pdo->query("
    SELECT
        t.id,
        t.title_name,
        t.display_order,
        t.is_active,
        t.created_at,
        COUNT(mta.id) as assignment_count
    FROM member_titles t
    LEFT JOIN member_title_assignments mta ON t.id = mta.title_id
    GROUP BY t.id
    ORDER BY t.display_order ASC, t.title_name ASC
");
$titles = $stmt->fetchAll();

// Fetch members with titles for display
$membersWithTitles = $pdo->query("
    SELECT
        m.id,
        m.first_name,
        m.last_name,
        t.title_name,
        t.display_order
    FROM members m
    INNER JOIN member_title_assignments mta ON m.id = mta.member_id
    INNER JOIN member_titles t ON mta.title_id = t.id
    WHERE t.is_active = 1
    ORDER BY t.display_order ASC, m.last_name ASC
")->fetchAll();

$page_title = 'Title Management | Arizona Railway Museum';
require_once __DIR__ . '/../../assets/header.php';
?>

</div></div><!-- Close grid-container and page-content for full-width hero -->

<section class="arm-hero" style="margin-top: -4.5rem; padding: 2.5rem 0 2rem;">
    <div class="grid-container">
        <div class="grid-x grid-margin-x align-middle">
            <div class="small-12 cell">
                <h1 style="margin-bottom: 0.25rem;">Title Management</h1>
                <p class="lead" style="margin-bottom: 0;">Manage organizational titles like President, Vice President, etc.</p>
            </div>
        </div>
    </div>
</section>

<div class="page-content">
<div class="grid-container" style="padding-top: 2rem; margin-bottom: 2rem;">

<?php if ($successMessage): ?>
    <div class="grid-x grid-margin-x">
        <div class="small-12 cell">
            <div class="callout success">
                <?php echo htmlspecialchars($successMessage, ENT_QUOTES, 'UTF-8'); ?>
            </div>
        </div>
    </div>
<?php endif; ?>

<?php if ($errorMessage): ?>
    <div class="grid-x grid-margin-x">
        <div class="small-12 cell">
            <div class="callout alert">
                <?php echo htmlspecialchars($errorMessage, ENT_QUOTES, 'UTF-8'); ?>
            </div>
        </div>
    </div>
<?php endif; ?>

<div class="grid-x grid-margin-x">
    <!-- Available Titles -->
    <div class="small-12 medium-7 cell">
        <div class="card arm-card">
            <div class="card-section">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem;">
                    <h3 style="margin: 0;">Available Titles</h3>
                    <button type="button" class="button primary small" style="border-radius: 8px; margin: 0;" data-open="add-title-modal">
                        Add Title
                    </button>
                </div>

                <?php if (empty($titles)): ?>
                    <div class="callout secondary">
                        <p>No titles have been created yet. Add your first title to get started.</p>
                    </div>
                <?php else: ?>
                    <div class="arm-card-table-wrapper">
                        <table class="hover" style="border-radius: 8px; overflow: hidden; border: 1px solid #e0e0e0;">
                            <thead>
                            <tr>
                                <th style="padding: 0.75rem; width: 50px;">Order</th>
                                <th style="padding: 0.75rem;">Title Name</th>
                                <th class="text-center" style="padding: 0.75rem;">Assigned</th>
                                <th class="text-center" style="padding: 0.75rem;">Status</th>
                                <th class="text-center" style="padding: 0.75rem;">Actions</th>
                            </tr>
                            </thead>
                            <tbody>
                            <?php foreach ($titles as $title): ?>
                                <tr>
                                    <td style="padding: 0.75rem; text-align: center; color: #888;"><?php echo (int)$title['display_order']; ?></td>
                                    <td style="padding: 0.75rem;">
                                        <strong><?php echo htmlspecialchars($title['title_name'], ENT_QUOTES, 'UTF-8'); ?></strong>
                                    </td>
                                    <td class="text-center" style="padding: 0.75rem;">
                                        <?php echo (int)$title['assignment_count']; ?> member<?php echo $title['assignment_count'] != 1 ? 's' : ''; ?>
                                    </td>
                                    <td class="text-center" style="padding: 0.75rem;">
                                        <?php if ($title['is_active']): ?>
                                            <span class="label success">Active</span>
                                        <?php else: ?>
                                            <span class="label secondary">Inactive</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-center" style="padding: 0.75rem;">
                                        <a href="#" class="edit-title"
                                           data-id="<?php echo (int)$title['id']; ?>"
                                           data-name="<?php echo htmlspecialchars($title['title_name'], ENT_QUOTES, 'UTF-8'); ?>"
                                           data-order="<?php echo (int)$title['display_order']; ?>"
                                           data-active="<?php echo $title['is_active'] ? '1' : '0'; ?>"
                                           style="color: #1779ba; text-decoration: none; font-weight: 500;">Edit</a>
                                        <?php if ($title['assignment_count'] == 0): ?>
                                            <span style="color: #ccc; margin: 0 0.25rem;">|</span>
                                            <a href="/admin/titles/handlers/delete.php?id=<?php echo (int)$title['id']; ?>"
                                               style="color: #cc4b37; text-decoration: none; font-weight: 500;"
                                               onclick="return confirm('Are you sure you want to delete this title?');">Delete</a>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Current Title Holders -->
    <div class="small-12 medium-5 cell">
        <div class="card arm-card">
            <div class="card-section">
                <h3 style="margin-top: 0;">Current Title Holders</h3>

                <?php if (empty($membersWithTitles)): ?>
                    <div class="callout secondary">
                        <p>No members have been assigned titles yet. Assign titles through the member edit page.</p>
                    </div>
                <?php else: ?>
                    <div style="max-height: 400px; overflow-y: auto;">
                        <?php
                        $currentTitle = '';
                        foreach ($membersWithTitles as $member):
                            if ($currentTitle !== $member['title_name']):
                                if ($currentTitle !== '') echo '</ul>';
                                $currentTitle = $member['title_name'];
                        ?>
                            <h5 style="margin-top: 1rem; margin-bottom: 0.5rem; color: #1779ba;">
                                <?php echo htmlspecialchars($member['title_name'], ENT_QUOTES, 'UTF-8'); ?>
                            </h5>
                            <ul style="margin-bottom: 0; margin-left: 0; list-style: none;">
                        <?php endif; ?>
                            <li style="padding: 0.25rem 0;">
                                <a href="/admin/membership/edit.php?id=<?php echo (int)$member['id']; ?>" style="color: #333;">
                                    <?php echo htmlspecialchars($member['first_name'] . ' ' . $member['last_name'], ENT_QUOTES, 'UTF-8'); ?>
                                </a>
                            </li>
                        <?php endforeach; ?>
                        <?php if (!empty($membersWithTitles)) echo '</ul>'; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <div class="callout" style="border-radius: 8px; margin-top: 1rem;">
            <h5 style="margin-top: 0;">Assigning Titles</h5>
            <p style="font-size: 0.9rem; margin-bottom: 0;">
                To assign titles to members, go to the
                <a href="/admin/membership/">Membership Management</a> page and edit a member's profile.
            </p>
        </div>
    </div>
</div>

<!-- Add Title Modal -->
<div class="reveal" id="add-title-modal" data-reveal>
    <h4>Add New Title</h4>
    <form method="post" action="/admin/titles/handlers/add.php">
        <label>Title Name *
            <input type="text" name="title_name" required placeholder="e.g. Vice President of Marketing" maxlength="100">
        </label>
        <label>Display Order
            <input type="number" name="display_order" value="0" min="0" max="999">
            <small>Lower numbers appear first (0 = highest priority)</small>
        </label>
        <div style="margin-top: 1.5rem;">
            <button type="submit" class="button primary" style="border-radius: 8px;">Add Title</button>
            <button type="button" class="button secondary" data-close style="border-radius: 8px;">Cancel</button>
        </div>
    </form>
    <button class="close-button" data-close aria-label="Close modal" type="button">
        <span aria-hidden="true">&times;</span>
    </button>
</div>

<!-- Edit Title Modal -->
<div class="reveal" id="edit-title-modal" data-reveal>
    <h4>Edit Title</h4>
    <form method="post" action="/admin/titles/handlers/update.php">
        <input type="hidden" name="title_id" id="edit-title-id">
        <label>Title Name *
            <input type="text" name="title_name" id="edit-title-name" required placeholder="e.g. Vice President of Marketing" maxlength="100">
        </label>
        <label>Display Order
            <input type="number" name="display_order" id="edit-display-order" value="0" min="0" max="999">
            <small>Lower numbers appear first (0 = highest priority)</small>
        </label>
        <label>
            <input type="checkbox" name="is_active" id="edit-is-active" value="1">
            Title is active (can be assigned to members)
        </label>
        <div style="margin-top: 1.5rem;">
            <button type="submit" class="button primary" style="border-radius: 8px;">Save Changes</button>
            <button type="button" class="button secondary" data-close style="border-radius: 8px;">Cancel</button>
        </div>
    </form>
    <button class="close-button" data-close aria-label="Close modal" type="button">
        <span aria-hidden="true">&times;</span>
    </button>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Handle edit title clicks
    document.querySelectorAll('.edit-title').forEach(function(link) {
        link.addEventListener('click', function(e) {
            e.preventDefault();
            document.getElementById('edit-title-id').value = this.dataset.id;
            document.getElementById('edit-title-name').value = this.dataset.name;
            document.getElementById('edit-display-order').value = this.dataset.order;
            document.getElementById('edit-is-active').checked = this.dataset.active === '1';

            // Open modal using Foundation
            var modal = new Foundation.Reveal($('#edit-title-modal'));
            modal.open();
        });
    });
});
</script>

<?php require_once __DIR__ . '/../../assets/footer.php'; ?>
