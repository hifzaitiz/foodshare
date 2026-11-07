<?php
require_once __DIR__ . '/includes/auth.php';
require_login();

$pdo    = db();
$userId = $_SESSION['user_id'];
$role   = $_SESSION['role'];
$postId = (int) ($_GET['id'] ?? 0);

$stmt = $pdo->prepare(
    "SELECT p.*, fc.name AS category, l.address, l.area, l.city,
            COALESCE(d.organization_name, d.full_name) AS donor, d.phone AS donor_phone,
            cl.claimed_by, cl.claimed_at, cl.collected_at,
            u.full_name AS claimer, u.phone AS claimer_phone, n.name AS claimer_ngo
     FROM food_posts p
     JOIN users d ON d.id = p.donor_id
     LEFT JOIN food_categories fc ON fc.id = p.category_id
     LEFT JOIN locations l ON l.id = p.pickup_location_id
     LEFT JOIN claims cl ON cl.post_id = p.id
     LEFT JOIN users u ON u.id = cl.claimed_by
     LEFT JOIN ngos n ON n.id = cl.ngo_id
     WHERE p.id = ?"
);
$stmt->execute([$postId]);
$post = $stmt->fetch();

$isOwner   = $post && (int) $post['donor_id'] === $userId;
$isClaimer = $post && (int) $post['claimed_by'] === $userId;
$isAdmin   = $role === 'admin';
$shown     = $post ? display_status($post['status'], $post['pickup_until']) : '';

// Who may open this page: the donor, the current claimer, the admin, or any
// NGO/volunteer while the post is still open for claiming.
$canView = $post && ($isOwner || $isClaimer || $isAdmin
    || (in_array($role, ['ngo', 'volunteer'], true) && $shown === 'available'));
if (!$canView) {
    flash('error', 'That post was not found or is no longer available.');
    header('Location: home.php');
    exit;
}
$seesContacts = $isOwner || $isClaimer || $isAdmin;   // full address and phone numbers

// Timeline from post_status_logs, oldest first.
$stmt = $pdo->prepare(
    'SELECT s.old_status, s.new_status, s.changed_at, u.full_name, u.role
     FROM post_status_logs s JOIN users u ON u.id = s.changed_by
     WHERE s.post_id = ? ORDER BY s.changed_at, s.id'
);
$stmt->execute([$postId]);
$events = [];
foreach ($stmt->fetchAll() as $log) {
    $who = e($log['full_name']);
    [$label, $ico, $tone] = match ([$log['old_status'], $log['new_status']]) {
        [null, 'available']          => ["Posted by <strong>$who</strong>", 'plus', 'slate'],
        ['available', 'claimed']     => ["Claimed by <strong>$who</strong>", 'hand', 'amber'],
        ['claimed', 'available']     => ["Claim cancelled by <strong>$who</strong>, food available again", 'x', 'red'],
        ['claimed', 'collected']     => ["Pickup confirmed by <strong>$who</strong>", 'check-circle', 'blue'],
        ['available', 'withdrawn']   => ["Withdrawn by <strong>$who</strong>", 'x', 'red'],
        default                      => ["Status changed to " . e($log['new_status']) . " by <strong>$who</strong>", 'clock', 'slate'],
    };
    $events[] = ['label' => $label, 'icon' => $ico, 'tone' => $tone, 'at' => $log['changed_at']];
}
// Expiry is calculated, not stored, so add it as a final step when it applies.
if ($shown === 'expired') {
    $events[] = ['label' => 'Pickup window closed before anyone claimed it', 'icon' => 'clock', 'tone' => 'slate', 'at' => $post['pickup_until']];
}

$eligible = in_array($role, ['ngo', 'volunteer'], true) && can_claim($pdo, $userId, $role);
[, , $photo] = category_style($post['category']);
[$left, $leftTone] = time_left($post['pickup_until']);
$csrf = csrf_token();

