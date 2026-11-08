<?php
require_once __DIR__ . '/includes/auth.php';

// Logged-in users go straight to their dashboard; everyone else sees the public homepage.
if (is_logged_in()) {
    header('Location: home.php');
    exit;
}

$pdo = db();

// Live numbers for the impact band (same rules as the Impact page).
$numbers = $pdo->query(
    "SELECT (SELECT COALESCE(SUM(quantity), 0) FROM food_posts WHERE status = 'collected' AND unit = 'plates') AS plates,
            (SELECT COUNT(*) FROM food_posts WHERE status = 'collected') AS pickups,
            (SELECT COUNT(*) FROM users WHERE role = 'donor') AS donors,
            (SELECT COUNT(*) FROM ngos WHERE is_verified = 1) AS ngos"
)->fetch();

$openNow = (int) $pdo->query("SELECT COUNT(*) FROM food_posts WHERE status = 'available' AND pickup_until > NOW()")->fetchColumn();

// A small, public preview of food that is open right now (area and city only, never the address).
$preview = $pdo->query(
    "SELECT p.title, p.quantity, p.unit, p.pickup_from, p.pickup_until, c.name AS category, l.area, l.city
     FROM food_posts p
     LEFT JOIN food_categories c ON c.id = p.category_id
     LEFT JOIN locations l ON l.id = p.pickup_location_id
     WHERE p.status = 'available' AND p.pickup_until > NOW()
     ORDER BY p.pickup_until ASC LIMIT 6"
)->fetchAll();

$features = [
    ['search',   'Live food feed',          'Every surplus post appears instantly, ending soonest first, with filters for city, area and food type.'],
    ['lock',     'Pickup codes & QR',       'Each claim gets a private 6-digit code. The donor confirms the handover only when the right person arrives.'],
    ['shield',   'Verified collectors',     'An admin checks every NGO and volunteer before they can claim, so donors know who is coming.'],
    ['clock',    'Status timeline',         'Posted, claimed, collected: every step is recorded with who did it and when.'],
    ['users',    'NGO teams',               'NGOs add their volunteers, who can then collect food on the organisation\'s behalf.'],
    ['leaf',     'Impact dashboard',        'Charts and leaderboards show how many plates have been saved, and by whom.'],
    ['phone',    'Contact when it matters', 'Phone numbers and full addresses are shared only between the donor and the claimer.'],
    ['utensils', 'Built for real kitchens', 'Restaurants, bakeries and wedding halls can post surplus in under a minute, from any phone.'],
];

$faq = [
    ['Is Save & Serve free to use?', 'Yes. Donors, NGOs and volunteers use it free of charge. The goal is simply to get good food to people instead of into bins.'],
    ['Who can claim food?', 'Members of an NGO verified by our admin, and individual volunteers the admin has verified. Unverified accounts can browse but not claim.'],
    ['How do I know the right person collects my food?', 'When someone claims your food they receive a private 6-digit pickup code (and QR code). You enter that code when they arrive; only the correct code marks the food as collected.'],
    ['What kind of food can I donate?', 'Cooked meals, bakery items, fruit and vegetables, packaged food and event leftovers that are still safe to eat. Please pack it well and describe it honestly.'],
    ['What happens if nobody claims my post?', 'When the pickup window ends the post is marked as expired. You can repost it with a new pickup time in one click.'],
    ['How does my NGO get verified?', 'Sign up as an NGO with your registration number and office address. An admin reviews it, and once verified your team can start claiming.'],
];

$pageTitle = 'Rescue surplus food';
$bodyClass = 'landing';
require __DIR__ . '/includes/header.php';
?>
<!-- ============================ Navbar ============================ -->
<!-- Live info bar -->
<div class="topbar">
    <div class="topbar-inner">
        <span class="topbar-live"><span class="live-dot"></span>
            <strong><?= plural($openNow, 'meal') ?></strong> available right now
            <span class="topbar-sep">·</span>
            <strong><?= number_format((int) $numbers['plates']) ?></strong> plates saved so far
        </span>
        <span class="topbar-links">
            <a href="signup.php?role=ngo">Are you an NGO? Register <?= icon('arrow-right') ?></a>
        </span>
    </div>
