<?php
require_once __DIR__ . '/includes/auth.php';
require_login();

$pdo    = db();
$userId = $_SESSION['user_id'];
$role   = $_SESSION['role'];

$myPosts = $available = $myClaims = $pendingNgos = $pendingVolunteers = $categories = $cities = [];
$eligible = false;
$stats = [];   // [label, value, icon, colour] cards shown at the top
$fCity = $fArea = '';
$fCat  = 0;

// Alerts left by claim.php, collect.php, etc.
$flash = $_SESSION['flash'] ?? null;
unset($_SESSION['flash']);

if ($role === 'donor') {
    $stmt = $pdo->prepare(
        'SELECT p.id, p.title, p.quantity, p.unit, p.pickup_until, p.status, u.full_name AS claimer, c.name AS category
         FROM food_posts p
         LEFT JOIN claims cl ON cl.post_id = p.id
         LEFT JOIN users u ON u.id = cl.claimed_by
         LEFT JOIN food_categories c ON c.id = p.category_id
         WHERE p.donor_id = ? ORDER BY p.created_at DESC'
    );
    $stmt->execute([$userId]);
    $myPosts = $stmt->fetchAll();

    $byStatus = array_count_values(array_column($myPosts, 'status'));
    $stats = [
        ['Total posts', count($myPosts), 'box', 'slate'],
        ['Available', $byStatus['available'] ?? 0, 'leaf', 'green'],
        ['Claimed', $byStatus['claimed'] ?? 0, 'hand', 'amber'],
        ['Collected', $byStatus['collected'] ?? 0, 'check-circle', 'blue'],
    ];
}

if ($role === 'ngo' || $role === 'volunteer') {
    $eligible   = can_claim($pdo, $userId, $role);
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
        'SELECT p.id, p.title, p.quantity, p.unit, p.pickup_until, p.status, c.claimed_at, c.collected_at, fc.name AS category
         FROM claims c JOIN food_posts p ON p.id = c.post_id
         LEFT JOIN food_categories fc ON fc.id = p.category_id
         WHERE c.claimed_by = ? ORDER BY c.claimed_at DESC'
    );
    $stmt->execute([$userId]);
    $myClaims = $stmt->fetchAll();

    $openNow  = (int) $pdo->query("SELECT COUNT(*) FROM food_posts WHERE status = 'available' AND pickup_until > NOW()")->fetchColumn();
    $byStatus = array_count_values(array_column($myClaims, 'status'));
    $stats = [
        ['Available now', $openNow, 'leaf', 'green'],
        ['Active claims', $byStatus['claimed'] ?? 0, 'hand', 'amber'],
        ['Collected', $byStatus['collected'] ?? 0, 'check-circle', 'blue'],
        ['Claim status', $eligible ? 'Verified' : 'Pending', 'shield', $eligible ? 'green' : 'red'],
    ];
}

if ($role === 'admin') {
    $pendingNgos = $pdo->query(
        'SELECT n.id, n.name, n.registration_no, u.email
         FROM ngos n JOIN users u ON u.id = n.owner_user_id
         WHERE n.is_verified = 0'
    )->fetchAll();
    $pendingVolunteers = $pdo->query(
        "SELECT id, full_name, email, phone FROM users WHERE role = 'volunteer' AND is_verified = 0"
    )->fetchAll();

    $totals = $pdo->query(
        "SELECT (SELECT COUNT(*) FROM users) AS users,
                (SELECT COUNT(*) FROM food_posts WHERE status = 'collected') AS collected"
    )->fetch();
    $stats = [
        ['Pending NGOs', count($pendingNgos), 'building', 'amber'],
        ['Pending volunteers', count($pendingVolunteers), 'users', 'amber'],
        ['Registered users', (int) $totals['users'], 'user', 'slate'],
        ['Pickups completed', (int) $totals['collected'], 'check-circle', 'green'],
    ];
}

$subtitles = [
    'donor'     => 'Share your surplus food and track who collects it.',
    'admin'     => 'Verify NGOs and volunteers and keep the platform safe.',
    'ngo'       => 'Find surplus food near you and claim it before pickup time ends.',
    'volunteer' => 'Find surplus food near you and claim it before pickup time ends.',
];

