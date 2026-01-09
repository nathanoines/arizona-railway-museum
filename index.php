<?php
$page_title = "Arizona Railway Museum | Chandler, AZ";
require_once __DIR__ . '/assets/header.php';
?>
</div></div><!-- Close grid-container and page-content for full-width sections -->

<!-- Hero / intro section -->
<section class="arm-hero" style="margin-top: -4.5rem; ">
    <div class="grid-container">
        <div class="grid-x grid-margin-x align-middle align-right">
          <div class="small-12 medium-6 cell text-right">
            <h1>Arizona Railway Museum</h1>
            <p class="lead">
              Preserving the railroad history of Arizona and the Southwest.
            </p>
            <p class="hero-meta">
              330 E. Ryan Road · Chandler, AZ 85286<br>
              (480) 821-1108
            </p>
            <div class="arm-nav-btn-container white-bg" style="margin-top: 0.5rem;">
              <a href="/information" class="arm-nav-link primary">
                <span>🕐</span> Hours &amp; Admission
              </a>
              <span style="color: #ddd;">|</span>
              <a href="https://www.google.com/maps/place/330+E+Ryan+Rd,+Chandler,+AZ+85286"
                 class="arm-nav-link primary"
                 target="_blank" rel="noopener">
                <span>📍</span> Get Directions
              </a>
            </div>
          </div>
        </div>
    </div>
</section>

<!-- Quick facts: season, hours, admission -->
<section class="arm-quick-facts">
    <div class="grid-container">
        <div class="grid-x grid-margin-x text-center">
          <div class="small-12 cell">
            <h2>Plan Your Visit</h2>
            <p class="section-intro">
              Open seasonally September through May with weekend visiting hours.
            </p>
          </div>
        </div>

        <div class="grid-x grid-margin-x small-up-1 medium-up-3 large-up-3">
          <div class="cell">
            <div class="card">
              <div class="card-section">
                <h3>2025–2026 Season</h3>
                <p>September 6, 2025 – May 24, 2026</p>
                <p class="subheader">Closed June, July, August</p>
              </div>
            </div>
          </div>
          <div class="cell">
            <div class="card">
              <div class="card-section">
                <h3>Hours</h3>
                <p>
                  Saturday: <strong>10am – 4pm</strong><br>
                  Sunday: <strong>10am – 4pm</strong><br>
                  <span class="subheader">Closed Mon–Fri & Easter</span>
                </p>
              </div>
            </div>
          </div>
          <div class="cell">
            <div class="card">
              <div class="card-section">
                <h3>Admission</h3>
                <p>
                  Adult (12+): <strong>$15</strong><br>
                  Child (2–12): <strong>$10</strong><br>
                  Under 2: <strong>Free</strong><br>
                  Military w/ ID: <strong>Free</strong>
                </p>
              </div>
            </div>
          </div>
          <?php /* Photo Highlights - Hidden for now
          <div class="cell">
            <div class="card secondary">
              <div class="card-section">
                <h3>Photo Highlights</h3>
                <div class="orbit" role="region" aria-label="Photo Highlights" data-orbit>
                  <div class="orbit-wrapper">
                    <div class="orbit-controls">
                      <button class="orbit-previous"><span class="show-for-sr">Previous Slide</span>&#9664;</button>
                      <button class="orbit-next"><span class="show-for-sr">Next Slide</span>&#9654;</button>
                    </div>
                    <ul class="orbit-container">
                      <li class="is-active orbit-slide">
                        <figure class="orbit-figure">
                          <img class="orbit-image" src="images/2016-02-20 13.05.50-tm.jpg"
                            alt="Historic passenger car exterior">
                        </figure>
                      </li>
                      <li class="orbit-slide">
                        <figure class="orbit-figure">
                          <img class="orbit-image" src="images/2016-02-20 13.08.09-tm.jpg"
                            alt="Historic passenger cars at the Arizona Railway Museum">
                        </figure>
                      </li>
                      <li class="orbit-slide">
                        <figure class="orbit-figure">
                          <img class="orbit-image" src="img/arm-yard.jpg"
                            alt="Museum yard and rolling stock">
                        </figure>
                      </li>
                    </ul>
                  </div>
                </div>
              </div>
            </div>
          </div>
          */ ?>
        </div>
    </div>
