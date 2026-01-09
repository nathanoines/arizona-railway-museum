<?php
$page_title = "Brochure & Newsletter | Arizona Railway Museum";
require_once __DIR__ . '/../assets/header.php';
?>
</div></div><!-- Close grid-container and page-content for full-width sections -->

<!-- Hero section with background image -->
<section class="arm-hero" style="margin-top: -4.5rem;">
    <div class="grid-container">
        <div class="grid-x grid-margin-x align-middle align-right">
            <div class="small-12 medium-8 cell text-right">
                <h1>Brochure & Newsletter</h1>
                <p class="lead">Learn more about the museum and stay updated with our latest news</p>
            </div>
        </div>
    </div>
</section>

<!-- Main content section on white -->
<section style="background: #fff; padding: 2rem 0;">
    <div class="grid-container">
        <div class="grid-x grid-margin-x">
            <div class="small-12 medium-6 cell">
                <div class="card arm-card">
                    <div class="card-section text-center">
                        <h3>📄 Museum Brochure</h3>
                        <p>Download our informational brochure to learn about the museum, our collection, and visiting information.</p>
                        <div style="margin: 2rem 0;">
                            <div style="background: #f0f3f7; padding: 2rem; border-radius: 8px; min-height: 200px; display: flex; align-items: center; justify-content: center;">
                                <span style="font-size: 4rem; opacity: 0.5;">📋</span>
                            </div>
                        </div>
                        <div class="arm-nav-btn-container" style="justify-content: center; width: 100%;">
                            <a href="https://www.azrymuseum.org/Brochure/2023%20ARM%20Brochure.pdf" target="_blank" class="arm-nav-link primary" style="flex-grow: 1; justify-content: center;">
                                <span>⬇️</span> Download Brochure (PDF)
                            </a>
                        </div>
                    </div>
                </div>
            </div>

            <div class="small-12 medium-6 cell">
                <div class="card arm-card">
                    <div class="card-section text-center">
                        <h3>📰 Museum Newsletter</h3>
                        <p>Read our latest newsletter for updates on restoration projects, events, and museum news.</p>
                        <div style="margin: 2rem 0;">
                            <div style="background: #fff3cd; padding: 2rem; border-radius: 8px; min-height: 200px; display: flex; flex-direction: column; align-items: center; justify-content: center;">
                                <span style="font-size: 4rem; opacity: 0.5;">📰</span>
                                <p style="margin-top: 1rem; font-weight: 600; color: #856404;">Volume 40, Issue 1</p>
                            </div>
                        </div>
                        <div class="arm-nav-btn-container" style="justify-content: center; width: 100%;">
                            <a href="https://www.azrymuseum.org/Brochure/V40-I1.pdf" target="_blank" class="arm-nav-link primary" style="flex-grow: 1; justify-content: center;">
                                <span>📖</span> Read Latest Newsletter (PDF)
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Membership & Archive section with background -->
<section class="arm-links" style="
    background: linear-gradient(rgba(255,255,255,0.92), rgba(255,255,255,0.92)), url('/assets/backgrounds/main.jpg') center/cover no-repeat fixed;
">
    <div class="grid-container">
        <!-- Membership Information -->
        <div class="grid-x grid-margin-x">
            <div class="small-12 cell">
                <div class="card arm-card" style="background: #e3f2fd;">
                    <div class="card-section">
                        <h3>Why Become a Member?</h3>
                        <div class="grid-x grid-margin-x">
                            <div class="small-12 medium-6 cell">
                                <h4 style="color: #1779ba;">Support Our Mission</h4>
                                <p>Your membership directly supports the preservation, restoration, and display of Arizona's railroad heritage. Every dollar helps maintain our collection and facilities.</p>

                                <h4 style="color: #1779ba; margin-top: 1.5rem;">Member Benefits</h4>
                                <ul>
                                    <li>Quarterly newsletter with exclusive updates</li>
                                    <li>Access to members-only events and activities</li>
                                    <li>Voting rights at annual meetings</li>
                                    <li>Satisfaction of supporting railroad preservation</li>
                                    <li>Tax-deductible donation (ARM is a 501(c)(3) non-profit)</li>
                                </ul>
                            </div>

                            <div class="small-12 medium-6 cell">
                                <h4 style="color: #1779ba;">100% Volunteer Organization</h4>
                                <p>The Arizona Railway Museum is entirely staffed by volunteers who donate their time because they believe in preserving Arizona's railroad history.</p>

                                <p>All membership dues and donations go directly toward:</p>
                                <ul>
                                    <li>Equipment restoration and maintenance</li>
                                    <li>Facility operations and improvements</li>
                                    <li>Artifact preservation and cataloging</li>
                                    <li>Educational programs and exhibits</li>
                                    <li>Property lease and utilities</li>
                                </ul>
                            </div>
                        </div>

                        <div style="margin-top: 2rem; text-align: center;">
                            <div class="arm-nav-btn-container white-bg" style="justify-content: center;">
                                <a href="/files/membership-application.pdf" target="_blank" class="arm-nav-link primary">
                                    <span>📝</span> Join Today
                                </a>
                                <span style="color: #ddd;">|</span>
                                <a href="/donations/" class="arm-nav-link primary">
                                    <span>💝</span> Make a Donation
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Archive Newsletters -->
        <div class="grid-x grid-margin-x" style="margin-top: 1.5rem;">
            <div class="small-12 cell">
                <div class="card arm-card">
                    <div class="card-section">
                        <h3>📚 Newsletter Archive</h3>
                        <p>Browse past issues of our newsletter:</p>

                        <div style="margin-top: 1.5rem;">
                            <p class="text-muted"><em>Newsletter archive coming soon. Check back for previous issues.</em></p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<div class="page-content">
<div class="grid-container" style="padding-top: 2.5rem; margin-bottom: -2.25rem;">

<?php require_once __DIR__ . '/../assets/footer.php'; ?>
