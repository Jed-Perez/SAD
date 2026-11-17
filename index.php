<?php
require_once __DIR__.'/app/Core/assets.php';
date_default_timezone_set('Asia/Manila');
header('Cache-Control: no-cache');
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#102f3c">
    <meta name="description" content="Plan CCTV coverage, request installation or repair, and stay connected with Nasugbu Computer Parts and CCTV Services through TechTrack.">
    <title>TechTrack | CCTV Planning &amp; Service with NCPCS</title>
    <link rel="stylesheet" href="<?=asset_url('assets/vendor/bootstrap.min.css')?>">
    <link rel="stylesheet" href="<?=asset_url('assets/css/landing.css')?>">
    <link rel="stylesheet" href="<?=asset_url('assets/css/showcase.css')?>">
</head>
<body>
<a class="skip-link" href="#main-content">Skip to content</a>
<header class="landing-header">
    <div class="landing-container landing-header-inner">
        <a class="landing-brand" href="index.php" aria-label="TechTrack home">
            <span class="landing-brand-mark" aria-hidden="true">T</span>
            <span class="landing-brand-copy">TechTrack<small>NCPCS MANAGEMENT SYSTEM</small></span>
        </a>
        <nav class="landing-links" id="landing-links" aria-label="Primary navigation">
            <a href="#features">Features</a>
            <a href="#how-it-works">How it works</a>
            <a href="#why-ncpcs">Why NCPCS</a>
            <a class="landing-nav-account" href="portal.php">Sign in</a>
            <a class="landing-nav-account" href="portal.php?register=1">Create account</a>
        </nav>
        <div class="landing-header-actions">
            <a class="landing-signin" href="portal.php">Sign in</a>
            <a class="landing-button landing-button-primary" href="portal.php?register=1">Get started <span aria-hidden="true">→</span></a>
        </div>
        <button class="landing-menu-button" id="landing-menu-button" type="button" aria-controls="landing-links" aria-expanded="false" aria-label="Open menu">
            <svg width="21" height="21" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" aria-hidden="true"><path d="M4 7h16M4 12h16M4 17h16"/></svg>
        </button>
    </div>
</header>