</div>

<!-- Navbar (full width: logo on the left, account buttons on the right) -->
<header class="site-nav" data-nav>
    <div class="site-nav-inner">
        <a class="brand" href="index.php">
            <img class="brand-logo" src="assets/img/logo.svg" alt="">
            <span>Save <b>&amp;</b> Serve</span>
        </a>
        <nav class="site-links" id="site-links" aria-label="Main">
            <a href="#top" class="is-current">Home</a>
            <a href="#now">Available food<?php if ($openNow): ?> <span class="nav-badge"><?= $openNow ?></span><?php endif; ?></a>
            <a href="#how">How it works</a>
            <a href="#who">Who it's for</a>
            <a href="#impact">Impact</a>
            <a href="#faq">FAQ</a>
            <a href="#contact">Contact</a>
            <span class="site-links-auth site-links-auth-mobile">
                <a class="btn btn-outline" href="login.php"><?= icon('user') ?> Log in</a>
                <a class="btn btn-primary" href="signup.php">Sign up free</a>
            </span>
        </nav>
        <div class="site-auth">
            <a class="btn btn-outline" href="login.php"><?= icon('user') ?> Log in</a>
            <a class="btn btn-primary" href="signup.php">Sign up free</a>
        </div>
        <button class="nav-toggle" type="button" aria-label="Open menu" aria-expanded="false" aria-controls="site-links" data-nav-toggle>
            <span></span><span></span><span></span>
        </button>
    </div>
</header>

