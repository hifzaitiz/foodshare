<?php
require_once __DIR__ . '/includes/auth.php';
require_login();

$pdo    = db();
$userId = $_SESSION['user_id'];
$role   = $_SESSION['role'];

$myPosts = $available = $myClaims = $pendingNgos = $pendingVolunteers = $categories = $cities = [];
$eligible = false;

// Alerts left by claim.php, collect.php, etc.
$flash = $_SESSION['flash'] ?? null;
unset($_SESSION['flash']);

if ($role === 'donor') {
    $stmt = $pdo->prepare(
        'SELECT p.id, p.title, p.quantity, p.unit, p.pickup_until, p.status, u.full_name AS claimer
         FROM food_posts p
         LEFT JOIN claims c ON c.post_id = p.id
         LEFT JOIN users u ON u.id = c.claimed_by
         WHERE p.donor_id = ? ORDER BY p.created_at DESC'
    );
    $stmt->execute([$userId]);
    $myPosts = $stmt->fetchAll();
}

if ($role === 'ngo' || $role === 'volunteer') {
    $eligible   = can_claim($pdo, $userId, $role);
    $categories = $pdo->query('SELECT id, name FROM food_categories ORDER BY name')->fetchAll();
    $cities     = $pdo->query('SELECT DISTINCT city FROM locations ORDER BY city')->fetchAll(PDO::FETCH_COLUMN);

    // Optional filters from the query string.
    $fCity = trim($_GET['city'] ?? '');
    $fArea = trim($_GET['area'] ?? '');
    $fCat  = (int) ($_GET['category'] ?? 0);

    $sql = "SELECT p.id, p.title, p.quantity, p.unit, p.pickup_from, p.pickup_until,
                   COALESCE(u.organization_name, u.full_name) AS donor, l.area, l.city, c.name AS category
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
        'SELECT p.id, p.title, p.quantity, p.unit, p.pickup_until, p.status
         FROM claims c JOIN food_posts p ON p.id = c.post_id
         WHERE c.claimed_by = ? ORDER BY c.claimed_at DESC'
    );
    $stmt->execute([$userId]);
    $myClaims = $stmt->fetchAll();
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
}

