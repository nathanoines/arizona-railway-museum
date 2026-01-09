<?php
/**
 * Admin Interface: View Single Membership Application
 *
 * Displays detailed information for a single application
 * Allows admins to approve or reject the application
 */

declare(strict_types=1);

session_start();
require_once __DIR__ . '/../../config/db.php';

// Helper function to format phone numbers
function formatPhone($phone) {
    if (empty($phone)) return 'Not provided';
    $phone = preg_replace('/\D/', '', $phone);
    if (strlen($phone) == 10) {
        return '(' . substr($phone, 0, 3) . ') ' . substr($phone, 3, 3) . '-' . substr($phone, 6);
    }
    return $phone;
}

// Check if user is logged in as admin
if (!isset($_SESSION['user_id'])) {
    header('Location: /login.php');
    exit;
}

$userRole = $_SESSION['user_role'] ?? 'user';
if ($userRole !== 'admin') {
    header('Location: /members/');
    exit;
}

// Validate application ID
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header('Location: /admin/membership/');
    exit;
}

$application_id = (int)$_GET['id'];

try {
    $pdo = getDbConnection();

    // Fetch the application with current member information
    $sql = "SELECT 
                ma.*,
                m.first_name,
                m.last_name,
                m.email as member_email,
                m.membership_status,
                m.membership_expires_at
            FROM membership_applications ma
            LEFT JOIN members m ON ma.member_id = m.id
            WHERE ma.id = :id 
            LIMIT 1";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([':id' => $application_id]);
    $app = $stmt->fetch();

    if (!$app) {
        $_SESSION['error_message'] = 'Application not found.';
        header('Location: /admin/membership/');
        exit;
    }
    
    // Use current member data if available, otherwise fall back to application data
    if ($app['member_id']) {
        $app['display_name'] = trim($app['first_name'] . ' ' . $app['last_name']);
        $app['display_email'] = $app['member_email'] ?: $app['email'];
    } else {
        $app['display_name'] = $app['name'];
        $app['display_email'] = $app['email'];
    }

} catch (PDOException $e) {
    error_log('Error loading application: ' . $e->getMessage());
    $_SESSION['error_message'] = 'Database error occurred.';
    header('Location: /admin/membership/');
    exit;
}

$page_title = "Application for " . $app['name'] . " | Arizona Railway Museum";
require_once __DIR__ . '/../../assets/header.php';
?>

</div></div><!-- Close grid-container and page-content for full-width hero -->

<section class="arm-hero" style="margin-top: -4.5rem; padding: 2.5rem 0 2rem;">
    <div class="grid-container">
        <div class="grid-x grid-margin-x align-middle">
            <div class="small-12 cell">
                <h1 style="margin-bottom: 0.25rem;">Review Application</h1>
                <p class="lead" style="margin-bottom: 0;">
                    <?php echo htmlspecialchars($app['display_name'], ENT_QUOTES, 'UTF-8'); ?>
                    <span style="opacity: 0.7;">&middot;</span>
                    <span style="opacity: 0.7;">#<?php echo $app['id']; ?></span>
                    <span style="opacity: 0.7;">&middot;</span>
                    <span style="text-transform: capitalize;"><?php echo htmlspecialchars($app['status'], ENT_QUOTES, 'UTF-8'); ?></span>
                </p>
            </div>
        </div>
    </div>
</section>

<div class="page-content">
<div class="grid-container" style="padding-top: 2rem; margin-bottom: 2rem;">

