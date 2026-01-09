<?php
/**
 * Admin Interface: Manual Entry for Paper Applications
 *
 * Allows admins to manually enter membership applications received via paper/mail
 */

declare(strict_types=1);

session_start();
require_once __DIR__ . '/../../config/db.php';

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

// Retrieve any form errors or previous data from session
$errors = $_SESSION['form_errors'] ?? [];
$form_data = $_SESSION['form_data'] ?? [];

// Clear session data after retrieving
unset($_SESSION['form_errors']);
unset($_SESSION['form_data']);

// Helper function to check if an interest was previously selected
function isInterestChecked($interest, $form_data) {
    if (isset($form_data['interests']) && is_array($form_data['interests'])) {
        return in_array($interest, $form_data['interests']) ? 'checked' : '';
    }
    return '';
}

$page_title = "Add Paper Application | Arizona Railway Museum";
require_once __DIR__ . '/../../assets/header.php';
?>

</div></div><!-- Close grid-container and page-content for full-width hero -->

<section class="arm-hero" style="margin-top: -4.5rem; padding: 2.5rem 0 2rem;">
    <div class="grid-container">
        <div class="grid-x grid-margin-x align-middle">
            <div class="small-12 cell">
                <h1 style="margin-bottom: 0.25rem;">Add Paper Application</h1>
                <p class="lead" style="margin-bottom: 0;">Manually enter membership applications received via mail or in person.</p>
            </div>
        </div>
    </div>
</section>

<div class="page-content">
<div class="grid-container" style="padding-top: 2rem; margin-bottom: 2rem;">

<div class="grid-x grid-margin-x">
    <div class="small-12 cell">
        <?php if (!empty($errors)): ?>
            <div class="callout alert" style="margin-top: 1rem;">
                <h5>Please correct the following errors:</h5>
                <ul style="margin-bottom: 0;">
                    <?php foreach ($errors as $error): ?>
                        <li><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>
    </div>
</div>