$csrf = csrf_token();
$pageTitle = 'Dashboard';
require __DIR__ . '/includes/header.php';
?>
<main class="content">
    <header class="page-head">
        <div>
            <p class="eyebrow"><?= icon('calendar') ?> <?= date('l, d F Y') ?></p>
            <h1><?= greeting() ?>, <?= e(strtok($_SESSION['name'], ' ')) ?> 👋</h1>
            <p class="muted"><?= e($subtitles[$role] ?? '') ?></p>
        </div>
        <?php if ($role === 'donor'): ?>
            <a class="btn btn-primary btn-lg" href="post_food.php"><?= icon('plus') ?> Post food</a>
        <?php endif; ?>
    </header>

    <?php if ($flash): ?>
        <?php $ok = $flash['type'] === 'success'; ?>
        <div class="alert alert-<?= $ok ? 'success' : 'error' ?>" <?= $ok ? 'data-autohide' : '' ?>>
            <?= icon($ok ? 'check-circle' : 'alert') ?><span><?= e($flash['message']) ?></span>
        </div>
    <?php endif; ?>

    <section class="stats">
        <?php foreach ($stats as [$label, $value, $ico, $tone]): ?>
        <div class="stat">
            <span class="stat-icon tone-<?= $tone ?>"><?= icon($ico) ?></span>
            <div>
                <span class="stat-label"><?= e($label) ?></span>
                <strong class="stat-value"><?= e((string) $value) ?></strong>
            </div>
        </div>
        <?php endforeach; ?>
    </section>

    <?php if ($role === 'donor'): ?>
    <section class="card" id="posts">
        <div class="card-head">
            <div>
                <h2>My food posts</h2>
                <p class="muted small">Everything you have shared, newest first.</p>
            </div>
        </div>
        <?php if (!$myPosts): ?>
            <div class="empty">
                <span class="empty-icon"><?= icon('utensils') ?></span>
                <h3>No food posted yet</h3>
                <p>Got leftovers today? Post them and nearby NGOs will be notified on their dashboard.</p>
                <a class="btn btn-primary" href="post_food.php"><?= icon('plus') ?> Post your first food</a>
            </div>
        <?php else: ?>
        <div class="table-wrap"><table>
            <thead><tr><th>Food</th><th>Quantity</th><th>Pickup window</th><th>Status</th><th>Claimed by</th><th></th></tr></thead>
            <tbody>
            <?php foreach ($myPosts as $p): ?>
                <?php [, , $photo] = category_style($p['category']); [$left, $leftTone] = time_left($p['pickup_until']); ?>
                <tr>
                    <td><div class="cell-title"><img class="thumb" src="<?= e($photo) ?>" alt=""><div><strong><?= e($p['title']) ?></strong><small><?= e($p['category'] ?? 'Uncategorised') ?></small></div></div></td>
                    <td><strong><?= e($p['quantity']) ?></strong> <?= e($p['unit']) ?></td>
                    <td><?= e(fmt_time($p['pickup_until'])) ?>
                        <?php if ($p['status'] !== 'collected'): ?><small class="time-text t-<?= $leftTone ?>"><?= e($left) ?></small><?php endif; ?></td>
                    <td><span class="status status-<?= e($p['status']) ?>"><?= e($p['status']) ?></span></td>
                    <td><?php if ($p['claimer']): ?><span class="person"><span class="avatar avatar-sm"><?= e(initials($p['claimer'])) ?></span><?= e($p['claimer']) ?></span><?php else: ?><span class="muted">-</span><?php endif; ?></td>
                    <td class="cell-action">
                        <?php if ($p['status'] === 'claimed'): ?>
                        <form method="post" action="collect.php" class="inline-form">
                            <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
                            <input type="hidden" name="post_id" value="<?= (int) $p['id'] ?>">
                            <button class="btn btn-primary btn-sm" type="submit"><?= icon('check') ?> Mark collected</button>
                        </form>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table></div>
        <?php endif; ?>
    </section>
    <?php endif; ?>

    <?php if ($role === 'ngo' || $role === 'volunteer'): ?>
    <?php if (!$eligible): ?>
        <div class="alert alert-warn"><?= icon('shield') ?>
            <span><strong>Verification pending.</strong> Claiming is open to verified volunteers and members of verified NGOs. You can browse food now; an admin will verify you soon.</span>
        </div>
    <?php endif; ?>

    <section class="card" id="available">
        <div class="card-head">
            <div>
                <h2>Available food</h2>
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
        <div class="grid">
            <?php foreach ($available as $p): ?>
            <?php [, $catClass, $photo] = category_style($p['category']); [$left, $leftTone] = time_left($p['pickup_until']); ?>
            <article class="food-card">
                <div class="food-top <?= $catClass ?>">
                    <img src="<?= e($photo) ?>" alt="<?= e($p['category'] ?? 'Food') ?>">
                    <?php if ($p['category']): ?><span class="tag tag-photo"><?= e($p['category']) ?></span><?php endif; ?>
                    <span class="time-chip t-<?= $leftTone ?>"><?= icon('clock') ?> <?= e($left) ?></span>
                </div>
                <div class="food-body">
                    <h3><?= e($p['title']) ?></h3>
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
                    <form method="post" action="claim.php" class="inline-form">
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
        <?php endif; ?>
    </section>

    <section class="card" id="claims">
        <div class="card-head">
            <div>
                <h2>My claims</h2>
                <p class="muted small">Food you have claimed. The donor marks it collected at pickup.</p>
            </div>
        </div>
        <?php if (!$myClaims): ?>
            <div class="empty">
                <span class="empty-icon"><?= icon('box') ?></span>
                <h3>No claims yet</h3>
                <p>When you claim food from the list above, it will show up here.</p>
            </div>
        <?php else: ?>
        <div class="table-wrap"><table>
            <thead><tr><th>Food</th><th>Quantity</th><th>Pickup until</th><th>Status</th><th></th></tr></thead>
            <tbody>
            <?php foreach ($myClaims as $c): ?>
                <?php [$left, $leftTone] = time_left($c['pickup_until']); [, , $photo] = category_style($c['category']); ?>
                <tr>
                    <td><div class="cell-title"><img class="thumb" src="<?= e($photo) ?>" alt=""><div><strong><?= e($c['title']) ?></strong><small>Claimed <?= e(fmt_time($c['claimed_at'])) ?></small></div></div></td>
                    <td><strong><?= e($c['quantity']) ?></strong> <?= e($c['unit']) ?></td>
                    <td><?= e(fmt_time($c['pickup_until'])) ?>
                        <?php if ($c['status'] === 'claimed'): ?><small class="time-text t-<?= $leftTone ?>"><?= e($left) ?></small><?php endif; ?></td>
                    <td><span class="status status-<?= e($c['status']) ?>"><?= e($c['status']) ?></span></td>
                    <td class="cell-action">
                        <?php if ($c['status'] === 'claimed'): ?>
                        <form method="post" action="cancel.php" class="inline-form">
                            <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
                            <input type="hidden" name="post_id" value="<?= (int) $c['id'] ?>">
                            <button class="btn btn-danger-ghost btn-sm" type="submit"><?= icon('x') ?> Cancel claim</button>
                        </form>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table></div>
        <?php endif; ?>
    </section>
    <?php endif; ?>

    <?php if ($role === 'admin'): ?>
    <div class="two-col">
        <section class="card" id="ngos">
            <div class="card-head">
                <div>
                    <h2>NGO requests</h2>
                    <p class="muted small">Check the registration number before verifying.</p>
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
                        <small><?= icon('id') ?><?= e($n['registration_no']) ?> &nbsp; <?= icon('mail') ?><?= e($n['email']) ?></small>
                    </div>
                    <form method="post" action="verify.php" class="inline-form">
                        <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
                        <input type="hidden" name="type" value="ngo">
                        <input type="hidden" name="id" value="<?= (int) $n['id'] ?>">
                        <button class="btn btn-primary btn-sm" type="submit"><?= icon('check') ?> Verify</button>
                    </form>
                </li>
                <?php endforeach; ?>
            </ul>
            <?php endif; ?>
        </section>

        <section class="card" id="volunteers">
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
                        <small><?= icon('mail') ?><?= e($v['email']) ?><?php if ($v['phone']): ?> &nbsp; <?= icon('phone') ?><?= e($v['phone']) ?><?php endif; ?></small>
                    </div>
                    <form method="post" action="verify.php" class="inline-form">
                        <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
                        <input type="hidden" name="type" value="volunteer">
                        <input type="hidden" name="id" value="<?= (int) $v['id'] ?>">
                        <button class="btn btn-primary btn-sm" type="submit"><?= icon('check') ?> Verify</button>
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
