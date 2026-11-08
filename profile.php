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
$verifyState  = null;
if ($verifiedFlag) {
    $verifyState = (int) $verifiedFlag['is_verified'] === 1 ? 'verified' : ($verifiedFlag['rejected_at'] ? 'rejected' : 'pending');
}

// ---- Profile completeness: what helps people reach you ----
$checks = [
    ['Name', trim((string) $user['full_name']) !== '', '#details'],
    ['Phone number', trim((string) $user['phone']) !== '', '#details'],
    ['Your address', (string) $user['city'] !== '', '#address'],
];
if ($role === 'donor') {
    $checks[] = ['Organization name', trim((string) $user['organization_name']) !== '', '#details'];
}
if ($ngo) {
    $checks[] = ['NGO office address', (string) $ngo['city'] !== '', '#ngo'];
}
if ($verifyState) {
    $checks[] = ['Verified by an admin', $verifyState === 'verified', null];
}
$done       = count(array_filter($checks, fn ($c) => $c[1]));
$completion = (int) round($done / count($checks) * 100);

$roleLabel = $role === 'ngo' ? 'NGO' : ucfirst($role);
$cityLine  = trim(($user['area'] ? $user['area'] . ', ' : '') . (string) $user['city'], ', ');

$flash = $_SESSION['flash'] ?? null;
unset($_SESSION['flash']);