$csrf = csrf_token();
$pageTitle = 'Home';
require __DIR__ . '/includes/header.php';
?>
<main class="container">
    <h1>Hello, <?= e($_SESSION['name']) ?></h1>
    <p class="muted">
        <?php if ($role === 'donor'): ?>Share your surplus food and track who collects it.
        <?php elseif ($role === 'admin'): ?>Verify NGOs and volunteers and keep the platform safe.
        <?php else: ?>Find surplus food near you and claim it before pickup time ends.
        <?php endif; ?>
    </p>

    <?php if ($flash): ?>
        <div class="alert alert-<?= $flash['type'] === 'success' ? 'success' : 'error' ?>"><?= e($flash['message']) ?></div>
    <?php endif; ?>

    <?php if ($role === 'donor'): ?>
    <section class="card">
        <div class="card-head">
            <h2>My food posts</h2>
            <a class="btn btn-primary" href="post_food.php">+ Post food</a>
        </div>
        <?php if (!$myPosts): ?>
            <p class="muted">You have not posted any food yet.</p>
        <?php else: ?>
        <div class="table-wrap"><table>
            <tr><th>Food</th><th>Quantity</th><th>Pickup until</th><th>Status</th><th>Claimed by</th><th></th></tr>
            <?php foreach ($myPosts as $p): ?>
            <tr>
                <td><?= e($p['title']) ?></td>
                <td><?= e($p['quantity']) ?> <?= e($p['unit']) ?></td>
                <td><?= e(fmt_time($p['pickup_until'])) ?></td>
                <td><span class="status status-<?= e($p['status']) ?>"><?= e($p['status']) ?></span></td>
                <td><?= e($p['claimer'] ?? '-') ?></td>
                <td>
                    <?php if ($p['status'] === 'claimed'): ?>
                    <form method="post" action="collect.php" class="inline-form">
                        <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
                        <input type="hidden" name="post_id" value="<?= (int) $p['id'] ?>">
                        <button class="btn btn-primary btn-sm" type="submit">Mark collected</button>
                    </form>
                    <?php endif; ?>
                </td>
            </tr>
            <?php endforeach; ?>
        </table></div>
        <?php endif; ?>
    </section>
    <?php endif; ?>

    <?php if ($role === 'ngo' || $role === 'volunteer'): ?>
    <?php if (!$eligible): ?>
        <div class="alert alert-error">
            You cannot claim food yet. Claiming is open to verified volunteers and members of verified NGOs. Please wait for an admin to verify you.
        </div>
    <?php endif; ?>

    <section class="card">
        <h2>Available food</h2>
        <form method="get" class="filters">
            <select name="city" aria-label="City">
                <option value="">All cities</option>
                <?php foreach ($cities as $c): ?>
                    <option value="<?= e($c) ?>" <?= $fCity === $c ? 'selected' : '' ?>><?= e($c) ?></option>
                <?php endforeach; ?>
            </select>
            <input type="text" name="area" placeholder="Area" value="<?= e($fArea) ?>" aria-label="Area">
            <select name="category" aria-label="Category">
                <option value="0">All categories</option>
                <?php foreach ($categories as $c): ?>
                    <option value="<?= (int) $c['id'] ?>" <?= $fCat === (int) $c['id'] ? 'selected' : '' ?>><?= e($c['name']) ?></option>
                <?php endforeach; ?>
            </select>
            <button class="btn btn-primary" type="submit">Filter</button>
            <a class="btn btn-outline" href="home.php">Clear</a>
        </form>

        <?php if (!$available): ?>
            <p class="muted">No food is available right now. Check back soon.</p>
        <?php else: ?>
        <div class="grid">
            <?php foreach ($available as $p): ?>
            <article class="food-card">
                <h3><?= e($p['title']) ?></h3>
                <p class="muted"><?= e($p['donor']) ?><?= $p['area'] ? ' &middot; ' . e($p['area']) . ', ' . e($p['city']) : '' ?></p>
                <p><strong><?= e($p['quantity']) ?></strong> <?= e($p['unit']) ?>
                    <?php if ($p['category']): ?><span class="tag"><?= e($p['category']) ?></span><?php endif; ?></p>
                <p class="muted small">Pickup: <?= e(fmt_time($p['pickup_from'])) ?> to <?= e(fmt_time($p['pickup_until'])) ?></p>
                <?php if ($eligible): ?>
                <form method="post" action="claim.php">
                    <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
                    <input type="hidden" name="post_id" value="<?= (int) $p['id'] ?>">
                    <button class="btn btn-primary btn-block" type="submit">Claim</button>
                </form>
                <?php endif; ?>
            </article>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </section>

    <section class="card">
        <h2>My claims</h2>
        <?php if (!$myClaims): ?>
            <p class="muted">You have not claimed any food yet.</p>
        <?php else: ?>
        <div class="table-wrap"><table>
            <tr><th>Food</th><th>Quantity</th><th>Pickup until</th><th>Status</th><th></th></tr>
            <?php foreach ($myClaims as $c): ?>
            <tr>
                <td><?= e($c['title']) ?></td>
                <td><?= e($c['quantity']) ?> <?= e($c['unit']) ?></td>
                <td><?= e(fmt_time($c['pickup_until'])) ?></td>
                <td><span class="status status-<?= e($c['status']) ?>"><?= e($c['status']) ?></span></td>
                <td>
                    <?php if ($c['status'] === 'claimed'): ?>
                    <form method="post" action="cancel.php" class="inline-form">
                        <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
                        <input type="hidden" name="post_id" value="<?= (int) $c['id'] ?>">
                        <button class="btn btn-outline btn-sm" type="submit">Cancel claim</button>
                    </form>
                    <?php endif; ?>
                </td>
            </tr>
            <?php endforeach; ?>
        </table></div>
        <?php endif; ?>
    </section>
    <?php endif; ?>

    <?php if ($role === 'admin'): ?>
    <section class="card">
        <h2>NGOs awaiting verification</h2>
        <?php if (!$pendingNgos): ?>
            <p class="muted">No pending NGOs.</p>
        <?php else: ?>
        <div class="table-wrap"><table>
            <tr><th>NGO</th><th>Registration no.</th><th>Owner email</th><th></th></tr>
            <?php foreach ($pendingNgos as $n): ?>
            <tr>
                <td><?= e($n['name']) ?></td>
                <td><?= e($n['registration_no']) ?></td>
                <td><?= e($n['email']) ?></td>
                <td>
                    <form method="post" action="verify.php" class="inline-form">
                        <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
                        <input type="hidden" name="type" value="ngo">
                        <input type="hidden" name="id" value="<?= (int) $n['id'] ?>">
                        <button class="btn btn-primary btn-sm" type="submit">Verify</button>
                    </form>
                </td>
            </tr>
            <?php endforeach; ?>
        </table></div>
        <?php endif; ?>
    </section>

    <section class="card">
        <h2>Volunteers awaiting verification</h2>
        <?php if (!$pendingVolunteers): ?>
            <p class="muted">No pending volunteers.</p>
        <?php else: ?>
        <div class="table-wrap"><table>
            <tr><th>Name</th><th>Email</th><th>Phone</th><th></th></tr>
            <?php foreach ($pendingVolunteers as $v): ?>
            <tr>
                <td><?= e($v['full_name']) ?></td>
                <td><?= e($v['email']) ?></td>
                <td><?= e($v['phone'] ?? '-') ?></td>
                <td>
                    <form method="post" action="verify.php" class="inline-form">
                        <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
                        <input type="hidden" name="type" value="volunteer">
                        <input type="hidden" name="id" value="<?= (int) $v['id'] ?>">
                        <button class="btn btn-primary btn-sm" type="submit">Verify</button>
                    </form>
                </td>
            </tr>
            <?php endforeach; ?>
        </table></div>
        <?php endif; ?>
    </section>
    <?php endif; ?>
</main>
<?php require __DIR__ . '/includes/footer.php'; ?>
