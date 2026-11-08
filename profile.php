<?php
require_once __DIR__ . '/includes/auth.php';
require_login();

$pdo    = db();
$userId = $_SESSION['user_id'];
$role   = $_SESSION['role'];

function load_user(PDO $pdo, int $id): array
{
    $stmt = $pdo->prepare(
        'SELECT u.full_name, u.email, u.phone, u.role, u.organization_name, u.is_verified, u.rejected_at, u.created_at,
                u.location_id, l.address, l.area, l.city
         FROM users u LEFT JOIN locations l ON l.id = u.location_id WHERE u.id = ?'
    );
    $stmt->execute([$id]);
    return $stmt->fetch();
}

$user      = load_user($pdo, $userId);
$errors    = [];      // details form
$pwErrors  = [];      // password form
$ngoErrors = [];      // NGO address form (NGO managers only)

// NGO accounts: the organisation they registered, its address and verification state.
$ngo = null;
if ($role === 'ngo') {
    $stmt = $pdo->prepare(
        'SELECT n.id, n.name, n.registration_no, n.is_verified, n.rejected_at, n.location_id, l.address, l.area, l.city
         FROM ngos n LEFT JOIN locations l ON l.id = n.location_id WHERE n.owner_user_id = ? LIMIT 1'
    );
    $stmt->execute([$userId]);
    $ngo = $stmt->fetch() ?: null;
}
$ngoOld = ['ngo_address' => (string) ($ngo['address'] ?? ''), 'ngo_area' => (string) ($ngo['area'] ?? ''), 'ngo_city' => (string) ($ngo['city'] ?? '')];
$old = [
    'full_name'         => $user['full_name'],
    'phone'             => (string) $user['phone'],
    'organization_name' => (string) $user['organization_name'],
    'address'           => (string) $user['address'],
    'area'              => (string) $user['area'],
    'city'              => (string) $user['city'],
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_post();
    $form = $_POST['form'] ?? '';

    if ($form === 'details') {
        foreach ($old as $k => $_) {
            $old[$k] = trim($_POST[$k] ?? '');
        }
        if ($old['full_name'] === '' || mb_strlen($old['full_name']) > 100) {
            $errors[] = 'Full name is required (max 100 characters).';
        }
        if ($old['phone'] !== '' && !preg_match('/^[0-9+\- ]{7,20}$/', $old['phone'])) {
            $errors[] = 'Enter a valid phone number.';
        }
        if (mb_strlen($old['organization_name']) > 150) {
            $errors[] = 'Organization name is too long (max 150 characters).';
        }
        $anyAddress = $old['address'] !== '' || $old['area'] !== '' || $old['city'] !== '';
        if ($anyAddress && ($old['address'] === '' || $old['city'] === '')) {
            $errors[] = 'To save an address, fill in at least the street address and city (or leave all three empty).';
        }

        if (!$errors) {
            $pdo->beginTransaction();
            try {
                $locationId = $user['location_id'] !== null ? (int) $user['location_id'] : null;
                $changed = $old['address'] !== (string) $user['address'] || $old['area'] !== (string) $user['area']
                        || $old['city'] !== (string) $user['city'];
                if ($changed) {
                    $locationId = null;
                    if ($anyAddress) {
                        // A new row, because location rows can be shared with food posts.
                        $pdo->prepare('INSERT INTO locations (address, area, city) VALUES (?, ?, ?)')
                            ->execute([$old['address'], $old['area'] ?: null, $old['city']]);
                        $locationId = (int) $pdo->lastInsertId();
                    }
                }
                $org = $role === 'donor' && $old['organization_name'] !== '' ? $old['organization_name'] : null;
                $pdo->prepare('UPDATE users SET full_name = ?, phone = ?, organization_name = ?, location_id = ? WHERE id = ?')
                    ->execute([$old['full_name'], $old['phone'] ?: null, $org, $locationId, $userId]);
                $pdo->commit();

                $_SESSION['name'] = $old['full_name'];   // sidebar shows the new name right away
                flash('success', 'Your details have been saved.');
                header('Location: profile.php');
                exit;
            } catch (Throwable $ex) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                $errors[] = 'Could not save your details. Please try again.';
            }
        }
    } elseif ($form === 'ngo' && $ngo) {
        foreach ($ngoOld as $k => $_) {
            $ngoOld[$k] = trim($_POST[$k] ?? '');
        }
        if ($ngoOld['ngo_address'] === '' || $ngoOld['ngo_city'] === '') {
            $ngoErrors[] = 'The NGO office address and city are required.';
        }
        if (!$ngoErrors) {
            $pdo->beginTransaction();
            try {
                // A new row, because location rows can be shared (e.g. with the manager's own address).
                $pdo->prepare('INSERT INTO locations (address, area, city) VALUES (?, ?, ?)')
                    ->execute([$ngoOld['ngo_address'], $ngoOld['ngo_area'] ?: null, $ngoOld['ngo_city']]);
                $pdo->prepare('UPDATE ngos SET location_id = ? WHERE id = ? AND owner_user_id = ?')
                    ->execute([(int) $pdo->lastInsertId(), (int) $ngo['id'], $userId]);
                $pdo->commit();
                flash('success', 'NGO address saved.');
                header('Location: profile.php');
                exit;
            } catch (Throwable $ex) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                $ngoErrors[] = 'Could not save the NGO address. Please try again.';
            }
        }
    } elseif ($form === 'password') {
        $current = $_POST['current_password'] ?? '';
        $new     = $_POST['new_password'] ?? '';
        $confirm = $_POST['confirm_password'] ?? '';

        $stmt = $pdo->prepare('SELECT password_hash FROM users WHERE id = ?');
        $stmt->execute([$userId]);
        $hash = (string) $stmt->fetchColumn();

        if (!password_verify($current, $hash)) {
            $pwErrors[] = 'Your current password is not correct.';
        }
        if (strlen($new) < 8) {
            $pwErrors[] = 'New password must be at least 8 characters.';
        }
        if ($new !== $confirm) {
            $pwErrors[] = 'New passwords do not match.';
        }
        if (!$pwErrors && password_verify($new, $hash)) {
            $pwErrors[] = 'New password must be different from the current one.';
        }

        if (!$pwErrors) {
            $pdo->prepare('UPDATE users SET password_hash = ? WHERE id = ?')
                ->execute([password_hash($new, PASSWORD_DEFAULT), $userId]);
            session_regenerate_id(true);
            flash('success', 'Your password has been changed.');
            header('Location: profile.php');
            exit;
        }
    }
}

