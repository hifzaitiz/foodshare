<?php
require_once __DIR__ . '/includes/auth.php';
require_login();

$pdo    = db();
$userId = $_SESSION['user_id'];
$role   = $_SESSION['role'];

$live = $past = $available = $activeClaims = $pastClaims = $pendingNgos = $pendingVolunteers = $categories = $cities = [];
$eligible   = false;
$rejectedAt = null;   // set when the admin rejected this NGO / volunteer
$teams      = [];     // NGOs a volunteer belongs to (ngo_members)
$stats      = [];     // [label, value, icon, colour] cards under the banner
$fCity = $fArea = '';
$fCat  = 0;
$hero  = ['line' => '', 'photos' => []];   // banner sentence + photo stack

// Alerts left by claim.php, collect.php, etc.
$flash = $_SESSION['flash'] ?? null;
unset($_SESSION['flash']);

if ($role === 'donor') {
    $stmt = $pdo->prepare(
        'SELECT p.id, p.title, p.description, p.quantity, p.unit, p.pickup_from, p.pickup_until, p.status, p.created_at,
                c.name AS category, l.area, l.city,
                u.full_name AS claimer, u.phone AS claimer_phone, n.name AS claimer_ngo, cl.claimed_at, cl.collected_at
         FROM food_posts p
         LEFT JOIN claims cl ON cl.post_id = p.id
         LEFT JOIN users u ON u.id = cl.claimed_by
         LEFT JOIN ngos n ON n.id = cl.ngo_id
         LEFT JOIN food_categories c ON c.id = p.category_id
         LEFT JOIN locations l ON l.id = p.pickup_location_id
         WHERE p.donor_id = ? ORDER BY p.created_at DESC'
    );
    $stmt->execute([$userId]);

    // Split into what is happening now (live) and what is finished (past).
    $plates = $collected = 0;
    foreach ($stmt->fetchAll() as $p) {
        $p['shown'] = display_status($p['status'], $p['pickup_until']);
        if (in_array($p['shown'], ['available', 'claimed'], true)) {
            $live[] = $p;
        } else {
            $past[] = $p;
        }
        if ($p['shown'] === 'collected') {
            $collected++;
            $plates += $p['unit'] === 'plates' ? (int) $p['quantity'] : 0;
        }
        if (count($hero['photos']) < 3) {
            $hero['photos'][] = category_style($p['category'])[2];
        }
    }
    $waiting = count(array_filter($live, fn($p) => $p['shown'] === 'claimed'));

    $hero['line'] = $plates > 0
        ? 'You have helped serve <strong data-count="' . $plates . '">' . $plates . '</strong> plates of food that would otherwise have been thrown away.'
        : 'Your first donation can feed a family tonight. Post your surplus and an NGO nearby will collect it.';
    $stats = [
        ['Live now', count($live), 'leaf', 'green'],
        ['Waiting for pickup', $waiting, 'hand', 'amber'],
        ['Collected', $collected, 'check-circle', 'blue'],
        ['Plates served', $plates, 'utensils', 'slate'],
    ];
}