<main class="landing-main" id="top">
    <!-- ============================ Hero ============================ -->
    <section class="l-hero">
        <div class="l-container l-hero-grid">
            <div class="l-hero-copy">
                <span class="pill"><span class="live-dot"></span> Surplus food sharing for your city</span>
                <h1>Good food belongs on plates, <em>not in bins.</em></h1>
                <p class="l-lead">Save &amp; Serve connects restaurants, bakeries and wedding halls that have food left over with NGOs and volunteers who can get it to people who need it, while it is still fresh.</p>
                <div class="l-hero-actions">
                    <a class="btn btn-primary btn-lg" href="signup.php?role=donor"><?= icon('utensils') ?> Donate surplus food</a>
                    <a class="btn btn-outline btn-lg" href="signup.php?role=ngo"><?= icon('hand') ?> Collect as an NGO</a>
                </div>
                <ul class="l-trust">
                    <li><?= icon('check') ?> Free to use</li>
                    <li><?= icon('shield') ?> Verified NGOs</li>
                    <li><?= icon('lock') ?> Safe handover codes</li>
                </ul>
            </div>

            <!-- Live "departures board" of real open food, soonest first -->
            <aside class="board" aria-label="Food available right now">
                <header class="board-head">
                    <span><span class="live-dot"></span> Live board</span>
                    <span class="board-clock" data-clock><?= date('H:i') ?></span>
                </header>
                <div class="board-cols"><span>Food</span><span>Area</span><span>Qty</span><span>Closes in</span></div>
                <?php if (!$preview): ?>
                    <p class="board-empty">No food is open right now. New posts appear here the moment a donor shares them.</p>
                <?php endif; ?>
                <ol class="board-rows">
                    <?php foreach (array_slice($preview, 0, 5) as $p): ?>
                    <?php [$left, $tone] = time_left($p['pickup_until']); ?>
                    <li>
                        <span class="board-food"><?= e($p['title']) ?><small><?= e($p['category'] ?? 'Food') ?></small></span>
                        <span class="board-area"><?= e($p['area'] ?: $p['city']) ?></span>
                        <span class="board-qty"><?= e($p['quantity']) ?> <small><?= e($p['unit']) ?></small></span>
                        <span class="board-left t-<?= $tone ?>" data-until="<?= e(iso($p['pickup_until'])) ?>" data-from="<?= e(iso($p['pickup_from'])) ?>"><span class="tl"><?= e($left) ?></span></span>
                    </li>
                    <?php endforeach; ?>
                </ol>
                <footer class="board-foot">
                    <span><?= plural($openNow, 'post') ?> open · <?= number_format((int) $numbers['plates']) ?> plates saved</span>
                    <a href="login.php">Log in to claim <?= icon('arrow-right') ?></a>
                </footer>
            </aside>
        </div>
    </section>

    <!-- ============================ Impact numbers ============================ -->
    <section class="l-numbers" id="impact">
        <div class="l-container l-numbers-grid">
            <div class="l-number scroll-reveal"><strong data-count="<?= (int) $numbers['plates'] ?>"><?= (int) $numbers['plates'] ?></strong><span>plates of food saved</span></div>
            <div class="l-number scroll-reveal"><strong data-count="<?= (int) $numbers['pickups'] ?>"><?= (int) $numbers['pickups'] ?></strong><span>pickups completed</span></div>
            <div class="l-number scroll-reveal"><strong data-count="<?= (int) $numbers['donors'] ?>"><?= (int) $numbers['donors'] ?></strong><span>donors sharing food</span></div>
            <div class="l-number scroll-reveal"><strong data-count="<?= (int) $numbers['ngos'] ?>"><?= (int) $numbers['ngos'] ?></strong><span>verified NGOs</span></div>
        </div>
    </section>

    <!-- ============================ How it works ============================ -->
    <section class="l-section" id="how">
        <div class="l-container">
            <div class="l-heading scroll-reveal">
                <span class="eyebrow">How it works</span>
                <h2>From kitchen to community in three steps</h2>
                <p>No phone calls, no guesswork. Everyone can see what is available, who is collecting it and when it was handed over.</p>
            </div>
            <ol class="l-steps">
                <li class="scroll-reveal">
                    <span class="l-step-num">1</span>
                    <span class="l-step-icon"><?= icon('utensils') ?></span>
                    <h3>Donor posts surplus</h3>
                    <p>A restaurant or wedding hall posts what is left over: what it is, how much, where, and until when it can be picked up.</p>
                </li>
                <li class="scroll-reveal">
                    <span class="l-step-num">2</span>
                    <span class="l-step-icon"><?= icon('hand') ?></span>
                    <h3>An NGO claims it</h3>
                    <p>Verified NGOs and volunteers see it instantly and claim it. Only one claim is allowed, so nobody makes a wasted trip.</p>
                </li>
                <li class="scroll-reveal">
                    <span class="l-step-num">3</span>
                    <span class="l-step-icon"><?= icon('check-circle') ?></span>
                    <h3>Collected with a code</h3>
                    <p>The collector shows a private pickup code, the donor confirms it, and the food is on its way to people who need it.</p>
                </li>
            </ol>
        </div>
    </section>

    <!-- ============================ Features ============================ -->
    <section class="l-section l-section-tint" id="features">
        <div class="l-container">
            <div class="l-heading scroll-reveal">
                <span class="eyebrow">Features</span>
                <h2>Everything a food rescue needs</h2>
                <p>Designed around how surplus food is really shared: quickly, safely and with clear responsibility.</p>
            </div>
            <div class="l-features">
                <?php foreach ($features as [$ico, $title, $text]): ?>
                <article class="l-feature scroll-reveal">
                    <span class="l-feature-icon"><?= icon($ico) ?></span>
                    <h3><?= e($title) ?></h3>
                    <p><?= e($text) ?></p>
                </article>
                <?php endforeach; ?>
            </div>
        </div>
    </section>

    <!-- ============================ Who it's for ============================ -->
    <section class="l-section" id="who">
        <div class="l-container">
            <div class="l-heading scroll-reveal">
                <span class="eyebrow">Who it's for</span>
                <h2>One platform, three ways to help</h2>
            </div>
            <div class="l-roles">
                <article class="l-role scroll-reveal">
                    <img src="assets/img/cat-event.jpg" alt="Catering trays at an event">
                    <div class="l-role-body">
                        <h3>Donors</h3>
                        <p>Restaurants, bakeries, wedding halls and households with good food left over.</p>
                        <ul>
                            <li><?= icon('check') ?> Post surplus in under a minute</li>
                            <li><?= icon('check') ?> See who is coming and confirm with a code</li>
                            <li><?= icon('check') ?> Track how many plates you have saved</li>
                        </ul>
                        <a class="btn btn-primary" href="signup.php?role=donor">Start donating <?= icon('arrow-right') ?></a>
                    </div>
                </article>
                <article class="l-role scroll-reveal">
                    <img src="assets/img/cat-cooked.jpg" alt="A plate of biryani">
                    <div class="l-role-body">
                        <h3>NGOs</h3>
                        <p>Registered organisations that feed people in need.</p>
                        <ul>
                            <li><?= icon('check') ?> Find food near you, ending soonest first</li>
                            <li><?= icon('check') ?> Build a team of volunteers</li>
                            <li><?= icon('check') ?> Claims are recorded under your NGO</li>
                        </ul>
                        <a class="btn btn-primary" href="signup.php?role=ngo">Register your NGO <?= icon('arrow-right') ?></a>
                    </div>
                </article>
                <article class="l-role scroll-reveal">
                    <img src="assets/img/cat-fresh.jpg" alt="A basket of fresh fruit">
                    <div class="l-role-body">
                        <h3>Volunteers</h3>
                        <p>People with a little time and a way to get around.</p>
                        <ul>
                            <li><?= icon('check') ?> Collect food on your own once verified</li>
                            <li><?= icon('check') ?> Or join an NGO's team</li>
                            <li><?= icon('check') ?> See every meal you helped rescue</li>
                        </ul>
                        <a class="btn btn-primary" href="signup.php?role=volunteer">Become a volunteer <?= icon('arrow-right') ?></a>
                    </div>
                </article>
            </div>
        </div>
    </section>

    <!-- ============================ Available now ============================ -->
    <section class="l-section l-section-tint" id="now">
        <div class="l-container">
            <div class="l-heading scroll-reveal">
                <span class="eyebrow"><span class="live-dot"></span> Available right now</span>
                <h2>Food waiting to be rescued</h2>
                <p>A live look at what donors have posted. Log in as a verified NGO or volunteer to claim it.</p>
            </div>
            <?php if (!$preview): ?>
            <div class="empty scroll-reveal">
                <span class="empty-icon"><?= icon('inbox') ?></span>
                <h3>Nothing open right now</h3>
                <p>New food appears here the moment a donor posts it. Have something to share?</p>
                <a class="btn btn-primary" href="signup.php?role=donor">Donate surplus food</a>
            </div>
            <?php else: ?>
            <div class="l-preview">
                <?php foreach ($preview as $p): ?>
                <?php [, , $photo] = category_style($p['category']); [$left, $tone] = time_left($p['pickup_until']); ?>
                <article class="food-card scroll-reveal">
                    <div class="food-top">
                        <img src="<?= e($photo) ?>" alt="<?= e($p['category'] ?? 'Food') ?>">
                        <?php if ($p['category']): ?><span class="tag tag-photo"><?= e($p['category']) ?></span><?php endif; ?>
                        <span class="time-chip t-<?= $tone ?>" data-until="<?= e(iso($p['pickup_until'])) ?>" data-from="<?= e(iso($p['pickup_from'])) ?>"><?= icon('clock') ?> <span class="tl"><?= e($left) ?></span></span>
                    </div>
                    <div class="food-body">
                        <h3><?= e($p['title']) ?></h3>
                        <ul class="food-meta">
                            <li><?= icon('pin') ?><?= e(trim(($p['area'] ? $p['area'] . ', ' : '') . $p['city'])) ?></li>
                        </ul>
                    </div>
                    <div class="food-foot">
                        <div class="qty"><strong><?= e($p['quantity']) ?></strong><span><?= e($p['unit']) ?></span></div>
                        <a class="btn btn-outline btn-sm" href="login.php">Log in to claim</a>
                    </div>
                </article>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>
    </section>

    <!-- ============================ Why it matters ============================ -->
    <section class="l-section">
        <div class="l-container l-why">
            <div class="l-why-photo scroll-reveal">
                <img src="assets/img/cat-event.jpg" alt="Food from an event">
            </div>
            <div class="l-why-copy scroll-reveal">
                <span class="eyebrow">Why it matters</span>
                <h2>The food is already there. It just needs to move.</h2>
                <p>Every evening, restaurants close with trays still full and wedding halls clear away dishes that were never touched. A few streets away, shelters and families are planning how to stretch tonight's meal.</p>
                <p>The gap is not food, it is coordination: knowing what is available, who can collect it, and doing it before the food spoils. Save &amp; Serve closes that gap with a simple, trustworthy process anyone can use from a phone.</p>
                <a class="btn btn-primary" href="signup.php">Join Save &amp; Serve <?= icon('arrow-right') ?></a>
            </div>
        </div>
    </section>

    <!-- ============================ FAQ ============================ -->
    <section class="l-section l-section-tint" id="faq">
        <div class="l-container l-faq-wrap">
            <div class="l-heading scroll-reveal">
                <span class="eyebrow">FAQ</span>
                <h2>Questions people ask</h2>
            </div>
            <div class="l-faq">
                <?php foreach ($faq as $i => [$q, $a]): ?>
                <details class="scroll-reveal" <?= $i === 0 ? 'open' : '' ?>>
                    <summary><?= e($q) ?><span class="faq-plus"></span></summary>
                    <p><?= e($a) ?></p>
                </details>
                <?php endforeach; ?>
            </div>
        </div>
    </section>

    <!-- ============================ Call to action ============================ -->
    <section class="l-cta">
        <div class="l-container l-cta-box scroll-reveal">
            <div>
                <h2>Have surplus food tonight?</h2>
                <p>Post it in a minute and a verified NGO nearby will come and collect it.</p>
            </div>
            <div class="l-cta-actions">
                <a class="btn btn-ink btn-lg" href="signup.php?role=donor">Donate food</a>
                <a class="btn btn-lg" href="login.php">Log in</a>
            </div>
        </div>
    </section>