$verifiedFlag = $role === 'ngo' ? $ngo : ($role === 'volunteer' ? $user : null);

$flash = $_SESSION['flash'] ?? null;
unset($_SESSION['flash']);

$csrf = csrf_token();
$pageTitle = 'Profile';
require __DIR__ . '/includes/header.php';
?>
<main class="content">
    <header class="page-head">
        <div>
            <p class="eyebrow"><a href="home.php">Dashboard</a> / Profile</p>
            <h1>Your profile</h1>
            <p class="muted">Keep your contact details up to date so donors and collectors can reach you.</p>
        </div>
    </header>

    <?php if ($flash): ?>
        <?php $ok = $flash['type'] === 'success'; ?>
        <div class="alert alert-<?= $ok ? 'success' : 'error' ?>" <?= $ok ? 'data-autohide' : '' ?>>
            <?= icon($ok ? 'check-circle' : 'alert') ?><span><?= e($flash['message']) ?></span>
        </div>
    <?php endif; ?>

    <div class="profile-layout">
        <aside class="card profile-card">
            <span class="avatar avatar-xl avatar-<?= e($role) ?>"><?= e(initials($user['full_name'])) ?></span>
            <h2><?= e($user['full_name']) ?></h2>
            <p class="muted small"><?= e($user['email']) ?></p>
            <span class="tag"><?= $role === 'ngo' ? 'NGO' : e(ucfirst($role)) ?></span>
            <dl class="facts facts-1">
                <div><dt><?= icon('calendar') ?> Member since</dt><dd><?= e(date('d M Y', strtotime($user['created_at']))) ?></dd></div>
                <?php if ($verifiedFlag): ?>
                <div><dt><?= icon('shield') ?> Verification</dt><dd>
                    <?php if ((int) $verifiedFlag['is_verified'] === 1): ?><span class="status status-available">Verified</span>
                    <?php elseif ($verifiedFlag['rejected_at']): ?><span class="status status-expired">Rejected</span>
                    <?php else: ?><span class="status status-claimed">Pending</span><?php endif; ?>
                </dd></div>
                <?php endif; ?>
                <?php if ($ngo): ?>
                <div><dt><?= icon('building') ?> Organisation</dt><dd><?= e($ngo['name']) ?><small>Reg. no. <?= e($ngo['registration_no']) ?></small>
                    <?php if ($ngo['city']): ?><small><?= e($ngo['address']) ?>, <?= e(trim(($ngo['area'] ? $ngo['area'] . ', ' : '') . $ngo['city'])) ?></small><?php endif; ?></dd></div>
                <?php endif; ?>
            </dl>
        </aside>

        <div>
            <form method="post" class="card form-card" id="profileForm" novalidate>
                <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
                <input type="hidden" name="form" value="details">

                <fieldset class="form-section">
                    <legend><span class="step"><?= icon('user') ?></span> Personal details</legend>
                    <?php if ($errors): ?>
                        <div class="alert alert-error"><?= icon('alert') ?><ul><?php foreach ($errors as $err): ?><li><?= e($err) ?></li><?php endforeach; ?></ul></div>
                    <?php endif; ?>

                    <div class="row-2">
                        <div>
                            <label for="full_name">Full name</label>
                            <input type="text" id="full_name" name="full_name" value="<?= e($old['full_name']) ?>" required>
                        </div>
                        <div>
                            <label for="phone">Phone <span class="opt">shown to people you share food with</span></label>
                            <input type="tel" id="phone" name="phone" value="<?= e($old['phone']) ?>" placeholder="0300 1234567">
                        </div>
                    </div>

                    <label for="email">Email <span class="opt">used to log in, cannot be changed</span></label>
                    <input type="email" id="email" value="<?= e($user['email']) ?>" disabled>

                    <?php if ($role === 'donor'): ?>
                        <label for="organization_name">Organization name <span class="opt">optional</span></label>
                        <input type="text" id="organization_name" name="organization_name" value="<?= e($old['organization_name']) ?>" placeholder="e.g. Spice Garden Restaurant">
                    <?php endif; ?>
                </fieldset>

                <fieldset class="form-section">
                    <legend><span class="step"><?= icon('pin') ?></span> Address <span class="opt">optional</span></legend>
                    <label for="address">Street address</label>
                    <input type="text" id="address" name="address" value="<?= e($old['address']) ?>" placeholder="Street, building, landmark">
                    <div class="row-2">
                        <div>
                            <label for="area">Area</label>
                            <input type="text" id="area" name="area" value="<?= e($old['area']) ?>" placeholder="e.g. Gulberg">
                        </div>
                        <div>
                            <label for="city">City</label>
                            <input type="text" id="city" name="city" value="<?= e($old['city']) ?>" placeholder="e.g. Lahore">
                        </div>
                    </div>
                </fieldset>

                <div class="form-actions">
                    <button type="submit" class="btn btn-primary"><?= icon('check') ?> Save details</button>
                </div>
            </form>

            <?php if ($ngo): ?>
            <form method="post" class="card form-card" id="ngoForm" novalidate>
                <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
                <input type="hidden" name="form" value="ngo">

                <fieldset class="form-section">
                    <legend><span class="step"><?= icon('building') ?></span> <?= e($ngo['name']) ?> office address</legend>
                    <?php if ($ngoErrors): ?>
                        <div class="alert alert-error"><?= icon('alert') ?><ul><?php foreach ($ngoErrors as $err): ?><li><?= e($err) ?></li><?php endforeach; ?></ul></div>
                    <?php endif; ?>
                    <p class="muted small">Shown to the admin when your NGO is verified.</p>
                    <label for="ngo_address">Street address</label>
                    <input type="text" id="ngo_address" name="ngo_address" value="<?= e($ngoOld['ngo_address']) ?>" placeholder="Street, building, landmark" required>
                    <div class="row-2">
                        <div>
                            <label for="ngo_area">Area <span class="opt">optional</span></label>
                            <input type="text" id="ngo_area" name="ngo_area" value="<?= e($ngoOld['ngo_area']) ?>">
                        </div>
                        <div>
                            <label for="ngo_city">City</label>
                            <input type="text" id="ngo_city" name="ngo_city" value="<?= e($ngoOld['ngo_city']) ?>" required>
                        </div>
                    </div>
                </fieldset>

                <div class="form-actions">
                    <button type="submit" class="btn btn-primary"><?= icon('check') ?> Save NGO address</button>
                </div>
            </form>
            <?php endif; ?>

            <form method="post" class="card form-card" id="passwordForm" novalidate>
                <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
                <input type="hidden" name="form" value="password">

                <fieldset class="form-section">
                    <legend><span class="step"><?= icon('lock') ?></span> Change password</legend>
                    <?php if ($pwErrors): ?>
                        <div class="alert alert-error"><?= icon('alert') ?><ul><?php foreach ($pwErrors as $err): ?><li><?= e($err) ?></li><?php endforeach; ?></ul></div>
                    <?php endif; ?>

                    <label for="current_password">Current password</label>
                    <div class="pw-wrap">
                        <input type="password" id="current_password" name="current_password" autocomplete="current-password" required>
                        <button type="button" class="pw-toggle" data-target="current_password">Show</button>
                    </div>
                    <div class="row-2">
                        <div>
                            <label for="new_password">New password</label>
                            <div class="pw-wrap">
                                <input type="password" id="new_password" name="new_password" placeholder="Min. 8 characters" autocomplete="new-password" required>
                                <button type="button" class="pw-toggle" data-target="new_password">Show</button>
                            </div>
                        </div>
                        <div>
                            <label for="confirm_password">Confirm new password</label>
                            <input type="password" id="confirm_password" name="confirm_password" autocomplete="new-password" required>
                        </div>
                    </div>
                </fieldset>

                <div class="form-actions">
                    <button type="submit" class="btn btn-primary"><?= icon('lock') ?> Update password</button>
                </div>
            </form>
        </div>
    </div>
</main>
<?php require __DIR__ . '/includes/footer.php'; ?>