</section>

<!-- Combined background section: Explore + Membership -->
<section class="arm-links" style="
    background: linear-gradient(rgba(255,255,255,0.92), rgba(255,255,255,0.92)), url('/assets/backgrounds/main.jpg') center/cover no-repeat fixed;
">
    <div class="grid-container">
        <!-- Become a Member -->
        <div class="grid-x grid-margin-x align-center text-center">
            <div class="small-12 medium-10 large-8 cell">
                <h2>Become a Member</h2>
                <p class="lead" style="color: #555;">
                    Join the Arizona Railway Museum family and help preserve Arizona's railroad heritage for future generations.
                </p>
                <p style="margin-bottom: 1.5rem;">
                    Members receive free admission, newsletter updates, special event invitations, and the satisfaction of supporting railroad history preservation.
                </p>
                <a href="/membership" class="button large primary" style="border-radius: 8px; margin-right: 0.5rem;">
                    Join Today
                </a>
                <a href="/donations" class="button large secondary" style="border-radius: 8px;">
                    Make a Donation
                </a>
            </div>
        </div>

        <!-- Explore the Museum Online -->
        <div class="grid-x grid-margin-x" style="margin-top: 3rem;">
          <div class="small-12 cell text-center">
            <h2>Explore the Museum Online</h2>
          </div>
        </div>

        <div class="grid-x grid-margin-x small-up-1 medium-up-3">
          <!-- Visit section -->
          <div class="cell">
            <div class="card">
              <div class="card-section">
                <h3>Plan Your Visit</h3>
                <ul class="no-bullet">
                  <li><a href="/information">Hours, Fees &amp; Tours</a></li>
                  <li>
                    <a href="https://www.google.com/maps/place/330+E+Ryan+Rd,+Chandler,+AZ+85286"
                       target="_blank" rel="noopener">
                      Directions / Map / Location
                    </a>
                  </li>
                  <li><a href="/events">Events</a></li>
                  <li><a href="azrrmap.htm">Arizona RR Map</a></li>
                </ul>
              </div>
            </div>
          </div>

          <!-- Learn section -->
          <div class="cell">
            <div class="card">
              <div class="card-section">
                <h3>Learn &amp; Explore</h3>
                <ul class="no-bullet">
                  <li><a href="/mission">Mission Statement</a></li>
                  <li><a href="/founders">Founders, Board of Directors</a></li>
                  <li><a href="/equipment">Equipment Roster</a></li>
                  <?php /* <li><a href="photos.htm">Online Photo Collection</a></li> */ ?>
                  <li><a href="/artifacts">Artifact Collection</a></li>
                  <li><a href="/projects">Projects</a></li>
                </ul>
              </div>
            </div>
          </div>

          <!-- Support section -->
          <div class="cell">
            <div class="card">
              <div class="card-section">
                <h3>Support ARM</h3>
                <ul class="no-bullet">
                  <li><a href="/brochure">Brochure &amp; Newsletter</a></li>
                  <li><a href="/donations">Donations</a></li>
                  <li><a href="/members">Members Only</a></li>
                  <li>
                    <a href="https://www.facebook.com/ArizonaRailwayMuseum" target="_blank" rel="noopener">
                      Facebook Page
                    </a>
                  </li>
                </ul>
              </div>
            </div>
          </div>
        </div>
    </div>
</section>

<div class="page-content">
<div class="grid-container" style="padding-top: 2.5rem; margin-bottom: -2.25rem;">
<?php require_once __DIR__ . '/assets/footer.php'; ?>