<main id="main-content">
    <section class="landing-hero landing-photo-hero" aria-labelledby="hero-title">
        <img class="landing-hero-photo" src="assets/images/security-home.webp" width="1600" height="900" alt="Illustrative modern home with a CCTV camera overlooking the entrance at dusk" fetchpriority="high">
        <div class="landing-container landing-hero-grid">
            <div class="landing-hero-copy">
                <div class="landing-eyebrow"><span class="landing-eyebrow-dot" aria-hidden="true"></span> Connected service. Clearer security.</div>
                <h1 id="hero-title">A clearer path to a <span>safer home.</span></h1>
                <p>Request CCTV installation or repair, share a house plan, and see preliminary camera placements with an itemized equipment and labor estimate. Stay connected with NCPCS from the first question to the final site check.</p>
                <div class="landing-hero-actions">
                    <a class="landing-button landing-button-primary" href="portal.php?register=1">Create your account <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M5 12h14m-6-6 6 6-6 6"/></svg></a>
                    <a class="landing-button landing-button-light" href="#how-it-works">See how it works</a>
                </div>
                <ul class="landing-trust" aria-label="What you can expect">
                    <li><svg viewBox="0 0 24 24" aria-hidden="true"><path d="m5 12 4 4 10-10"/></svg> Private floor plans</li>
                    <li><svg viewBox="0 0 24 24" aria-hidden="true"><path d="m5 12 4 4 10-10"/></svg> Clear cost breakdown</li>
                    <li><svg viewBox="0 0 24 24" aria-hidden="true"><path d="m5 12 4 4 10-10"/></svg> Technician review</li>
                </ul>
            </div>
            <div class="landing-preview-wrap" role="img" aria-label="Illustrative preview of a house floor plan with suggested CCTV camera positions">
                <div class="landing-preview">
                    <div class="landing-preview-top">
                        <div><strong>Home security plan</strong><small>Planning preview · ground floor</small></div>
                        <div class="landing-preview-dots" aria-hidden="true"><span></span><span></span><span></span></div>
                    </div>
                    <div class="landing-plan">
                        <svg viewBox="0 0 460 310" aria-hidden="true" focusable="false">
                            <path class="coverage" d="M53 258 151 189 151 310Z M398 59 310 24 310 118Z M401 253 327 190 460 190Z"/>
                            <path class="wall" d="M50 35H410V275H50ZM50 142H176M176 35V142M176 181V275M176 142H282M282 35V142M282 142H410M282 142V275"/>
                            <text class="room-label" x="77" y="102">ENTRY</text>
                            <text class="room-label" x="190" y="91">LIVING</text>
                            <text class="room-label" x="313" y="91">GARAGE</text>
                            <text class="room-label" x="293" y="217">GARDEN</text>
                            <circle class="camera-point" cx="56" cy="252" r="16"/><text class="camera-number" x="56" y="253">1</text>
                            <circle class="camera-point" cx="397" cy="60" r="16"/><text class="camera-number" x="397" y="61">2</text>
                            <circle class="camera-point" cx="399" cy="251" r="16"/><text class="camera-number" x="399" y="252">3</text>
                        </svg>
                    </div>
                    <div class="landing-preview-bottom"><span>Suggested positions are checked on site</span><strong>Preliminary plan</strong></div>
                </div>
                <div class="landing-floating-note" aria-hidden="true">
                    <span class="landing-floating-note-icon"><svg viewBox="0 0 24 24"><path d="M4 4h16v16H4zM4 10h16M10 10v10"/></svg></span>
                    <span><strong>Clearer estimates</strong><small>Equipment + installation labor</small></span>
                </div>
            </div>
        </div>
    </section>

    <section class="landing-features" id="features" aria-labelledby="features-title">
        <div class="landing-container">
            <div class="landing-section-heading">
                <div class="landing-eyebrow">One connected workspace</div>
                <h2 id="features-title">Everything you need to move from idea to installation.</h2>
                <p>Keep your plans, service requests, estimates, and conversations together so you always know what comes next.</p>
            </div>
            <div class="landing-feature-grid">
                <article class="landing-feature-card">
                    <span class="landing-feature-icon" aria-hidden="true"><svg viewBox="0 0 24 24"><path d="M4 4h16v16H4zM4 12h8M12 4v16M16 7l3 2-3 2"/></svg></span>
                    <h3>Blueprint-guided planning</h3>
                    <p>Upload a floor plan and get preliminary CCTV positions with reasons for each suggestion.</p>
                </article>
                <article class="landing-feature-card">
                    <span class="landing-feature-icon" aria-hidden="true"><svg viewBox="0 0 24 24"><path d="M5 3h14v18l-3-2-4 2-4-2-3 2zM8 8h8M8 12h8M8 16h5"/></svg></span>
                    <h3>Transparent estimates</h3>
                    <p>See an itemized allowance for cameras, materials, installation labor, setup, and contingency.</p>
                </article>
                <article class="landing-feature-card">
                    <span class="landing-feature-icon" aria-hidden="true"><svg viewBox="0 0 24 24"><path d="M4 6h16v14H4zM8 3v6M16 3v6M4 11h16m-11 5 2 2 4-4"/></svg></span>
                    <h3>Service tracking</h3>
                    <p>Request installation, repair, or maintenance and follow appointments and work updates in one place.</p>
                </article>
                <article class="landing-feature-card">
                    <span class="landing-feature-icon" aria-hidden="true"><svg viewBox="0 0 24 24"><path d="M21 11a9 9 0 0 1-9 9H3l2-5a9 9 0 1 1 16-4zM8 10h8M8 14h5"/></svg></span>
                    <h3>Direct communication</h3>
                    <p>Message the NCPCS office about your plan or service without losing the conversation.</p>
                </article>
            </div>
        </div>
    </section>

    <section class="landing-steps" id="how-it-works" aria-labelledby="steps-title">
        <div class="landing-container landing-steps-grid">
            <div class="landing-steps-intro landing-section-heading">
                <div class="landing-eyebrow">Simple from the start</div>
                <h2 id="steps-title">A better way to plan your next step.</h2>
                <p>TechTrack keeps the process understandable, from your first request to a reviewed proposal and scheduled service.</p>
                <a class="landing-button landing-button-primary" href="portal.php?register=1">Get started <span aria-hidden="true">→</span></a>
            </div>
            <div class="landing-step-list">
                <article class="landing-step"><span class="landing-step-number">01</span><div><h3>Create your account</h3><p>Set up a customer account to keep your plans, messages, and service history in one private place.</p></div></article>
                <article class="landing-step"><span class="landing-step-number">02</span><div><h3>Tell us what you need</h3><p>Request a service, or upload a clear image of your house floor plan with your coverage priorities.</p></div></article>
                <article class="landing-step"><span class="landing-step-number">03</span><div><h3>Explore a preliminary plan</h3><p>Review suggested camera positions and an equipment-and-labor estimate built from the catalog and quotation rates.</p></div></article>
                <article class="landing-step"><span class="landing-step-number">04</span><div><h3>Confirm with the team</h3><p>Discuss the proposal with NCPCS. A technician verifies the site, final placement, and actual quote before installation.</p></div></article>
            </div>
        </div>
    </section>

    <section class="landing-care" id="why-ncpcs" aria-labelledby="care-title">
        <div class="landing-container">
            <div class="landing-care-card">
                <div class="landing-care-art" aria-hidden="true"><svg viewBox="0 0 120 120"><path d="M60 9 101 26v30c0 26-18 44-41 55C37 100 19 82 19 56V26z"/><path d="m39 60 14 14 28-30"/></svg></div>
                <div class="landing-care-copy">
                    <div class="landing-eyebrow">Technology with a human check</div>
                    <h2 id="care-title">Smart suggestions. Practical decisions.</h2>
                    <p>The AI planner helps you explore coverage and cost before a visit. Final camera placement, cable routes, stock, and pricing are confirmed by the NCPCS team after a site review.</p>
                    <ul class="landing-care-points">
                        <li><svg viewBox="0 0 24 24" aria-hidden="true"><path d="m5 12 4 4 10-10"/></svg> Preliminary plans are clearly labeled</li>
                        <li><svg viewBox="0 0 24 24" aria-hidden="true"><path d="m5 12 4 4 10-10"/></svg> Costs include equipment and labor allowances</li>
                        <li><svg viewBox="0 0 24 24" aria-hidden="true"><path d="m5 12 4 4 10-10"/></svg> Your conversation stays with your account</li>
                    </ul>
                </div>
            </div>
        </div>
    </section>

    <section class="landing-cta" aria-labelledby="cta-title">
        <div class="landing-container">
            <div class="landing-cta-card">
                <div><div class="landing-eyebrow">Ready when you are</div><h2 id="cta-title">Start with a clear plan. Stay connected through every step.</h2></div>
                <div class="landing-cta-actions">
                    <a class="landing-button landing-button-primary" href="portal.php?register=1">Create account <span aria-hidden="true">→</span></a>
                    <a class="landing-button landing-button-ghost" href="portal.php">Sign in</a>
                </div>
            </div>
        </div>
    </section>
</main>

<footer class="landing-footer">
    <div class="landing-container">
        <div class="landing-footer-main">
            <div>
                <a class="landing-brand" href="index.php"><span class="landing-brand-mark" aria-hidden="true">T</span><span class="landing-brand-copy">TechTrack<small>NCPCS MANAGEMENT SYSTEM</small></span></a>
                <p>Plan CCTV coverage, manage service requests, and stay in touch with Nasugbu Computer Parts and CCTV Services.</p>
            </div>
            <nav class="landing-footer-links" aria-label="Footer navigation"><a href="#features">Features</a><a href="#how-it-works">How it works</a><a href="portal.php">Customer portal</a><a href="portal.php?register=1">Create account</a></nav>
        </div>
        <div class="landing-footer-bottom"><span>© <?=date('Y')?> Nasugbu Computer Parts and CCTV Services</span><span>Nasugbu, Batangas · Philippines</span></div>
    </div>
</footer>
<script src="<?=asset_url('assets/js/Landing/landing.js')?>"></script>
</body>
</html>