if ($role === 'ngo' || $role === 'volunteer') {
    $eligible = can_claim($pdo, $userId, $role);
    if (!$eligible) {
        $stmt = $role === 'volunteer'
            ? $pdo->prepare('SELECT rejected_at FROM users WHERE id = ?')
            : $pdo->prepare('SELECT MAX(rejected_at) FROM ngos WHERE owner_user_id = ?');
        $stmt->execute([$userId]);
        $rejectedAt = $stmt->fetchColumn() ?: null;
    }
    $categories = $pdo->query('SELECT id, name FROM food_categories ORDER BY name')->fetchAll();
    $cities     = $pdo->query('SELECT DISTINCT city FROM locations ORDER BY city')->fetchAll(PDO::FETCH_COLUMN);

    // Optional filters from the query string.
    $fCity = trim($_GET['city'] ?? '');
    $fArea = trim($_GET['area'] ?? '');
    $fCat  = (int) ($_GET['category'] ?? 0);

    $sql = "SELECT p.id, p.title, p.description, p.quantity, p.unit, p.pickup_from, p.pickup_until,
                   COALESCE(u.organization_name, u.full_name) AS donor, l.address, l.area, l.city, c.name AS category
            FROM food_posts p
            JOIN users u ON u.id = p.donor_id
            LEFT JOIN food_categories c ON c.id = p.category_id
            LEFT JOIN locations l ON l.id = p.pickup_location_id
            WHERE p.status = 'available' AND p.pickup_until > NOW()";
    $params = [];
    if ($fCity !== '') { $sql .= ' AND l.city = ?';   $params[] = $fCity; }
    if ($fArea !== '') { $sql .= ' AND l.area LIKE ?'; $params[] = '%' . $fArea . '%'; }
    if ($fCat > 0)     { $sql .= ' AND p.category_id = ?'; $params[] = $fCat; }
    $stmt = $pdo->prepare($sql . ' ORDER BY p.pickup_until ASC');
    $stmt->execute($params);
    $available = $stmt->fetchAll();

    $stmt = $pdo->prepare(
        'SELECT p.id, p.title, p.quantity, p.unit, p.pickup_from, p.pickup_until, p.status, c.id AS claim_id, c.claimed_at, c.collected_at,
                fc.name AS category, COALESCE(d.organization_name, d.full_name) AS donor, d.phone AS donor_phone,
                l.address, l.area, l.city, cn.name AS claim_ngo
         FROM claims c JOIN food_posts p ON p.id = c.post_id
         JOIN users d ON d.id = p.donor_id
         LEFT JOIN ngos cn ON cn.id = c.ngo_id
         LEFT JOIN locations l ON l.id = p.pickup_location_id
         LEFT JOIN food_categories fc ON fc.id = p.category_id
         WHERE c.claimed_by = ? ORDER BY c.claimed_at DESC'
    );
    $stmt->execute([$userId]);
    foreach ($stmt->fetchAll() as $c) {
        if ($c['status'] === 'claimed') {
            $activeClaims[] = $c;
        } else {
            $pastClaims[] = $c;
        }
    }

    if ($role === 'volunteer') {
        $stmt = $pdo->prepare(
            "SELECT n.name, n.is_verified FROM ngo_members m JOIN ngos n ON n.id = m.ngo_id
             WHERE m.user_id = ? AND m.member_role = 'volunteer' ORDER BY n.name"
        );
        $stmt->execute([$userId]);
        $teams = $stmt->fetchAll();
    }

    $openNow = (int) $pdo->query("SELECT COUNT(*) FROM food_posts WHERE status = 'available' AND pickup_until > NOW()")->fetchColumn();
    $soonest = $pdo->query("SELECT MIN(pickup_until) FROM food_posts WHERE status = 'available' AND pickup_until > NOW()")->fetchColumn();
    $pickedUp = 0;
    foreach ($pastClaims as $c) {
        $pickedUp += $c['unit'] === 'plates' && $c['status'] === 'collected' ? (int) $c['quantity'] : 0;
    }
    foreach (array_slice($available, 0, 3) as $p) {
        $hero['photos'][] = category_style($p['category'])[2];
    }

    if ($activeClaims) {
        $hero['line'] = 'You have <strong>' . plural(count($activeClaims), 'pickup') . '</strong> waiting. Show your pickup code to the donor when you arrive.';
    } elseif ($openNow > 0) {
        $hero['line'] = '<strong data-count="' . $openNow . '">' . $openNow . '</strong> ' . ($openNow === 1 ? 'meal is' : 'meals are')
            . ' waiting to be rescued right now. The next one closes in <strong>' . e(strtolower(str_replace(' left', '', time_left($soonest)[0]))) . '</strong>.';
    } else {
        $hero['line'] = 'Nothing is waiting right now. New food appears here the moment a donor posts it.';
    }
    $stats = [
        ['Available now', $openNow, 'leaf', 'green'],
        ['Your pickups waiting', count($activeClaims), 'hand', 'amber'],
        ['Rescued by you', count($pastClaims), 'check-circle', 'blue'],
        ['Claim status', $eligible ? 'Verified' : ($rejectedAt ? 'Rejected' : 'Pending'), 'shield', $eligible ? 'green' : ($rejectedAt ? 'red' : 'amber')],
    ];
}

if ($role === 'admin') {
    $pendingNgos = $pdo->query(
        'SELECT n.id, n.name, n.registration_no, u.email, l.address, l.area, l.city
         FROM ngos n JOIN users u ON u.id = n.owner_user_id
         LEFT JOIN locations l ON l.id = n.location_id
         WHERE n.is_verified = 0 AND n.rejected_at IS NULL'
    )->fetchAll();
    $pendingVolunteers = $pdo->query(
        "SELECT id, full_name, email, phone FROM users WHERE role = 'volunteer' AND is_verified = 0 AND rejected_at IS NULL"
    )->fetchAll();

    $totals = $pdo->query(
        "SELECT (SELECT COUNT(*) FROM users) AS users,
                (SELECT COUNT(*) FROM food_posts WHERE status = 'collected') AS collected"
    )->fetch();
    $pending = count($pendingNgos) + count($pendingVolunteers);
    $hero['line'] = $pending > 0
        ? '<strong>' . plural($pending, 'request') . '</strong> ' . ($pending === 1 ? 'is' : 'are') . ' waiting for your review. Verified accounts can start rescuing food straight away.'
        : 'All caught up. Every NGO and volunteer on the platform has been reviewed.';
    $hero['photos'] = ['assets/img/cat-event.jpg', 'assets/img/cat-cooked.jpg', 'assets/img/cat-bakery.jpg'];
    $stats = [
        ['Pending NGOs', count($pendingNgos), 'building', 'amber'],
        ['Pending volunteers', count($pendingVolunteers), 'users', 'amber'],
        ['Registered users', (int) $totals['users'], 'user', 'slate'],
        ['Pickups completed', (int) $totals['collected'], 'check-circle', 'green'],
    ];
}

