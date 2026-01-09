<?php
session_start();

// Redirect if they accessed this page directly without submitting
if (!isset($_SESSION['application_submitted'])) {
    header('Location: /membership/apply.php');
    exit;
}

$name = $_SESSION['applicant_name'] ?? 'Member';
$email = $_SESSION['applicant_email'] ?? '';

// Clear the session flags
unset($_SESSION['application_submitted']);
unset($_SESSION['applicant_name']);
unset($_SESSION['applicant_email']);

$page_title = "Application Received | Arizona Railway Museum";
require_once __DIR__ . '/../assets/header.php';
?>

<div class="grid-x grid-margin-x">
    <div class="small-12 medium-8 medium-offset-2 cell">
        <div class="card arm-card" style="text-align: center; padding: 2rem; background: #d4edda; border: 2px solid #28a745;">
            <div class="card-section">
                <h1 style="color: #155724; margin-bottom: 1rem;">
                    <i class="fas fa-check-circle" style="font-size: 3rem;"></i><br>
                    Application Received!
                </h1>

                <p class="lead" style="color: #155724;">
                    Thank you, <strong><?php echo htmlspecialchars($name, ENT_QUOTES, 'UTF-8'); ?></strong>!
                </p>

                <p style="font-size: 1.1rem; margin: 1.5rem 0;">
                    Your membership application has been received and is <strong>pending review</strong> by our team.
                </p>

                <?php if (!empty($email)): ?>
                <p style="color: #666; margin-bottom: 2rem;">
                    A confirmation email will be sent to <strong><?php echo htmlspecialchars($email, ENT_QUOTES, 'UTF-8'); ?></strong>
                </p>
                <?php endif; ?>

                <hr style="margin: 2rem 0; border-color: #28a745;">

                <h3 style="color: #155724; margin-bottom: 1rem;">What Happens Next?</h3>

                <div style="text-align: left; max-width: 600px; margin: 0 auto;">
                    <ol style="line-height: 1.8; font-size: 1rem;">
                        <li>Our membership team will review your application</li>
                        <li>You will receive an email with payment instructions</li>
                        <li>Once payment is received, your membership will be activated</li>
                        <li>You'll receive your membership card and welcome materials</li>
                    </ol>
                </div>

                <div style="background: #fff3cd; border-left: 4px solid #ffc107; padding: 1rem; margin: 2rem 0; text-align: left;">
                    <h4 style="margin-top: 0; color: #856404;">
                        <i class="fas fa-info-circle"></i> Payment Information
                    </h4>
                    <p style="margin: 0; color: #856404;">
                        Mail your payment to:<br>
                        <strong>Arizona Railway Museum</strong><br>
                        P.O. Box 842<br>
                        Chandler, AZ 85244
                    </p>
                    <p style="margin-top: 0.5rem; color: #856404; font-size: 0.9rem;">
                        Make checks payable to <strong>Arizona Railway Museum</strong>
                    </p>
                </div>

                <div style="margin-top: 2rem;">
                    <a href="/" class="button primary large" style="border-radius: 8px;">Return to Home</a>
                    <a href="/membership/" class="button secondary large" style="border-radius: 8px;">Membership Info</a>
                </div>

                <p style="margin-top: 2rem; font-size: 0.9rem; color: #666;">
                    Questions? <a href="/information">Contact us</a> for assistance.
                </p>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../assets/footer.php'; ?>