<div class="grid-x grid-margin-x">
    <div class="small-12 medium-8 cell">
        <div class="card arm-card">
            <div class="card-section">
                <form method="post" action="handlers/submit_manual.php">
                    <fieldset>
                        <legend>Personal Information</legend>
                        
                        <label>Name *
                            <input type="text" name="name" required placeholder="First and Last Name"
                                   value="<?php echo htmlspecialchars($form_data['name'] ?? '', ENT_QUOTES, 'UTF-8'); ?>">
                        </label>
                        
                        <label>Email *
                            <input type="email" name="email" required
                                   value="<?php echo htmlspecialchars($form_data['email'] ?? '', ENT_QUOTES, 'UTF-8'); ?>">
                        </label>

                        <label>Phone
                            <input type="tel" name="phone" id="phone" placeholder="(480) 555-1234"
                                   value="<?php echo htmlspecialchars($form_data['phone'] ?? '', ENT_QUOTES, 'UTF-8'); ?>">
                        </label>

                        <label>Address *
                            <input type="text" name="address" required placeholder="Street Address"
                                   value="<?php echo htmlspecialchars($form_data['address'] ?? '', ENT_QUOTES, 'UTF-8'); ?>">
                        </label>
                        
                        <div class="grid-x grid-margin-x">
                            <div class="small-12 medium-6 cell">
                                <label>City *
                                    <input type="text" name="city" required placeholder="Chandler"
                                           value="<?php echo htmlspecialchars($form_data['city'] ?? '', ENT_QUOTES, 'UTF-8'); ?>">
                                </label>
                            </div>
                            <div class="small-12 medium-3 cell">
                                <label>State *
                                    <input type="text" name="state" required placeholder="AZ" maxlength="2"
                                           value="<?php echo htmlspecialchars($form_data['state'] ?? '', ENT_QUOTES, 'UTF-8'); ?>">
                                </label>
                            </div>
                            <div class="small-12 medium-3 cell">
                                <label>ZIP Code *
                                    <input type="text" name="zip" required placeholder="85286"
                                           value="<?php echo htmlspecialchars($form_data['zip'] ?? '', ENT_QUOTES, 'UTF-8'); ?>">
                                </label>
                            </div>
                        </div>
                    </fieldset>
                    
                    <fieldset style="margin-top: 2rem;">
                        <legend>Membership Level * <span style="font-weight: normal; font-size: 0.9rem;">(2-year memberships save $10!)</span></legend>
                        
                        <div style="margin-bottom: 1rem;">
                            <h5 style="margin-bottom: 0.5rem; color: #1779ba;">Traditional Memberships</h5>
                            <p style="font-size: 0.85rem; color: #666; margin-bottom: 1rem;">For supporters who wish to receive benefits and participate in museum activities</p>
                            
                            <div style="border-left: 4px solid #1779ba; padding-left: 1rem; margin-bottom: 0.75rem;">
                                <input type="radio" name="membership_level" value="traditional_regular_1yr" id="trad_reg_1" required
                                       <?php echo (($form_data['membership_level'] ?? '') === 'traditional_regular_1yr') ? 'checked' : ''; ?>>
                                <label for="trad_reg_1" style="margin-bottom: 0.25rem;">
                                    <strong>Traditional Regular: $50/yr</strong> ($95/2yr)
                                </label>
                                <p style="font-size: 0.85rem; color: #666; margin-left: 1.5rem; margin-top: 0.25rem;">
                                    Entitled to all museum benefits with full voting rights. A person must be 18 or older to hold this type of membership.
                                </p>
                            </div>
                            
                            <div style="border-left: 4px solid #1779ba; padding-left: 1rem; margin-bottom: 0.75rem;">
                                <input type="radio" name="membership_level" value="traditional_family_1yr" id="trad_fam_1"
                                       <?php echo (($form_data['membership_level'] ?? '') === 'traditional_family_1yr') ? 'checked' : ''; ?>>
                                <label for="trad_fam_1" style="margin-bottom: 0.25rem;">
                                    <strong>Traditional Family: $75/yr</strong> ($145/2yr)
                                </label>
                                <p style="font-size: 0.85rem; color: #666; margin-left: 1.5rem; margin-top: 0.25rem;">
                                    A family living in the same household, entitled to all museum benefits with full voting rights for one person only.
                                </p>
                            </div>

                            <div style="border-left: 4px solid #1779ba; padding-left: 1rem; margin-bottom: 0.75rem;">
                                <input type="radio" name="membership_level" value="traditional_senior_1yr" id="trad_sen_1"
                                       <?php echo (($form_data['membership_level'] ?? '') === 'traditional_senior_1yr') ? 'checked' : ''; ?>>
                                <label for="trad_sen_1" style="margin-bottom: 0.25rem;">
                                    <strong>Traditional Senior: $45/yr</strong> ($85/2yr)
                                </label>
                                <p style="font-size: 0.85rem; color: #666; margin-left: 1.5rem; margin-top: 0.25rem;">
                                    A person over age 62 entitled to all museum benefits with full voting rights.
                                </p>
                            </div>

                            <div style="border-left: 4px solid #1779ba; padding-left: 1rem; margin-bottom: 0.75rem;">
                                <input type="radio" name="membership_level" value="traditional_regular_2yr" id="trad_reg_2"
                                       <?php echo (($form_data['membership_level'] ?? '') === 'traditional_regular_2yr') ? 'checked' : ''; ?>>
                                <label for="trad_reg_2" style="margin-bottom: 0.25rem;">
                                    <strong>Traditional Regular: $95/2yr</strong>
                                </label>
                            </div>

                            <div style="border-left: 4px solid #1779ba; padding-left: 1rem; margin-bottom: 0.75rem;">
                                <input type="radio" name="membership_level" value="traditional_family_2yr" id="trad_fam_2"
                                       <?php echo (($form_data['membership_level'] ?? '') === 'traditional_family_2yr') ? 'checked' : ''; ?>>
                                <label for="trad_fam_2" style="margin-bottom: 0.25rem;">
                                    <strong>Traditional Family: $145/2yr</strong>
                                </label>
                            </div>

                            <div style="border-left: 4px solid #1779ba; padding-left: 1rem; margin-bottom: 0.75rem;">
                                <input type="radio" name="membership_level" value="traditional_senior_2yr" id="trad_sen_2"
                                       <?php echo (($form_data['membership_level'] ?? '') === 'traditional_senior_2yr') ? 'checked' : ''; ?>>
                                <label for="trad_sen_2" style="margin-bottom: 0.25rem;">
                                    <strong>Traditional Senior: $85/2yr</strong>
                                </label>
                            </div>
                        </div>
                        
                        <div style="margin-bottom: 1rem;">
                            <h5 style="margin-bottom: 0.5rem; color: #28a745;">Active Docent Memberships</h5>
                            <p style="font-size: 0.85rem; color: #666; margin-bottom: 1rem;">For volunteers who actively participate as docents at the museum</p>
                            
                            <div style="border-left: 4px solid #28a745; padding-left: 1rem; margin-bottom: 0.75rem;">
                                <input type="radio" name="membership_level" value="docent_regular_1yr" id="doc_reg_1"
                                       <?php echo (($form_data['membership_level'] ?? '') === 'docent_regular_1yr') ? 'checked' : ''; ?>>
                                <label for="doc_reg_1" style="margin-bottom: 0.25rem;">
                                    <strong>Active Docent Regular: $35/yr</strong> ($65/2yr)
                                </label>
                            </div>

                            <div style="border-left: 4px solid #28a745; padding-left: 1rem; margin-bottom: 0.75rem;">
                                <input type="radio" name="membership_level" value="docent_family_1yr" id="doc_fam_1"
                                       <?php echo (($form_data['membership_level'] ?? '') === 'docent_family_1yr') ? 'checked' : ''; ?>>
                                <label for="doc_fam_1" style="margin-bottom: 0.25rem;">
                                    <strong>Active Docent Family: $50/yr</strong> ($95/2yr)
                                </label>
                            </div>

                            <div style="border-left: 4px solid #28a745; padding-left: 1rem; margin-bottom: 0.75rem;">
                                <input type="radio" name="membership_level" value="docent_senior_1yr" id="doc_sen_1"
                                       <?php echo (($form_data['membership_level'] ?? '') === 'docent_senior_1yr') ? 'checked' : ''; ?>>
                                <label for="doc_sen_1" style="margin-bottom: 0.25rem;">
                                    <strong>Active Docent Senior: $30/yr</strong> ($55/2yr)
                                </label>
                            </div>

                            <div style="border-left: 4px solid #28a745; padding-left: 1rem; margin-bottom: 0.75rem;">
                                <input type="radio" name="membership_level" value="docent_regular_2yr" id="doc_reg_2"
                                       <?php echo (($form_data['membership_level'] ?? '') === 'docent_regular_2yr') ? 'checked' : ''; ?>>
                                <label for="doc_reg_2" style="margin-bottom: 0.25rem;">
                                    <strong>Active Docent Regular: $65/2yr</strong>
                                </label>
                            </div>

                            <div style="border-left: 4px solid #28a745; padding-left: 1rem; margin-bottom: 0.75rem;">
                                <input type="radio" name="membership_level" value="docent_family_2yr" id="doc_fam_2"
                                       <?php echo (($form_data['membership_level'] ?? '') === 'docent_family_2yr') ? 'checked' : ''; ?>>
                                <label for="doc_fam_2" style="margin-bottom: 0.25rem;">
                                    <strong>Active Docent Family: $95/2yr</strong>
                                </label>
                            </div>

                            <div style="border-left: 4px solid #28a745; padding-left: 1rem; margin-bottom: 0.75rem;">
                                <input type="radio" name="membership_level" value="docent_senior_2yr" id="doc_sen_2"
                                       <?php echo (($form_data['membership_level'] ?? '') === 'docent_senior_2yr') ? 'checked' : ''; ?>>
                                <label for="doc_sen_2" style="margin-bottom: 0.25rem;">
                                    <strong>Active Docent Senior: $55/2yr</strong>
                                </label>
                            </div>
                        </div>
                        
                        <div style="margin-bottom: 1rem;">
                            <h5 style="margin-bottom: 0.5rem; color: #6f42c1;">Special Memberships</h5>
                            
                            <div style="border-left: 4px solid #6f42c1; padding-left: 1rem; margin-bottom: 0.75rem;">
                                <input type="radio" name="membership_level" value="life" id="life"
                                       <?php echo (($form_data['membership_level'] ?? '') === 'life') ? 'checked' : ''; ?>>
                                <label for="life" style="margin-bottom: 0.25rem;">
                                    <strong>Life: $500</strong> (Single payment)
                                </label>
                                <p style="font-size: 0.85rem; color: #666; margin-left: 1.5rem; margin-top: 0.25rem;">
                                    Entitled to all museum benefits with full voting rights.
                                </p>
                            </div>

                            <div style="border-left: 4px solid #6f42c1; padding-left: 1rem; margin-bottom: 0.75rem;">
                                <input type="radio" name="membership_level" value="sustaining" id="sustaining"
                                       <?php echo (($form_data['membership_level'] ?? '') === 'sustaining') ? 'checked' : ''; ?>>
                                <label for="sustaining" style="margin-bottom: 0.25rem;">
                                    <strong>Sustaining: $50-$500/yr</strong>
                                </label>
                                <p style="font-size: 0.85rem; color: #666; margin-left: 1.5rem; margin-top: 0.25rem;">
                                    Entitled to all museum benefits with full voting rights.
                                </p>
                            </div>

                            <div style="border-left: 4px solid #6f42c1; padding-left: 1rem; margin-bottom: 0.75rem;">
                                <input type="radio" name="membership_level" value="corporate" id="corporate"
                                       <?php echo (($form_data['membership_level'] ?? '') === 'corporate') ? 'checked' : ''; ?>>
                                <label for="corporate" style="margin-bottom: 0.25rem;">
                                    <strong>Corporate Sponsors: $500-Up/yr</strong>
                                </label>
                                <p style="font-size: 0.85rem; color: #666; margin-left: 1.5rem; margin-top: 0.25rem;">
                                    Entitled to all museum benefits with full voting rights for one person.
                                </p>
                            </div>
                        </div>
                        
                        <div id="sustaining_amount_field" style="display: none; margin-top: 1rem; padding: 1rem; background: #f8f9fa; border-radius: 5px;">
                            <label>Sustaining Membership Amount ($50-$500)
                                <input type="number" name="sustaining_amount" min="50" max="500" step="5" placeholder="Enter amount"
                                       value="<?php echo htmlspecialchars($form_data['sustaining_amount'] ?? '', ENT_QUOTES, 'UTF-8'); ?>">
                            </label>
                        </div>

                        <div id="corporate_amount_field" style="display: none; margin-top: 1rem; padding: 1rem; background: #f8f9fa; border-radius: 5px;">
                            <label>Corporate Sponsorship Amount ($500 minimum)
                                <input type="number" name="corporate_amount" min="500" step="50" placeholder="Enter amount"
                                       value="<?php echo htmlspecialchars($form_data['corporate_amount'] ?? '', ENT_QUOTES, 'UTF-8'); ?>">
                            </label>
                        </div>
                    </fieldset>
                    
                    <script>
                        // Show/hide amount fields based on membership selection
                        function updateAmountFields() {
                            const selectedLevel = document.querySelector('input[name="membership_level"]:checked');
                            if (selectedLevel) {
                                document.getElementById('sustaining_amount_field').style.display =
                                    selectedLevel.value === 'sustaining' ? 'block' : 'none';
                                document.getElementById('corporate_amount_field').style.display =
                                    selectedLevel.value === 'corporate' ? 'block' : 'none';
                            }
                        }

                        // Add change listeners to all radio buttons
                        document.querySelectorAll('input[name="membership_level"]').forEach(radio => {
                            radio.addEventListener('change', updateAmountFields);
                        });

                        // Run on page load to show fields if form was submitted with errors
                        updateAmountFields();

                        // Phone number formatting
                        const phoneInput = document.getElementById('phone');
                        
                        phoneInput.addEventListener('input', function(e) {
                            let value = e.target.value.replace(/\D/g, ''); // Remove all non-digits
                            
                            if (value.length > 10) {
                                value = value.slice(0, 10); // Limit to 10 digits
                            }
                            
                            let formatted = '';
                            if (value.length > 0) {
                                formatted = '(' + value.substring(0, 3);
                            }
                            if (value.length >= 4) {
                                formatted += ') ' + value.substring(3, 6);
                            }
                            if (value.length >= 7) {
                                formatted += '-' + value.substring(6, 10);
                            }
                            
                            e.target.value = formatted;
                        });
                    </script>
                    
                    <fieldset style="margin-top: 2rem;">
                        <legend>Areas of Interest (Optional)</legend>
                        <p class="text-muted" style="font-size: 0.9rem; margin-bottom: 1rem;">Check any areas where they might be interested in volunteering or learning more:</p>
                        
                        <div class="grid-x grid-margin-x">
                            <div class="small-12 medium-6 cell">
                                <label style="display: flex; align-items: center; padding: 0.75rem; border: 1px solid #e0e0e0; border-radius: 5px; margin-bottom: 0.5rem; cursor: pointer;">
                                    <input type="checkbox" name="interests[]" value="restoration" style="margin: 0 0.75rem 0 0;" <?php echo isInterestChecked('restoration', $form_data); ?>>
                                    <span>Equipment Restoration</span>
                                </label>
                            </div>
                            <div class="small-12 medium-6 cell">
                                <label style="display: flex; align-items: center; padding: 0.75rem; border: 1px solid #e0e0e0; border-radius: 5px; margin-bottom: 0.5rem; cursor: pointer;">
                                    <input type="checkbox" name="interests[]" value="curatorial" style="margin: 0 0.75rem 0 0;" <?php echo isInterestChecked('curatorial', $form_data); ?>>
                                    <span>Curatorial/Archives</span>
                                </label>
                            </div>
                            <div class="small-12 medium-6 cell">
                                <label style="display: flex; align-items: center; padding: 0.75rem; border: 1px solid #e0e0e0; border-radius: 5px; margin-bottom: 0.5rem; cursor: pointer;">
                                    <input type="checkbox" name="interests[]" value="events" style="margin: 0 0.75rem 0 0;" <?php echo isInterestChecked('events', $form_data); ?>>
                                    <span>Events & Tours</span>
                                </label>
                            </div>
                            <div class="small-12 medium-6 cell">
                                <label style="display: flex; align-items: center; padding: 0.75rem; border: 1px solid #e0e0e0; border-radius: 5px; margin-bottom: 0.5rem; cursor: pointer;">
                                    <input type="checkbox" name="interests[]" value="maintenance" style="margin: 0 0.75rem 0 0;" <?php echo isInterestChecked('maintenance', $form_data); ?>>
                                    <span>Facility Maintenance</span>
                                </label>
                            </div>
                            <div class="small-12 medium-6 cell">
                                <label style="display: flex; align-items: center; padding: 0.75rem; border: 1px solid #e0e0e0; border-radius: 5px; margin-bottom: 0.5rem; cursor: pointer;">
                                    <input type="checkbox" name="interests[]" value="fundraising" style="margin: 0 0.75rem 0 0;" <?php echo isInterestChecked('fundraising', $form_data); ?>>
                                    <span>Fundraising</span>
                                </label>
                            </div>
                            <div class="small-12 medium-6 cell">
                                <label style="display: flex; align-items: center; padding: 0.75rem; border: 1px solid #e0e0e0; border-radius: 5px; margin-bottom: 0.5rem; cursor: pointer;">
                                    <input type="checkbox" name="interests[]" value="gift_shop" style="margin: 0 0.75rem 0 0;" <?php echo isInterestChecked('gift_shop', $form_data); ?>>
                                    <span>Gift Shop</span>
                                </label>
                            </div>
                        </div>
                    </fieldset>
                    
                    <style>
                        fieldset label:has(input[type="checkbox"]):hover {
                            background-color: #f8f9fa;
                            border-color: #1779ba !important;
                        }
                        fieldset label:has(input[type="checkbox"]:checked) {
                            background-color: #e3f2fd;
                            border-color: #1779ba !important;
                        }
                    </style>
                    
                    <fieldset style="margin-top: 2rem;">
                        <legend>Additional Comments</legend>
                        <label>Additional notes from paper application:
                            <textarea name="comments" rows="4"><?php echo htmlspecialchars($form_data['comments'] ?? '', ENT_QUOTES, 'UTF-8'); ?></textarea>
                        </label>
                    </fieldset>
                    
                    <div style="margin-top: 2rem;">
                        <button type="submit" class="button primary large" style="border-radius: 8px;">Submit Application</button>
                        <a href="index.php" class="button secondary large" style="border-radius: 8px;">Cancel</a>
                    </div>
                    
                    <p class="text-muted" style="margin-top: 1rem; font-size: 0.85rem;">* Required fields</p>
                </form>
            </div>
        </div>
    </div>
    
    <div class="small-12 medium-4 cell">
        <div class="card arm-card" style="background: #fff3cd;">
            <div class="card-section">
                <h4>⚠️ Manual Entry</h4>
                <p style="font-size: 0.9rem; margin: 0;">
                    This form is for entering paper applications received by mail or in person. 
                    The application will be marked as pending for review.
                </p>
            </div>
        </div>
        
        <div class="card arm-card" style="margin-top: 1rem;">
            <div class="card-section">
                <h4>No Account Required</h4>
                <p style="font-size: 0.9rem; margin: 0;">
                    Unlike online applications, manual entries do not require a member account. 
                    The application will be stored without linking to a user account.
                </p>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../../assets/footer.php'; ?>