</main>

<!-- ============================ Footer ============================ -->
<footer class="site-footer">
    <div class="l-container site-footer-grid">
        <div>
            <a class="brand" href="index.php">
                <img class="brand-logo" src="assets/img/logo.svg" alt="">
                <span>Save <b>&amp;</b> Serve</span>
            </a>
            <p>Connecting surplus food with the people who need it. Less waste, fewer hungry nights.</p>
        </div>
        <div>
            <h4>Platform</h4>
            <a href="#how">How it works</a>
            <a href="#features">Features</a>
            <a href="#faq">FAQ</a>
        </div>
        <div>
            <h4>Get started</h4>
            <a href="signup.php?role=donor">Donate food</a>
            <a href="signup.php?role=ngo">Register an NGO</a>
            <a href="signup.php?role=volunteer">Volunteer</a>
        </div>
        <div id="contact">
            <h4>Contact</h4>
            <a href="mailto:hello@saveandserve.test"><?= icon('mail') ?> hello@saveandserve.test</a>
            <a href="login.php"><?= icon('user') ?> Log in</a>
            <a href="signup.php"><?= icon('plus') ?> Create an account</a>
        </div>
    </div>
    <div class="l-container site-footer-bottom">
        <span>&copy; <?= date('Y') ?> Save &amp; Serve</span>
        <span>Photos from Unsplash</span>
    </div>
</footer>
<?php require __DIR__ . '/includes/footer.php'; ?>