<div class="grid-x grid-margin-x">
    <div class="small-12 cell">
        <?php if (isset($_SESSION['success_message'])): ?>
            <div class="callout success">
                <?php
                echo htmlspecialchars($_SESSION['success_message'], ENT_QUOTES, 'UTF-8');
                unset($_SESSION['success_message']);
                ?>
            </div>
        <?php endif; ?>

        <?php if (isset($_SESSION['error_message'])): ?>
            <div class="callout alert">
                <?php
                echo htmlspecialchars($_SESSION['error_message'], ENT_QUOTES, 'UTF-8');
                unset($_SESSION['error_message']);
                ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<div class="grid-x grid-margin-x">
    <!-- Left Column: Application Details -->
    <div class="small-12 medium-8 cell">
        <div class="card arm-card">
            <div class="card-section">
                <h3 style="margin-top: 0;">
                    <?php echo htmlspecialchars($app['display_name'], ENT_QUOTES, 'UTF-8'); ?>
                    <span class="label <?php
                        echo $app['status'] === 'pending' ? 'warning' :
                            ($app['status'] === 'approved' ? 'success' : 'alert');
                    ?>" style="margin-left: 0.5rem; font-size: 0.875rem; vertical-align: middle;">
                        <?php echo strtoupper($app['status']); ?>
                    </span>
                    <?php if ($app['member_id']): ?>
                        <?php
                        // Fetch the member's current role
                        $roleStmt = $pdo->prepare("SELECT user_role FROM members WHERE id = :id LIMIT 1");
                        $roleStmt->execute([':id' => $app['member_id']]);
                        $memberRole = $roleStmt->fetchColumn();
                        
                        if ($memberRole):
                            $roleColors = [
                                'admin' => 'alert',
                                'board' => 'warning',
                                'user' => 'secondary'
                            ];
                            $roleColor = $roleColors[$memberRole] ?? 'secondary';
                        ?>
                        <span class="label <?php echo $roleColor; ?>" style="margin-left: 0.5rem; font-size: 0.875rem; vertical-align: middle;">
                            <?php echo strtoupper($memberRole); ?>
                        </span>
                        <?php endif; ?>
                    <?php endif; ?>
                </h3>

                <div style="margin: 1.5rem 0;">
                    <h4>Contact Information</h4>
                    <p style="margin: 0.5rem 0;">
                        <strong>Email:</strong> <a href="mailto:<?php echo htmlspecialchars($app['display_email'], ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($app['display_email'], ENT_QUOTES, 'UTF-8'); ?></a><br>
                        <strong>Phone:</strong> <?php echo formatPhone($app['phone']); ?>
                    </p>
                    <p style="margin: 0.5rem 0;">
                        <strong>Address:</strong><br>
                        <?php echo htmlspecialchars($app['address'], ENT_QUOTES, 'UTF-8'); ?><br>
                        <?php echo htmlspecialchars($app['city'], ENT_QUOTES, 'UTF-8'); ?>, 
                        <?php echo htmlspecialchars($app['state'], ENT_QUOTES, 'UTF-8'); ?> 
                        <?php echo htmlspecialchars($app['zip'], ENT_QUOTES, 'UTF-8'); ?>
                    </p>
                </div>

                <div style="margin: 1.5rem 0;">
                    <h4>Membership Information</h4>
                    <p style="margin: 0.5rem 0;">
                        <strong>Membership Level:</strong>
                        <?php
                        $level_display = str_replace('_', ' ', $app['membership_level']);
                        echo htmlspecialchars(ucwords($level_display), ENT_QUOTES, 'UTF-8');
                        ?>
                    </p>
                    <?php if ($app['sustaining_amount']): ?>
                        <p style="margin: 0.5rem 0;">
                            <strong>Sustaining Amount:</strong> $<?php echo number_format((float)$app['sustaining_amount'], 2); ?>
                        </p>
                    <?php endif; ?>
                    <?php if ($app['corporate_amount']): ?>
                        <p style="margin: 0.5rem 0;">
                            <strong>Corporate Amount:</strong> $<?php echo number_format((float)$app['corporate_amount'], 2); ?>
                        </p>
                    <?php endif; ?>
                </div>

                <?php if (!empty($app['interests'])): ?>
                    <div style="margin: 1.5rem 0;">
                        <h4>Areas of Interest</h4>
                        <p style="margin: 0.5rem 0;">
                            <?php
                            // Try to decode as JSON first (legacy format), otherwise treat as comma-separated string
                            $interests_raw = $app['interests'];
                            $interests_array = json_decode($interests_raw, true);
                            
                            if (!is_array($interests_array)) {
                                // Not JSON, treat as comma-separated string
                                $interests_array = array_map('trim', explode(',', $interests_raw));
                            }
                            
                            if (!empty($interests_array)) {
                                $interest_labels = [
                                    'restoration' => 'Equipment Restoration',
                                    'curatorial' => 'Curatorial/Archives',
                                    'events' => 'Events & Tours',
                                    'maintenance' => 'Facility Maintenance',
                                    'fundraising' => 'Fundraising',
                                    'gift_shop' => 'Gift Shop'
                                ];
                                
                                $formatted_interests = [];
                                foreach ($interests_array as $interest) {
                                    $formatted_interests[] = $interest_labels[$interest] ?? ucfirst($interest);
                                }
                                echo htmlspecialchars(implode(', ', $formatted_interests), ENT_QUOTES, 'UTF-8');
                            } else {
                                echo 'None specified';
                            }
                            ?>
                        </p>
                    </div>
                <?php endif; ?>

                <?php if (!empty($app['comments'])): ?>
                    <div style="margin: 1.5rem 0;">
                        <h4>Comments</h4>
                        <div style="padding: 1rem; background: #f8f9fa; border-radius: 5px; border-left: 4px solid #1779ba;">
                            <?php echo nl2br(htmlspecialchars($app['comments'], ENT_QUOTES, 'UTF-8')); ?>
                        </div>
                    </div>
                <?php endif; ?>

                <div style="margin: 1.5rem 0; padding: 1rem; background: #f8f9fa; border-radius: 5px;">
                    <h4 style="margin-top: 0;">Application Details</h4>
                    <p style="margin: 0.25rem 0; font-size: 0.9rem;">
                        <strong>Application ID:</strong> <?php echo $app['id']; ?><br>
                        <strong>Submitted:</strong> <?php echo date('F j, Y \a\t g:i A', strtotime($app['created_at'])); ?><br>
                        <strong>Last Updated:</strong> <?php echo date('F j, Y \a\t g:i A', strtotime($app['updated_at'])); ?>
                        <?php if ($app['member_id']): ?>
                            <br><strong>Linked to Member ID:</strong> 
                            <a href="/admin/membership/edit/<?php echo $app['member_id']; ?>">
                                #<?php echo $app['member_id']; ?>
                            </a>
                        <?php endif; ?>
                    </p>
                </div>
            </div>
        </div>
    </div>

    <!-- Right Column: Actions -->
    <div class="small-12 medium-4 cell">
        <?php if ($app['status'] === 'pending'): ?>
            <div class="card arm-card" style="background: #d4edda;">
                <div class="card-section">
                    <h4 style="margin-top: 0; color: #155724;">Approve Application</h4>
                    
                    <?php if ($app['member_id']): ?>
                        <p style="color: #155724; font-size: 0.9rem; margin-bottom: 1rem;">
                            <strong>Member Account:</strong> #<?php echo $app['member_id']; ?><br>
                            <small>This application is linked to an existing member account.</small>
                        </p>
                    <?php endif; ?>
                    
                    <form method="post" action="../handlers/approve.php">
                        <input type="hidden" name="application_id" value="<?php echo $app['id']; ?>">

                        <p style="font-size: 0.9rem; color: #155724; margin-bottom: 1rem;">
                            The membership term will be set automatically based on the membership level selected in the application.
                        </p>

                        <button type="submit" class="button success expanded" style="border-radius: 8px;">
                            ✓ Approve & Activate Membership
                        </button>
                    </form>
                </div>
            </div>

            <div class="card arm-card" style="background: #f8d7da; margin-top: 1rem;">
                <div class="card-section">
                    <h4 style="margin-top: 0; color: #721c24;">Reject Application</h4>
                    <p style="font-size: 0.9rem; color: #721c24;">
                        This will mark the application as rejected. This action cannot be undone.
                    </p>
                    <form method="post" action="../handlers/reject.php"
                          onsubmit="return confirm('Are you sure you want to reject this application? This cannot be undone.');">
                        <input type="hidden" name="application_id" value="<?php echo $app['id']; ?>">
                        <button type="submit" class="button alert expanded" style="border-radius: 8px;">
                            ✗ Reject Application
                        </button>
                    </form>
                </div>
            </div>

        <?php elseif ($app['status'] === 'approved'): ?>
            <div class="card arm-card" style="background: #d4edda;">
                <div class="card-section">
                    <h4 style="margin-top: 0; color: #155724;">✓ Application Approved</h4>
                    <p style="color: #155724; margin: 0;">
                        This application has been approved and the membership has been activated.
                    </p>
                </div>
            </div>

        <?php else: ?>
            <div class="card arm-card" style="background: #f8d7da;">
                <div class="card-section">
                    <h4 style="margin-top: 0; color: #721c24;">✗ Application Rejected</h4>
                    <p style="color: #721c24; font-size: 0.9rem;">
                        This application has been rejected. You can restore it to pending status if needed.
                    </p>
                    <form method="post" action="../handlers/unreject.php"
                          onsubmit="return confirm('Restore this application to pending status?');">
                        <input type="hidden" name="application_id" value="<?php echo $app['id']; ?>">
                        <button type="submit" class="button warning expanded" style="border-radius: 8px;">
                            ↻ Restore to Pending
                        </button>
                    </form>
                </div>
            </div>
        <?php endif; ?>

        <?php if (!$app['member_id']): ?>
            <div class="card arm-card" style="margin-top: 1rem; background: #fff3cd; overflow: visible;">
                <div class="card-section" style="overflow: visible;">
                    <h5 style="margin-top: 0; color: #856404;">Link to Existing Member</h5>
                    <p style="font-size: 0.85rem; color: #856404; margin-bottom: 1rem;">
                        This application is not linked to a member account. Search for an existing member to link it.
                    </p>

                    <div style="position: relative;">
                        <input type="text"
                               id="member-search"
                               placeholder="Search by name or email..."
                               autocomplete="off"
                               style="margin-bottom: 0;">
                        <div id="member-search-results" style="display: none; position: absolute; top: 100%; left: 0; right: 0; background: white; border: 1px solid #ccc; border-top: none; max-height: 250px; overflow-y: auto; z-index: 1000; box-shadow: 0 4px 6px rgba(0,0,0,0.1); border-radius: 0 0 5px 5px;"></div>
                    </div>

                    <div id="selected-member" style="display: none; margin-top: 1rem; padding: 0.75rem; background: #fff; border: 1px solid #ddd; border-radius: 5px;">
                        <div style="display: flex; justify-content: space-between; align-items: center;">
                            <div>
                                <strong id="selected-member-name"></strong><br>
                                <small id="selected-member-email" style="color: #666;"></small>
                            </div>
                            <button type="button" id="clear-selection" class="button small alert" style="margin: 0; border-radius: 5px; padding: 0.25rem 0.5rem;">Clear</button>
                        </div>
                    </div>

                    <form method="post" action="/admin/membership/handlers/link_member.php" id="link-form" style="display: none; margin-top: 1rem;">
                        <input type="hidden" name="application_id" value="<?php echo $app['id']; ?>">
                        <input type="hidden" name="member_id" id="link-member-id" value="">
                        <button type="submit" class="button warning expanded" style="border-radius: 8px;">
                            Link Application to This Member
                        </button>
                    </form>
                </div>
            </div>

            <script>
            (function() {
                const searchInput = document.getElementById('member-search');
                const resultsDiv = document.getElementById('member-search-results');
                const selectedDiv = document.getElementById('selected-member');
                const selectedName = document.getElementById('selected-member-name');
                const selectedEmail = document.getElementById('selected-member-email');
                const clearBtn = document.getElementById('clear-selection');
                const linkForm = document.getElementById('link-form');
                const linkMemberId = document.getElementById('link-member-id');

                let searchTimeout;

                searchInput.addEventListener('input', function() {
                    clearTimeout(searchTimeout);
                    const query = this.value.trim();

                    if (query.length < 2) {
                        resultsDiv.style.display = 'none';
                        return;
                    }

                    searchTimeout = setTimeout(function() {
                        fetch('/admin/api/search_members.php?q=' + encodeURIComponent(query))
                            .then(response => response.json())
                            .then(members => {
                                if (members.length === 0) {
                                    resultsDiv.innerHTML = '<div style="padding: 0.75rem; color: #666;">No members found</div>';
                                } else {
                                    resultsDiv.innerHTML = members.map(function(m) {
                                        const statusColor = m.status === 'inactive' ? '#dc3545' : '#28a745';
                                        const expiresText = m.expires ? 'Expires: ' + m.expires : 'No expiration';
                                        return '<div class="member-result" data-id="' + m.id + '" data-name="' + escapeHtml(m.name) + '" data-email="' + escapeHtml(m.email) + '" style="padding: 0.75rem; border-bottom: 1px solid #eee; cursor: pointer;">' +
                                            '<div style="display: flex; justify-content: space-between; align-items: center;">' +
                                                '<div>' +
                                                    '<strong>' + escapeHtml(m.name) + '</strong><br>' +
                                                    '<small style="color: #666;">' + escapeHtml(m.email) + '</small>' +
                                                '</div>' +
                                                '<div style="text-align: right;">' +
                                                    '<span style="color: ' + statusColor + '; font-size: 0.8rem;">' + escapeHtml(m.status) + '</span><br>' +
                                                    '<small style="color: #999;">' + expiresText + '</small>' +
                                                '</div>' +
                                            '</div>' +
                                        '</div>';
                                    }).join('');

                                    // Add hover effect
                                    resultsDiv.querySelectorAll('.member-result').forEach(function(el) {
                                        el.addEventListener('mouseenter', function() {
                                            this.style.background = '#f0f0f0';
                                        });
                                        el.addEventListener('mouseleave', function() {
                                            this.style.background = 'white';
                                        });
                                        el.addEventListener('click', function() {
                                            selectMember(this.dataset.id, this.dataset.name, this.dataset.email);
                                        });
                                    });
                                }
                                resultsDiv.style.display = 'block';
                            })
                            .catch(function(err) {
                                console.error('Search error:', err);
                                resultsDiv.innerHTML = '<div style="padding: 0.75rem; color: #dc3545;">Error searching members</div>';
                                resultsDiv.style.display = 'block';
                            });
                    }, 300);
                });

                function selectMember(id, name, email) {
                    linkMemberId.value = id;
                    selectedName.textContent = name + ' (ID: ' + id + ')';
                    selectedEmail.textContent = email;
                    selectedDiv.style.display = 'block';
                    linkForm.style.display = 'block';
                    resultsDiv.style.display = 'none';
                    searchInput.value = '';
                }

                clearBtn.addEventListener('click', function() {
                    linkMemberId.value = '';
                    selectedDiv.style.display = 'none';
                    linkForm.style.display = 'none';
                });

                // Close results when clicking outside
                document.addEventListener('click', function(e) {
                    if (!searchInput.contains(e.target) && !resultsDiv.contains(e.target)) {
                        resultsDiv.style.display = 'none';
                    }
                });

                function escapeHtml(text) {
                    const div = document.createElement('div');
                    div.textContent = text;
                    return div.innerHTML;
                }
            })();
            </script>
        <?php endif; ?>

        <div class="card arm-card" style="margin-top: 1rem;">
            <div class="card-section">
                <h5 style="margin-top: 0;">Quick Actions</h5>
                <?php if ($app['member_id']): ?>
                    <a href="/admin/membership/edit/<?php echo $app['member_id']; ?>" class="button primary expanded small" style="border-radius: 8px;">
                        Edit Member Status
                    </a>
                <?php endif; ?>
                <a href="/admin/membership/" class="button secondary expanded small" style="border-radius: 8px;">
                    Back to All Applications
                </a>
                
                <form method="post" action="../handlers/delete.php" style="margin-top: 1rem;"
                      onsubmit="return confirm('Are you sure you want to permanently delete this application? This action cannot be undone.');">
                    <input type="hidden" name="application_id" value="<?php echo $app['id']; ?>">
                    <button type="submit" class="button alert expanded small" style="border-radius: 8px;">
                        🗑 Delete Application
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../../assets/footer.php'; ?>
