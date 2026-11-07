<?php
require_once __DIR__ . '/includes/auth.php';
require_role('ngo');

$pdo    = db();
$userId = $_SESSION['user_id'];

// The NGO this user manages (the account that registered it is its manager).
$stmt = $pdo->prepare(
    "SELECT n.id, n.name, n.registration_no, n.is_verified, n.rejected_at
     FROM ngos n JOIN ngo_members m ON m.ngo_id = n.id
     WHERE m.user_id = ? AND m.member_role = 'manager' LIMIT 1"
);
$stmt->execute([$userId]);
$ngo = $stmt->fetch();
if (!$ngo) {
    http_response_code(403);
    exit('Only NGO managers can manage a team.');
}
$ngoId = (int) $ngo['id'];
$email = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_post();
    $action = $_POST['action'] ?? '';

    if ($action === 'add') {
        $email = trim($_POST['email'] ?? '');
        $stmt = $pdo->prepare('SELECT id, full_name, role, rejected_at FROM users WHERE email = ?');
        $stmt->execute([$email]);
        $vol = $stmt->fetch();

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            flash('error', 'Enter a valid email address.');
        } elseif (!$vol || $vol['role'] !== 'volunteer') {
            flash('error', 'No volunteer account uses that email. Ask them to sign up as a volunteer first.');
        } elseif ($vol['rejected_at'] !== null) {
            flash('error', 'This volunteer was rejected by the admin and cannot be added.');
        } else {
            // INSERT IGNORE: the (ngo_id, user_id) primary key stops duplicate memberships.
            $add = $pdo->prepare("INSERT IGNORE INTO ngo_members (ngo_id, user_id, member_role) VALUES (?, ?, 'volunteer')");
            $add->execute([$ngoId, (int) $vol['id']]);
            $add->rowCount() > 0
                ? flash('success', $vol['full_name'] . ' joined ' . $ngo['name'] . '.')
                : flash('error', $vol['full_name'] . ' is already a member.');
            $email = '';
        }
    } elseif ($action === 'remove') {
        // Only volunteers can be removed, so the NGO always keeps its manager.
        $del = $pdo->prepare("DELETE FROM ngo_members WHERE ngo_id = ? AND user_id = ? AND member_role = 'volunteer'");
        $del->execute([$ngoId, (int) ($_POST['user_id'] ?? 0)]);
        $del->rowCount() > 0
            ? flash('success', 'Member removed from the team.')
            : flash('error', 'That member could not be removed.');
    }
    header('Location: team.php');
    exit;
}

$stmt = $pdo->prepare(
    "SELECT u.id, u.full_name, u.email, u.phone, u.is_verified, m.member_role,
            (SELECT COUNT(*) FROM claims c WHERE c.claimed_by = u.id AND c.ngo_id = m.ngo_id) AS claims
     FROM ngo_members m JOIN users u ON u.id = m.user_id
     WHERE m.ngo_id = ?
     ORDER BY m.member_role = 'manager' DESC, u.full_name"
);
$stmt->execute([$ngoId]);
$members = $stmt->fetchAll();
$volunteerCount = count(array_filter($members, fn($m) => $m['member_role'] === 'volunteer'));

$flash = $_SESSION['flash'] ?? null;
unset($_SESSION['flash']);
$csrf = csrf_token();
$pageTitle = 'My team';
require __DIR__ . '/includes/header.php';
?>
<main class="content">
    <header class="page-head">
        <div>
            <p class="eyebrow"><a href="home.php">Dashboard</a> / My team</p>
            <h1><?= e($ngo['name']) ?> team</h1>
            <p class="muted">Volunteers you add can claim food on behalf of <?= e($ngo['name']) ?> once the NGO is verified.</p>
        </div>
        <span class="count-pill"><?= $volunteerCount ?> volunteer<?= $volunteerCount === 1 ? '' : 's' ?></span>
    </header>

    <?php if ($flash): ?>
        <?php $ok = $flash['type'] === 'success'; ?>
        <div class="alert alert-<?= $ok ? 'success' : 'error' ?>" <?= $ok ? 'data-autohide' : '' ?>>
            <?= icon($ok ? 'check-circle' : 'alert') ?><span><?= e($flash['message']) ?></span>
        </div>
    <?php endif; ?>

    <?php if ((int) $ngo['is_verified'] !== 1): ?>
        <div class="alert alert-warn"><?= icon('shield') ?>
            <span><strong><?= $ngo['rejected_at'] ? 'Your NGO was rejected by the admin.' : 'Your NGO is waiting for admin verification.' ?></strong>
            You can build your team now, but members can only claim food for the NGO after it is verified.</span>
        </div>
    <?php endif; ?>

    <div class="profile-layout">
        <aside class="card">
            <h2>Add a volunteer</h2>
            <p class="muted small">Enter the email of someone who already has a <strong>volunteer</strong> account on FoodShare.</p>
            <form method="post" id="teamForm">
                <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
                <input type="hidden" name="action" value="add">
                <label for="email">Volunteer email</label>
                <input type="email" id="email" name="email" value="<?= e($email) ?>" placeholder="volunteer@example.com" required>
                <button class="btn btn-primary btn-block team-add-btn" type="submit"><?= icon('plus') ?> Add to team</button>
            </form>
            <ul class="tips-list">
                <li><?= icon('hand') ?><span>Members can claim food for the NGO without being verified individually: your verified NGO vouches for them.</span></li>
                <li><?= icon('box') ?><span>Their claims are recorded under <?= e($ngo['name']) ?> and donors see it.</span></li>
            </ul>
        </aside>

        <section class="card">
            <div class="card-head">
                <div>
                    <h2>Members</h2>
                    <p class="muted small">Stored in the <code>ngo_members</code> table: one NGO, many members.</p>
                </div>
            </div>
            <ul class="request-list">
                <?php foreach ($members as $m): ?>
                <li>
                    <span class="avatar avatar-<?= $m['member_role'] === 'manager' ? 'ngo' : 'volunteer' ?>"><?= e(initials($m['full_name'])) ?></span>
                    <div class="req-info">
                        <strong><?= e($m['full_name']) ?> <span class="tag"><?= e($m['member_role']) ?></span></strong>
                        <small><?= icon('mail') ?><?= e($m['email']) ?>
                            <?php if ($m['phone']): ?> &nbsp; <?= icon('phone') ?><?= e($m['phone']) ?><?php endif; ?>
                            &nbsp; <?= icon('box') ?><?= (int) $m['claims'] ?> claim<?= (int) $m['claims'] === 1 ? '' : 's' ?> for the NGO</small>
                    </div>
                    <?php if ($m['member_role'] === 'volunteer'): ?>
                    <form method="post" class="inline-form" onsubmit="return confirm('Remove this member from the team?');">
                        <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
                        <input type="hidden" name="action" value="remove">
                        <input type="hidden" name="user_id" value="<?= (int) $m['id'] ?>">
                        <button class="btn btn-danger-ghost btn-sm" type="submit"><?= icon('x') ?> Remove</button>
                    </form>
                    <?php else: ?>
                        <span class="muted small">You</span>
                    <?php endif; ?>
                </li>
                <?php endforeach; ?>
            </ul>
            <?php if ($volunteerCount === 0): ?>
                <div class="empty empty-sm">
                    <span class="empty-icon"><?= icon('users') ?></span>
                    <h3>No volunteers yet</h3>
                    <p>Add volunteers by email to let them collect food for your NGO.</p>
                </div>
            <?php endif; ?>
        </section>
    </div>
</main>
<?php require __DIR__ . '/includes/footer.php'; ?>