$csrf = csrf_token();
$pageTitle = 'Profile';
require __DIR__ . '/includes/header.php';
?>
<main class="content profile-page">
    <p class="eyebrow"><a href="home.php">Dashboard</a> / Profile</p>

    <?php if ($flash): ?>
        <?php $ok = $flash['type'] === 'success'; ?>
        <div class="alert alert-<?= $ok ? 'success' : 'error' ?>" <?= $ok ? 'data-autohide' : '' ?>>
            <?= icon($ok ? 'check-circle' : 'alert') ?><span><?= e($flash['message']) ?></span>
        </div>
    <?php endif; ?>

    <!-- Identity header: who you are, how complete your profile is, what you have done -->
    <section class="pf-hero pf-<?= e($role) ?>" aria-labelledby="pfName">
        <div class="pf-id">
            <span class="pf-avatar"><?= e(initials($user['full_name'])) ?></span>
            <div class="pf-who">
                <div class="pf-badges">
                    <span class="pf-role"><?= e($roleLabel) ?></span>
                    <?php if ($verifyState === 'verified'): ?><span class="pf-verify is-ok"><?= icon('shield') ?> Verified</span>
                    <?php elseif ($verifyState === 'rejected'): ?><span class="pf-verify is-bad"><?= icon('x') ?> Not approved</span>
                    <?php elseif ($verifyState === 'pending'): ?><span class="pf-verify is-wait"><?= icon('clock') ?> Waiting for admin</span><?php endif; ?>
                </div>
                <h1 id="pfName"><?= e($user['full_name']) ?></h1>
                <ul class="pf-meta">
                    <li><?= icon('mail') ?><?= e($user['email']) ?></li>
                    <?php if ($user['phone']): ?><li><?= icon('phone') ?><?= e($user['phone']) ?></li><?php endif; ?>
                    <?php if ($cityLine !== ''): ?><li><?= icon('pin') ?><?= e($cityLine) ?></li><?php endif; ?>
                    <li><?= icon('calendar') ?>Member since <?= e(date('M Y', strtotime($user['created_at']))) ?></li>
                </ul>
            </div>
        </div>

        <div class="pf-meter" style="--p: <?= $completion ?>">
            <div class="pf-ring" role="img" aria-label="Profile <?= $completion ?>% complete"><strong><?= $completion ?><small>%</small></strong></div>
            <div>
                <p class="pf-meter-title"><?= $completion === 100 ? 'Profile complete' : 'Profile ' . $completion . '% complete' ?></p>
                <p class="pf-meter-sub"><?= $done ?> of <?= count($checks) ?> done<?= $completion < 100 ? '. Finish it so people can reach you.' : '.' ?></p>
            </div>
        </div>

    </section>

    <div class="pf-layout">
        <aside class="pf-side">
            <nav class="pf-nav card" aria-label="Profile sections">
                <p class="pf-side-title">Settings</p>
                <a href="#details"><?= icon('user') ?> Personal details</a>
                <a href="#address"><?= icon('pin') ?> Address</a>
                <?php if ($ngo): ?><a href="#ngo"><?= icon('building') ?> NGO office</a><?php endif; ?>
                <a href="#security"><?= icon('lock') ?> Password</a>
            </nav>

        </aside>

        <div class="pf-main">
            <form method="post" class="card form-card pf-card" id="profileForm" novalidate data-dirty-watch>
                <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
                <input type="hidden" name="form" value="details">

                <fieldset class="form-section" id="details">
                    <legend><span class="step"><?= icon('user') ?></span> Personal details</legend>
                    <p class="pf-help">Your name and phone are shown to the people you share food with.</p>
                    <?php if ($errors): ?>
                        <div class="alert alert-error"><?= icon('alert') ?><ul><?php foreach ($errors as $err): ?><li><?= e($err) ?></li><?php endforeach; ?></ul></div>
                    <?php endif; ?>

                    <div class="row-2">
                        <div>
                            <label for="full_name">Full name</label>
                            <input type="text" id="full_name" name="full_name" value="<?= e($old['full_name']) ?>" maxlength="100" required>
                        </div>
                        <div>
                            <label for="phone">Phone</label>
                            <input type="tel" id="phone" name="phone" value="<?= e($old['phone']) ?>" placeholder="0300 1234567">
                        </div>
                    </div>

                    <label for="email">Email <span class="opt">used to log in, cannot be changed</span></label>
                    <div class="pf-locked">
                        <input type="email" id="email" value="<?= e($user['email']) ?>" disabled>
                        <?= icon('lock') ?>
                    </div>

                    <?php if ($role === 'donor'): ?>
                        <label for="organization_name">Organization name <span class="opt">optional</span></label>
                        <input type="text" id="organization_name" name="organization_name" value="<?= e($old['organization_name']) ?>" maxlength="150" placeholder="e.g. Spice Garden Restaurant">
                    <?php endif; ?>
                </fieldset>

                <fieldset class="form-section" id="address">
                    <legend><span class="step"><?= icon('pin') ?></span> Address <span class="opt">optional</span></legend>
                    <p class="pf-help">Leave all three empty if you don't want to save an address.</p>
                    <label for="address_line">Street address</label>
                    <input type="text" id="address_line" name="address" value="<?= e($old['address']) ?>" placeholder="Street, building, landmark">
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

                <div class="form-actions pf-actions">
                    <span class="pf-dirty" data-dirty-note hidden><?= icon('edit') ?> Unsaved changes</span>
                    <button type="submit" class="btn btn-primary"><?= icon('check') ?> Save details</button>
                </div>
            </form>

            <?php if ($ngo): ?>
            <form method="post" class="card form-card pf-card" id="ngoForm" novalidate data-dirty-watch>
                <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
                <input type="hidden" name="form" value="ngo">

                <fieldset class="form-section" id="ngo">
                    <legend><span class="step"><?= icon('building') ?></span> NGO office</legend>
                    <div class="pf-org">
                        <div>
                            <strong><?= e($ngo['name']) ?></strong>
                            <span>Registration no. <code><?= e($ngo['registration_no']) ?></code></span>
                        </div>
                        <?php if ($verifyState === 'verified'): ?><span class="status status-available">Verified</span>
                        <?php elseif ($verifyState === 'rejected'): ?><span class="status status-expired">Rejected</span>
                        <?php else: ?><span class="status status-claimed">Pending</span><?php endif; ?>
                    </div>
                    <?php if ($ngoErrors): ?>
                        <div class="alert alert-error"><?= icon('alert') ?><ul><?php foreach ($ngoErrors as $err): ?><li><?= e($err) ?></li><?php endforeach; ?></ul></div>
                    <?php endif; ?>
                    <p class="pf-help">The admin sees this address when verifying your NGO.</p>
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

                <div class="form-actions pf-actions">
                    <span class="pf-dirty" data-dirty-note hidden><?= icon('edit') ?> Unsaved changes</span>
                    <button type="submit" class="btn btn-primary"><?= icon('check') ?> Save NGO address</button>
                </div>
            </form>
            <?php endif; ?>

            <form method="post" class="card form-card pf-card" id="passwordForm" novalidate>
                <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
                <input type="hidden" name="form" value="password">

                <fieldset class="form-section" id="security">
                    <legend><span class="step"><?= icon('lock') ?></span> Password</legend>
                    <p class="pf-help">Use at least 8 characters. Changing it keeps you logged in here.</p>
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
                                <input type="password" id="new_password" name="new_password" placeholder="Min. 8 characters" autocomplete="new-password" required aria-describedby="pwStrength">
                                <button type="button" class="pw-toggle" data-target="new_password">Show</button>
                            </div>
                            <div class="pw-meter" id="pwStrength" data-pw-meter="new_password" aria-live="polite">
                                <span class="pw-bars"><i></i><i></i><i></i><i></i></span>
                                <span class="pw-label">Enter a new password</span>
                            </div>
                        </div>
                        <div>
                            <label for="confirm_password">Confirm new password</label>
                            <input type="password" id="confirm_password" name="confirm_password" autocomplete="new-password" required>
                            <p class="pw-match" data-pw-match="new_password:confirm_password" aria-live="polite"></p>
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