if (!$hero['photos']) {
    $hero['photos'] = ['assets/img/cat-cooked.jpg', 'assets/img/cat-bakery.jpg', 'assets/img/cat-fresh.jpg'];
}

$csrf = csrf_token();
$pageTitle = 'Dashboard';
require __DIR__ . '/includes/header.php';
?>
<main class="content">
    <!-- Welcome banner -->
    <section class="hero-banner reveal">
        <div class="hero-banner-text">
            <p class="eyebrow"><?= icon('calendar') ?> <?= date('l, d F Y') ?></p>
            <h1><?= greeting() ?>, <?= e(strtok($_SESSION['name'], ' ')) ?> <span class="wave">👋</span></h1>
            <p class="hero-line"><?= $hero['line'] /* built above; dynamic parts escaped */ ?></p>
            <div class="hero-actions">
                <?php if ($role === 'donor'): ?>
                    <a class="btn btn-lg btn-ink" href="post_food.php"><?= icon('plus') ?> Post surplus food</a>
                    <a class="btn btn-lg" href="impact.php"><?= icon('leaf') ?> See the impact</a>
                <?php elseif ($role === 'admin'): ?>
                    <a class="btn btn-lg btn-ink" href="#ngos"><?= icon('shield') ?> Review requests</a>
                    <a class="btn btn-lg" href="impact.php"><?= icon('leaf') ?> Platform impact</a>
                <?php else: ?>
                    <a class="btn btn-lg btn-ink" href="#available"><?= icon('search') ?> Find food</a>
                    <a class="btn btn-lg" href="#claims"><?= icon('box') ?> My pickups</a>
                <?php endif; ?>
            </div>
        </div>
        <div class="photo-stack" aria-hidden="true">
            <?php foreach (array_slice($hero['photos'], 0, 3) as $i => $ph): ?>
                <img src="<?= e($ph) ?>" alt="" style="--i: <?= $i ?>">
            <?php endforeach; ?>
        </div>
    </section>

    <?php if ($flash): ?>
        <?php $ok = $flash['type'] === 'success'; ?>
        <div class="alert alert-<?= $ok ? 'success' : 'error' ?> toast" <?= $ok ? 'data-autohide' : '' ?>>
            <?= icon($ok ? 'check-circle' : 'alert') ?><span><?= e($flash['message']) ?></span>
        </div>
    <?php endif; ?>

    <?php if ($teams): ?>
    <div class="team-strip">
        <span class="team-strip-label"><?= icon('users') ?> Your teams</span>
        <?php foreach ($teams as $t): ?>
            <span class="team-chip">
                <?= e($t['name']) ?>
                <?php if ((int) $t['is_verified'] === 1): ?><span class="status status-available">Verified NGO</span>
                <?php else: ?><span class="status status-claimed">NGO pending</span><?php endif; ?>
            </span>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <section class="stats">
        <?php foreach ($stats as $i => [$label, $value, $ico, $tone]): ?>
        <div class="stat reveal" style="--i: <?= $i + 1 ?>">
            <span class="stat-icon tone-<?= $tone ?>"><?= icon($ico) ?></span>
            <div>
                <span class="stat-label"><?= e($label) ?></span>
                <strong class="stat-value" <?= is_int($value) ? 'data-count="' . $value . '"' : '' ?>><?= e((string) $value) ?></strong>
            </div>
        </div>
        <?php endforeach; ?>
    </section>

    <?php if ($role === 'donor'): ?>
    <!-- ================= Donor: live posts ================= -->
    <?php
    $lanes = [
        ['now',  1, 'Waiting for a claim',  'Posted and visible to NGOs. You can still edit or withdraw it.',
         array_values(array_filter($live, fn($p) => $p['shown'] === 'available'))],
        ['next', 2, 'Collector on the way', 'Someone claimed it. Enter their pickup code when they arrive.',
         array_values(array_filter($live, fn($p) => $p['shown'] === 'claimed'))],
    ];
    ?>
    <section class="lanes" id="live" aria-label="Your food, from posted to collected">
        <?php foreach ($lanes as [$laneKey, $laneStep, $laneTitle, $laneHelp, $laneItems]): ?>
        <section class="lane lane-<?= $laneKey ?>" aria-labelledby="lane-<?= $laneKey ?>">
            <header class="lane-head">
                
                <div class="lane-title-row">
                    <span class="lane-dot"></span>
                    <h2 id="lane-<?= $laneKey ?>"><?= e($laneTitle) ?></h2>
                    <span class="lane-count"><?= count($laneItems) ?></span>
                </div>
                <p class="lane-help"><?= e($laneHelp) ?></p>
            </header>
            <?php if (!$laneItems): ?>
                <div class="lane-empty">
                    <?php if ($laneKey === 'now'): ?>
                        <p>Nothing waiting. Cooked too much today?</p>
                        <a class="btn btn-primary btn-sm" href="post_food.php"><?= icon('plus') ?> Post surplus food</a>
                    <?php else: ?>
                        <p>No collector is on the way right now. Claimed food appears here with the collector's name and phone.</p>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
            <?php foreach ($laneItems as $i => $p): ?>
            <?php [, , $photo] = category_style($p['category']); [$left, $leftTone] = time_left($p['pickup_until']);
                  $claimed = $p['shown'] === 'claimed'; ?>
            <article class="live-card reveal <?= $claimed ? 'is-claimed' : '' ?>" style="--i: <?= $i ?>">
                <div class="live-photo">
                    <img src="<?= e($photo) ?>" alt="">
                    <span class="time-chip t-<?= $leftTone === 'over' ? 'urgent' : $leftTone ?>" data-until="<?= e(iso($p['pickup_until'])) ?>" data-from="<?= e(iso($p['pickup_from'])) ?>" <?= $claimed ? 'data-mode="claimed"' : '' ?>>
                        <?= icon('clock') ?> <span class="tl"><?= e($claimed && $leftTone === 'over' ? 'Pickup overdue' : $left) ?></span>
                    </span>
                </div>
                <div class="live-body">
                    <div class="live-title">
                        <h3><a class="title-link" href="post.php?id=<?= (int) $p['id'] ?>"><?= e($p['title']) ?></a></h3>
                        <span class="qty"><strong><?= e($p['quantity']) ?></strong><span><?= e($p['unit']) ?></span></span>
                    </div>
                    <p class="muted small"><?= e($p['category'] ?? 'Food') ?> &middot; <?= e(trim(($p['area'] ? $p['area'] . ', ' : '') . $p['city'])) ?> &middot; until <?= e(date('g:i a', strtotime($p['pickup_until']))) ?></p>
                    <?= tracker($p['shown']) ?>

                    <?php if ($claimed): ?>
                        <div class="handover">
                            <div class="contact">
                                <span class="person"><span class="avatar avatar-sm"><?= e(initials($p['claimer'])) ?></span><?= e($p['claimer']) ?> is coming</span>
                                <?php if ($p['claimer_ngo'] && $p['claimer_ngo'] !== $p['claimer']): ?><small><?= icon('building') ?><?= e($p['claimer_ngo']) ?></small><?php endif; ?>
                                <?php if ($p['claimer_phone']): ?><a class="phone-link" href="tel:<?= e(preg_replace('/[^0-9+]/', '', $p['claimer_phone'])) ?>"><?= icon('phone') ?><?= e($p['claimer_phone']) ?></a><?php endif; ?>
                            </div>
                            <form method="post" action="collect.php" class="code-form">
                                <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
                                <input type="hidden" name="post_id" value="<?= (int) $p['id'] ?>">
                                <input type="text" name="code" class="code-input" inputmode="numeric" autocomplete="off" maxlength="7" placeholder="Code" aria-label="Pickup code from the collector" required>
                                <button class="btn btn-primary btn-sm" type="submit"><?= icon('check') ?> Confirm</button>
                            </form>
                        </div>
                    <?php else: ?>
                        <div class="live-foot">
                            <span class="waiting"><span class="dots"><i></i><i></i><i></i></span> Waiting for an NGO to claim</span>
                            <div class="row-actions">
                                <a class="btn btn-outline btn-sm" href="post_food.php?id=<?= (int) $p['id'] ?>"><?= icon('edit') ?> Edit</a>
                                <form method="post" action="withdraw.php" class="inline-form" onsubmit="return confirm('Withdraw this post? NGOs will no longer see it.');">
                                    <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
                                    <input type="hidden" name="post_id" value="<?= (int) $p['id'] ?>">
                                    <button class="btn btn-danger-ghost btn-sm" type="submit"><?= icon('x') ?> Withdraw</button>
                                </form>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </article>
            <?php endforeach; ?>
        </section>
        <?php endforeach; ?>

        <section class="lane lane-done" aria-labelledby="lane-done">
            <header class="lane-head">
                <div class="lane-title-row">
                    <span class="lane-dot"></span>
                    <h2 id="lane-done">Recently finished</h2>
                    <span class="lane-count"><?= count($past) ?></span>
                </div>
                <p class="lane-help">Collected, expired or withdrawn. Collected food reached people who need it.</p>
            </header>
            <?php if (!$past): ?>
                <div class="lane-empty"><p>Collected, expired and withdrawn posts will be listed here.</p></div>
            <?php endif; ?>
            <ul class="done-list">
                <?php foreach (array_slice($past, 0, 5) as $p): ?>
                <li>
                    <span class="status status-<?= e($p['shown']) ?>"><?= e($p['shown']) ?></span>
                    <a class="title-link" href="post.php?id=<?= (int) $p['id'] ?>"><?= e($p['title']) ?></a>
                    <small><?= e($p['quantity']) ?> <?= e($p['unit']) ?><?php if ($p['shown'] === 'collected'): ?> &middot; by <?= e($p['claimer_ngo'] ?: $p['claimer']) ?><?php endif; ?></small>
                </li>
                <?php endforeach; ?>
            </ul>
            <?php if (count($past) > 5): ?><a class="lane-more" href="#past">All <?= count($past) ?> past posts <?= icon('arrow-right') ?></a><?php endif; ?>
        </section>
    </section>

    <!-- ================= Donor: past posts ================= -->
    <?php if ($past): ?>
    <section class="card" id="past">
        <div class="card-head">
            <div>
                <h2>Past posts</h2>
                <p class="muted small">Collected, expired and withdrawn food. Expired food can be reposted with a new pickup time.</p>
            </div>
            <span class="count-pill"><?= count($past) ?></span>
        </div>
        <ul class="history-list">
            <?php foreach ($past as $i => $p): ?>
            <?php [, , $photo] = category_style($p['category']); ?>
            <li class="reveal" style="--i: <?= min($i, 8) ?>">
                <img class="thumb" src="<?= e($photo) ?>" alt="">
                <div class="history-info">
                    <a class="title-link" href="post.php?id=<?= (int) $p['id'] ?>"><?= e($p['title']) ?></a>
                    <small><?= e($p['quantity']) ?> <?= e($p['unit']) ?> &middot; posted <?= e(fmt_time($p['created_at'])) ?>
                        <?php if ($p['shown'] === 'collected'): ?> &middot; collected by <strong><?= e($p['claimer_ngo'] ?: $p['claimer']) ?></strong><?php endif; ?></small>
                </div>
                <span class="status status-<?= e($p['shown']) ?>"><?= e($p['shown']) ?></span>
                <?php if ($p['shown'] === 'expired'): ?>
                    <a class="btn btn-outline btn-sm" href="post_food.php?id=<?= (int) $p['id'] ?>" title="Set a new pickup time"><?= icon('clock') ?> Repost</a>
                <?php else: ?>
                    <a class="btn btn-ghost btn-sm" href="post_food.php?copy=<?= (int) $p['id'] ?>" title="Start a new post with the same details"><?= icon('plus') ?> Post again</a>
                <?php endif; ?>
            </li>
            <?php endforeach; ?>
        </ul>
    </section>
    <?php endif; ?>
    <?php endif; ?>

    <?php if ($role === 'ngo' || $role === 'volunteer'): ?>
    <?php if (!$eligible && $rejectedAt): ?>
        <div class="alert alert-error"><?= icon('alert') ?>
            <span><strong>Verification rejected</strong> on <?= e(fmt_time($rejectedAt)) ?>. An admin could not verify your <?= $role === 'ngo' ? 'NGO registration' : 'volunteer account' ?>, so you cannot claim food. You can still browse what is available.</span>
        </div>
    <?php elseif (!$eligible): ?>
        <div class="alert alert-warn"><?= icon('shield') ?>
            <span><strong>Verification pending.</strong> Claiming is open to verified volunteers and members of verified NGOs. You can browse food now; an admin will verify you soon<?= $role === 'volunteer' ? ', or ask a verified NGO to add you to its team' : '' ?>.</span>
        </div>
    <?php endif; ?>

    <!-- ================= Collector: my pickups ================= -->
    <?php if ($activeClaims): ?>
    <section class="card card-highlight" id="claims">
        <div class="card-head">
            <div>
                <h2><span class="live-dot"></span> Your pickups</h2>
                <p class="muted small">Go to the address, show your pickup code, and the donor confirms the handover.</p>
            </div>
            <span class="count-pill"><?= count($activeClaims) ?> waiting</span>
        </div>
        <div class="live-grid">
            <?php foreach ($activeClaims as $i => $c): ?>
            <?php [, , $photo] = category_style($c['category']); [$left, $leftTone] = time_left($c['pickup_until']); ?>
            <article class="live-card is-claimed reveal" style="--i: <?= $i ?>">
                <div class="live-photo">
                    <img src="<?= e($photo) ?>" alt="">
                    <span class="time-chip t-<?= $leftTone === 'over' ? 'urgent' : $leftTone ?>" data-until="<?= e(iso($c['pickup_until'])) ?>" data-from="<?= e(iso($c['pickup_from'])) ?>" data-mode="claimed">
                        <?= icon('clock') ?> <span class="tl"><?= e($leftTone === 'over' ? 'Pickup overdue' : $left) ?></span>
                    </span>
                </div>
                <div class="live-body">
                    <div class="live-title">
                        <h3><a class="title-link" href="post.php?id=<?= (int) $c['id'] ?>"><?= e($c['title']) ?></a></h3>
                        <span class="qty"><strong><?= e($c['quantity']) ?></strong><span><?= e($c['unit']) ?></span></span>
                    </div>
                    <p class="muted small">Claimed <?= e(fmt_time($c['claimed_at'])) ?><?php if ($c['claim_ngo'] && $role === 'volunteer'): ?> for <?= e($c['claim_ngo']) ?><?php endif; ?></p>
                    <?= tracker('claimed') ?>
                    <div class="handover">
                        <div class="contact">
                            <strong><?= e($c['donor']) ?></strong>
                            <?php if ($c['address']): ?><small><?= icon('pin') ?><?= e($c['address']) ?>, <?= e(trim(($c['area'] ? $c['area'] . ', ' : '') . $c['city'])) ?></small><?php endif; ?>
                            <?php if ($c['donor_phone']): ?><a class="phone-link" href="tel:<?= e(preg_replace('/[^0-9+]/', '', $c['donor_phone'])) ?>"><?= icon('phone') ?><?= e($c['donor_phone']) ?></a><?php endif; ?>
                        </div>
                        <div class="handover-code">
                            <a class="code-chip code-chip-lg" href="post.php?id=<?= (int) $c['id'] ?>#pickup" title="Show this code (or the QR) to the donor">
                                <?= icon('lock') ?><?= e(format_code(pickup_code((int) $c['claim_id'], (int) $c['id'], $userId, $c['claimed_at']))) ?>
                            </a>
                            <form method="post" action="cancel.php" class="inline-form">
                                <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
                                <input type="hidden" name="post_id" value="<?= (int) $c['id'] ?>">
                                <button class="btn btn-ghost btn-sm" type="submit"><?= icon('x') ?> Cancel claim</button>
                            </form>
                        </div>
                    </div>
                </div>
            </article>
            <?php endforeach; ?>
        </div>
    </section>
    <?php endif; ?>

    <!-- ================= Collector: available food ================= -->
    <section class="card" id="available">
        <div class="card-head">
            <div>
                <h2>Food waiting to be rescued</h2>
                <p class="muted small">Ending soonest first. Claim it, then collect it before the pickup window closes.</p>
            </div>
            <span class="count-pill"><?= count($available) ?> listed</span>
        </div>

        <form method="get" class="filters" action="home.php#available">
            <div class="field-icon"><?= icon('pin') ?>
                <select name="city" aria-label="City">
                    <option value="">All cities</option>
                    <?php foreach ($cities as $c): ?>
                        <option value="<?= e($c) ?>" <?= $fCity === $c ? 'selected' : '' ?>><?= e($c) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="field-icon"><?= icon('search') ?>
                <input type="text" name="area" placeholder="Search area" value="<?= e($fArea) ?>" aria-label="Area">
            </div>
            <div class="field-icon"><?= icon('filter') ?>
                <select name="category" aria-label="Category">
                    <option value="0">All categories</option>
                    <?php foreach ($categories as $c): ?>
                        <option value="<?= (int) $c['id'] ?>" <?= $fCat === (int) $c['id'] ? 'selected' : '' ?>><?= e($c['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <button class="btn btn-primary" type="submit">Apply</button>
            <?php if ($fCity !== '' || $fArea !== '' || $fCat > 0): ?>
                <a class="btn btn-ghost" href="home.php#available"><?= icon('x') ?> Clear</a>
            <?php endif; ?>
        </form>

        <?php if (!$available): ?>
            <div class="empty">
                <span class="empty-icon"><?= icon('inbox') ?></span>
                <h3>No food available right now</h3>
                <p>New posts appear here as soon as donors share them. Try clearing filters or check back soon.</p>
            </div>
        <?php else: ?>
        <?php
        // Group the open food by urgency so the most time-critical posts are seen first.
        $groups = ['urgent' => [], 'today' => [], 'later' => []];
        foreach ($available as $p) {
            $secs = strtotime($p['pickup_until']) - time();
            $key  = $secs < 3600 ? 'urgent' : (date('Y-m-d', strtotime($p['pickup_until'])) === date('Y-m-d') ? 'today' : 'later');
            $groups[$key][] = $p;
        }
        $groupTitles = ['urgent' => 'Ending within the hour', 'today' => 'Later today', 'later' => 'Tomorrow and later'];
        ?>
        <?php foreach ($groups as $gKey => $gItems): if (!$gItems) continue; ?>
        <h3 class="group-head group-<?= $gKey ?>"><?= e($groupTitles[$gKey]) ?> <span><?= count($gItems) ?></span></h3>
        <div class="grid">
            <?php foreach ($gItems as $i => $p): ?>
            <?php [, $catClass, $photo] = category_style($p['category']); [$left, $leftTone] = time_left($p['pickup_until']); ?>
            <article class="food-card reveal" style="--i: <?= min($i, 8) ?>">
                <div class="food-top <?= $catClass ?>">
                    <img src="<?= e($photo) ?>" alt="<?= e($p['category'] ?? 'Food') ?>">
                    <?php if ($p['category']): ?><span class="tag tag-photo"><?= e($p['category']) ?></span><?php endif; ?>
                    <span class="time-chip t-<?= $leftTone ?>" data-until="<?= e(iso($p['pickup_until'])) ?>" data-from="<?= e(iso($p['pickup_from'])) ?>"><?= icon('clock') ?> <span class="tl"><?= e($left) ?></span></span>
                </div>
                <div class="food-body">
                    <h3><a class="title-link" href="post.php?id=<?= (int) $p['id'] ?>"><?= e($p['title']) ?></a></h3>
                    <?php if ($p['description']): ?><p class="food-desc"><?= e($p['description']) ?></p><?php endif; ?>
                    <ul class="food-meta">
                        <li><?= icon('building') ?><?= e($p['donor']) ?></li>
                        <?php if ($p['city']): ?><li><?= icon('pin') ?><?= e(trim(($p['area'] ? $p['area'] . ', ' : '') . $p['city'])) ?></li><?php endif; ?>
                        <li><?= icon('calendar') ?><?= e(fmt_time($p['pickup_from'])) ?> – <?= e(date('Y-m-d', strtotime($p['pickup_from'])) === date('Y-m-d', strtotime($p['pickup_until'])) ? date('g:i a', strtotime($p['pickup_until'])) : fmt_time($p['pickup_until'])) ?></li>
                    </ul>
                </div>
                <div class="food-foot">
                    <div class="qty"><strong><?= e($p['quantity']) ?></strong><span><?= e($p['unit']) ?></span></div>
                    <?php if ($eligible): ?>
                    <form method="post" action="claim.php" class="inline-form claim-form"
                          data-title="<?= e($p['title']) ?>" data-qty="<?= e($p['quantity'] . ' ' . $p['unit']) ?>"
                          data-place="<?= e(trim(($p['area'] ? $p['area'] . ', ' : '') . $p['city'])) ?> · <?= e($p['donor']) ?>"
                          data-until="<?= e(date('g:i a', strtotime($p['pickup_until']))) ?>">
                        <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
                        <input type="hidden" name="post_id" value="<?= (int) $p['id'] ?>">
                        <button class="btn btn-primary" type="submit"><?= icon('hand') ?> Claim</button>
                    </form>
                    <?php else: ?>
                        <span class="locked"><?= icon('lock') ?> Verification needed</span>
                    <?php endif; ?>
                </div>
            </article>
            <?php endforeach; ?>
        </div>
        <?php endforeach; ?>
        <?php endif; ?>
    </section>

    <!-- ================= Collector: past pickups ================= -->
    <section class="card" <?= $activeClaims ? '' : 'id="claims"' ?>>
        <div class="card-head">
            <div>
                <h2>Food you rescued</h2>
                <p class="muted small">Every completed pickup, newest first.</p>
            </div>
            <span class="count-pill"><?= count($pastClaims) ?></span>
        </div>
        <?php if (!$pastClaims): ?>
            <div class="empty empty-sm">
                <span class="empty-icon"><?= icon('box') ?></span>
                <h3>No pickups yet</h3>
                <p>Claim food above and it will be listed here once the donor confirms your pickup code.</p>
            </div>
        <?php else: ?>
        <ul class="history-list">
            <?php foreach ($pastClaims as $i => $c): ?>
            <?php [, , $photo] = category_style($c['category']); ?>
            <li class="reveal" style="--i: <?= min($i, 8) ?>">
                <img class="thumb" src="<?= e($photo) ?>" alt="">
                <div class="history-info">
                    <a class="title-link" href="post.php?id=<?= (int) $c['id'] ?>"><?= e($c['title']) ?></a>
                    <small><?= e($c['quantity']) ?> <?= e($c['unit']) ?> from <?= e($c['donor']) ?> &middot; collected <?= e(fmt_time($c['collected_at'])) ?></small>
                </div>
                <span class="status status-<?= e($c['status']) ?>"><?= e($c['status']) ?></span>
            </li>
            <?php endforeach; ?>
        </ul>
        <?php endif; ?>
    </section>
    <?php endif; ?>

    <?php if ($role === 'admin'): ?>
    <div class="two-col">
        <section class="card reveal" id="ngos">
            <div class="card-head">
                <div>
                    <h2>NGO requests</h2>
                    <p class="muted small">Check the registration number and address before verifying.</p>
                </div>
                <span class="count-pill"><?= count($pendingNgos) ?> pending</span>
            </div>
            <?php if (!$pendingNgos): ?>
                <div class="empty empty-sm">
                    <span class="empty-icon"><?= icon('check-circle') ?></span>
                    <h3>All caught up</h3>
                    <p>No NGOs are waiting for verification.</p>
                </div>
            <?php else: ?>
            <ul class="request-list">
                <?php foreach ($pendingNgos as $n): ?>
                <li>
                    <span class="avatar avatar-ngo"><?= e(initials($n['name'])) ?></span>
                    <div class="req-info">
                        <strong><?= e($n['name']) ?></strong>
                        <small><span class="meta"><?= icon('id') ?><?= e($n['registration_no']) ?></span><span class="meta"><?= icon('mail') ?><?= e($n['email']) ?></span></small>
                        <?php if ($n['city']): ?><small><span class="meta"><?= icon('pin') ?><?= e($n['address']) ?>, <?= e(trim(($n['area'] ? $n['area'] . ', ' : '') . $n['city'])) ?></span></small><?php endif; ?>
                    </div>
                    <form method="post" action="verify.php" class="inline-form">
                        <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
                        <input type="hidden" name="type" value="ngo">
                        <input type="hidden" name="id" value="<?= (int) $n['id'] ?>">
                        <div class="row-actions">
                            <button class="btn btn-danger-ghost btn-sm" type="submit" name="action" value="reject" onclick="return confirm('Reject this request? They will not be able to claim food.');"><?= icon('x') ?> Reject</button>
                            <button class="btn btn-primary btn-sm" type="submit" name="action" value="verify"><?= icon('check') ?> Verify</button>
                        </div>
                    </form>
                </li>
                <?php endforeach; ?>
            </ul>
            <?php endif; ?>
        </section>

        <section class="card reveal" style="--i: 1" id="volunteers">
            <div class="card-head">
                <div>
                    <h2>Volunteer requests</h2>
                    <p class="muted small">Verified volunteers can claim food on their own.</p>
                </div>
                <span class="count-pill"><?= count($pendingVolunteers) ?> pending</span>
            </div>
            <?php if (!$pendingVolunteers): ?>
                <div class="empty empty-sm">
                    <span class="empty-icon"><?= icon('check-circle') ?></span>
                    <h3>All caught up</h3>
                    <p>No volunteers are waiting for verification.</p>
                </div>
            <?php else: ?>
            <ul class="request-list">
                <?php foreach ($pendingVolunteers as $v): ?>
                <li>
                    <span class="avatar avatar-volunteer"><?= e(initials($v['full_name'])) ?></span>
                    <div class="req-info">
                        <strong><?= e($v['full_name']) ?></strong>
                        <small><span class="meta"><?= icon('mail') ?><?= e($v['email']) ?></span><?php if ($v['phone']): ?><span class="meta"><?= icon('phone') ?><?= e($v['phone']) ?></span><?php endif; ?></small>
                    </div>
                    <form method="post" action="verify.php" class="inline-form">
                        <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
                        <input type="hidden" name="type" value="volunteer">
                        <input type="hidden" name="id" value="<?= (int) $v['id'] ?>">
                        <div class="row-actions">
                            <button class="btn btn-danger-ghost btn-sm" type="submit" name="action" value="reject" onclick="return confirm('Reject this request? They will not be able to claim food.');"><?= icon('x') ?> Reject</button>
                            <button class="btn btn-primary btn-sm" type="submit" name="action" value="verify"><?= icon('check') ?> Verify</button>
                        </div>
                    </form>
                </li>
                <?php endforeach; ?>
            </ul>
            <?php endif; ?>
        </section>
    </div>
    <?php endif; ?>
</main>
<?php require __DIR__ . '/includes/footer.php'; ?>