$pageTitle = $post['title'];
$activeNav = 'home';
require __DIR__ . '/includes/header.php';
?>
<main class="content">
    <header class="page-head">
        <div>
            <p class="eyebrow"><a href="home.php">Dashboard</a> / Post #<?= (int) $post['id'] ?></p>
            <h1><?= e($post['title']) ?></h1>
            <p class="muted"><?= e($post['category'] ?? 'Uncategorised') ?> &middot; posted <?= e(fmt_time($post['created_at'])) ?></p>
        </div>
        <span class="status status-lg status-<?= e($shown) ?>"><?= e($shown) ?></span>
    </header>

    <div class="detail-layout">
        <div>
            <section class="card detail-card">
                <img class="detail-photo" src="<?= e($photo) ?>" alt="<?= e($post['category'] ?? 'Food') ?>">
                <?php if ($post['description']): ?><p class="detail-desc"><?= e($post['description']) ?></p><?php endif; ?>

                <dl class="facts">
                    <div><dt><?= icon('box') ?> Quantity</dt><dd><?= e($post['quantity']) ?> <?= e($post['unit']) ?></dd></div>
                    <div><dt><?= icon('calendar') ?> Pickup window</dt><dd><?= e(fmt_time($post['pickup_from'])) ?> – <?= e(fmt_time($post['pickup_until'])) ?>
                        <?php if (in_array($shown, ['available', 'claimed'], true)): ?><small class="time-text t-<?= $leftTone === 'over' ? 'urgent' : $leftTone ?>"><?= e($leftTone === 'over' ? 'Pickup overdue' : $left) ?></small><?php endif; ?></dd></div>
                    <div><dt><?= icon('building') ?> Donor</dt><dd><?= e($post['donor']) ?>
                        <?php if ($seesContacts && $post['donor_phone']): ?><a class="phone-link" href="tel:<?= e(preg_replace('/[^0-9+]/', '', $post['donor_phone'])) ?>"><?= icon('phone') ?><?= e($post['donor_phone']) ?></a><?php endif; ?></dd></div>
                    <div><dt><?= icon('pin') ?> Pickup location</dt><dd>
                        <?php if ($seesContacts && $post['address']): ?><?= e($post['address']) ?>, <?php endif; ?><?= e(trim(($post['area'] ? $post['area'] . ', ' : '') . $post['city'])) ?>
                        <?php if (!$seesContacts): ?><small>Full address is shown after you claim.</small><?php endif; ?></dd></div>
                    <?php if ($post['claimer'] && $seesContacts): ?>
                    <div><dt><?= icon('hand') ?> Claimed by</dt><dd><?= e($post['claimer']) ?>
                        <?php if ($post['claimer_ngo'] && $post['claimer_ngo'] !== $post['claimer']): ?><small>for <?= e($post['claimer_ngo']) ?></small><?php endif; ?>
                        <?php if ($post['claimer_phone']): ?><a class="phone-link" href="tel:<?= e(preg_replace('/[^0-9+]/', '', $post['claimer_phone'])) ?>"><?= icon('phone') ?><?= e($post['claimer_phone']) ?></a><?php endif; ?></dd></div>
                    <?php endif; ?>
                </dl>

                <div class="detail-actions">
                    <?php if ($isOwner && $post['status'] === 'available'): ?>
                        <a class="btn btn-outline" href="post_food.php?id=<?= (int) $post['id'] ?>"><?= icon('edit') ?> Edit</a>
                        <form method="post" action="withdraw.php" class="inline-form" onsubmit="return confirm('Withdraw this post? NGOs will no longer see it.');">
                            <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
                            <input type="hidden" name="post_id" value="<?= (int) $post['id'] ?>">
                            <button class="btn btn-danger-ghost" type="submit"><?= icon('x') ?> Withdraw</button>
                        </form>
                    <?php endif; ?>
                    <?php if ($isOwner && $post['status'] === 'claimed'): ?>
                        <form method="post" action="collect.php" class="inline-form">
                            <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
                            <input type="hidden" name="post_id" value="<?= (int) $post['id'] ?>">
                            <button class="btn btn-primary" type="submit"><?= icon('check') ?> Mark collected</button>
                        </form>
                    <?php endif; ?>
                    <?php if ($isClaimer && $post['status'] === 'claimed'): ?>
                        <form method="post" action="cancel.php" class="inline-form">
                            <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
                            <input type="hidden" name="post_id" value="<?= (int) $post['id'] ?>">
                            <button class="btn btn-danger-ghost" type="submit"><?= icon('x') ?> Cancel claim</button>
                        </form>
                    <?php endif; ?>
                    <?php if ($eligible && $shown === 'available'): ?>
                        <form method="post" action="claim.php" class="inline-form">
                            <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
                            <input type="hidden" name="post_id" value="<?= (int) $post['id'] ?>">
                            <button class="btn btn-primary btn-lg" type="submit"><?= icon('hand') ?> Claim this food</button>
                        </form>
                    <?php endif; ?>
                    <a class="btn btn-ghost" href="home.php">Back to dashboard</a>
                </div>
            </section>
        </div>

        <aside class="card">
            <h2>History</h2>
            <p class="muted small">Every status change is recorded with who made it.</p>
            <ol class="timeline">
                <?php foreach ($events as $ev): ?>
                <li>
                    <span class="tl-dot tone-<?= $ev['tone'] ?>"><?= icon($ev['icon']) ?></span>
                    <div>
                        <p><?= $ev['label'] /* names escaped above */ ?></p>
                        <time><?= e(fmt_time($ev['at'])) ?></time>
                    </div>
                </li>
                <?php endforeach; ?>
            </ol>
        </aside>
    </div>
</main>
<?php require __DIR__ . '/includes/footer.php'; ?>
