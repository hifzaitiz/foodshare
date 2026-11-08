<?php
require_once __DIR__ . '/includes/auth.php';

if (is_logged_in()) {
    header('Location: home.php');
    exit;
}

$errors = [];
$old = ['full_name' => '', 'email' => '', 'phone' => '', 'role' => 'donor',
        'organization_name' => '', 'ngo_name' => '', 'registration_no' => '',
        'ngo_address' => '', 'ngo_area' => '', 'ngo_city' => ''];

// Links like signup.php?role=ngo (from the homepage) pre-select a role.
if (in_array($_GET['role'] ?? '', ['donor', 'ngo', 'volunteer'], true)) {
    $old['role'] = $_GET['role'];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    foreach ($old as $k => $_) {
        $old[$k] = trim($_POST[$k] ?? $old[$k]);
    }
    $password = $_POST['password'] ?? '';
    $confirm  = $_POST['confirm_password'] ?? '';

    $roles = ['donor', 'ngo', 'volunteer'];

    if ($old['full_name'] === '' || mb_strlen($old['full_name']) > 100) {
        $errors[] = 'Full name is required (max 100 characters).';
    }
    if (!filter_var($old['email'], FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Enter a valid email address.';
    }
    if ($old['phone'] !== '' && !preg_match('/^[0-9+\- ]{7,20}$/', $old['phone'])) {
        $errors[] = 'Enter a valid phone number.';
    }
    if (!in_array($old['role'], $roles, true)) {
        $errors[] = 'Choose a valid role.';
    }
    if (strlen($password) < 8) {
        $errors[] = 'Password must be at least 8 characters.';
    }
    if ($password !== $confirm) {
        $errors[] = 'Passwords do not match.';
    }
    if ($old['role'] === 'ngo' && ($old['ngo_name'] === '' || $old['registration_no'] === '')) {
        $errors[] = 'NGO name and registration number are required.';
    }
    if ($old['role'] === 'ngo' && ($old['ngo_address'] === '' || $old['ngo_city'] === '')) {
        $errors[] = 'NGO address and city are required.';
    }

    if (!$errors) {
        $pdo = db();
        $dup = $pdo->prepare('SELECT 1 FROM users WHERE email = ?');
        $dup->execute([$old['email']]);
        if ($dup->fetch()) {
            $errors[] = 'An account with this email already exists.';
        }
        if (!$errors && $old['role'] === 'ngo') {
            $dupNgo = $pdo->prepare('SELECT 1 FROM ngos WHERE registration_no = ?');
            $dupNgo->execute([$old['registration_no']]);
            if ($dupNgo->fetch()) {
                $errors[] = 'This NGO registration number is already registered.';
            }
        }
    }

    if (!$errors) {
        $pdo->beginTransaction();
        try {
            $org = $old['role'] === 'donor' && $old['organization_name'] !== '' ? $old['organization_name'] : null;
            $pdo->prepare('INSERT INTO users (full_name, email, phone, password_hash, role, organization_name) VALUES (?, ?, ?, ?, ?, ?)')
                ->execute([$old['full_name'], $old['email'], $old['phone'] ?: null,
                           password_hash($password, PASSWORD_DEFAULT), $old['role'], $org]);
            $userId = (int) $pdo->lastInsertId();

            if ($old['role'] === 'ngo') {
                // The NGO's office address goes into locations and is linked by ngos.location_id.
                $pdo->prepare('INSERT INTO locations (address, area, city) VALUES (?, ?, ?)')
                    ->execute([$old['ngo_address'], $old['ngo_area'] ?: null, $old['ngo_city']]);
                $ngoLocationId = (int) $pdo->lastInsertId();

                $pdo->prepare('INSERT INTO ngos (owner_user_id, name, registration_no, location_id) VALUES (?, ?, ?, ?)')
                    ->execute([$userId, $old['ngo_name'], $old['registration_no'], $ngoLocationId]);
                $pdo->prepare('INSERT INTO ngo_members (ngo_id, user_id, member_role) VALUES (?, ?, ?)')
                    ->execute([(int) $pdo->lastInsertId(), $userId, 'manager']);
            }
            $pdo->commit();
            header('Location: login.php?registered=1');
            exit;
        } catch (Throwable $ex) {
            $pdo->rollBack();
            $errors[] = 'Could not create your account. Please try again.';
        }
    }
}

$pageTitle = 'Sign up';
$bodyClass = 'auth-page';
require __DIR__ . '/includes/header.php';
?>
<div class="auth-split">
    <?php require __DIR__ . '/includes/auth_hero.php'; ?>

    <main class="auth-panel" id="main" tabindex="-1">
        <div class="auth-card auth-card-wide">
            <h1>Create your account</h1>
            <p class="muted">Donate surplus food or help collect it.</p>

            <?php if ($errors): ?>
                <div class="alert alert-error"><?= icon('alert') ?>
                    <ul><?php foreach ($errors as $err): ?><li><?= e($err) ?></li><?php endforeach; ?></ul>
                </div>
            <?php endif; ?>

            <form method="post" id="signupForm" novalidate>
                <fieldset class="role-picker">
                    <legend>I am a</legend>
                    <?php foreach ([
                        'donor'     => ['utensils', 'Donor', 'Restaurant, hall or household'],
                        'ngo'       => ['building', 'NGO', 'Registered organisation'],
                        'volunteer' => ['hand', 'Volunteer', 'Individual helper'],
                    ] as $value => [$ico, $title, $sub]): ?>
                    <label class="role-card">
                        <input type="radio" name="role" value="<?= $value ?>" <?= $old['role'] === $value ? 'checked' : '' ?>>
                        <span class="role-card-body">
                            <span class="role-icon"><?= icon($ico) ?></span>
                            <strong><?= $title ?></strong>
                            <small><?= $sub ?></small>
                        </span>
                    </label>
                    <?php endforeach; ?>
                </fieldset>

                <div class="row-2">
                    <div>
                        <label for="full_name">Full name</label>
                        <input type="text" id="full_name" name="full_name" value="<?= e($old['full_name']) ?>" placeholder="Ayesha Khan" autocomplete="name" required>
                    </div>
                    <div>
                        <label for="phone">Phone <span class="opt">optional</span></label>
                        <input type="tel" id="phone" name="phone" value="<?= e($old['phone']) ?>" placeholder="0300 1234567" autocomplete="tel">
                    </div>
                </div>

                <label for="email">Email</label>
                <input type="email" id="email" name="email" value="<?= e($old['email']) ?>" placeholder="you@example.com" autocomplete="email" required>

                <div class="role-fields" data-role="donor">
                    <label for="organization_name">Organization name <span class="opt">optional</span></label>
                    <input type="text" id="organization_name" name="organization_name" value="<?= e($old['organization_name']) ?>" placeholder="e.g. Spice Garden Restaurant">
                </div>

                <div class="role-fields" data-role="ngo">
                    <div class="row-2">
                        <div>
                            <label for="ngo_name">NGO name</label>
                            <input type="text" id="ngo_name" name="ngo_name" value="<?= e($old['ngo_name']) ?>" placeholder="e.g. Hope Foundation">
                        </div>
                        <div>
                            <label for="registration_no">Registration number</label>
                            <input type="text" id="registration_no" name="registration_no" value="<?= e($old['registration_no']) ?>" placeholder="e.g. NGO-2024-77">
                        </div>
                    </div>
                    <label for="ngo_address">NGO office address</label>
                    <input type="text" id="ngo_address" name="ngo_address" value="<?= e($old['ngo_address']) ?>" placeholder="Street, building, landmark">
                    <div class="row-2">
                        <div>
                            <label for="ngo_area">Area <span class="opt">optional</span></label>
                            <input type="text" id="ngo_area" name="ngo_area" value="<?= e($old['ngo_area']) ?>" placeholder="e.g. Model Town">
                        </div>
                        <div>
                            <label for="ngo_city">City</label>
                            <input type="text" id="ngo_city" name="ngo_city" value="<?= e($old['ngo_city']) ?>" placeholder="e.g. Lahore">
                        </div>
                    </div>
                    <p class="hint"><?= icon('shield') ?> An admin must verify your NGO before you can claim food.</p>
                </div>

                <div class="role-fields" data-role="volunteer">
                    <p class="hint"><?= icon('shield') ?> An admin verifies volunteers before they can claim food.</p>
                </div>

                <div class="row-2">
                    <div>
                        <label for="password">Password</label>
                        <div class="pw-wrap">
                            <input type="password" id="password" name="password" placeholder="Min. 8 characters" autocomplete="new-password" required>
                            <button type="button" class="pw-toggle" data-target="password">Show</button>
                        </div>
                    </div>
                    <div>
                        <label for="confirm_password">Confirm password</label>
                        <input type="password" id="confirm_password" name="confirm_password" placeholder="Repeat password" autocomplete="new-password" required>
                    </div>
                </div>

                <button type="submit" class="btn btn-primary btn-block btn-lg">Create account <?= icon('arrow-right') ?></button>
            </form>
            <p class="switch">Already have an account? <a href="login.php">Log in</a></p>
        </div>
    </main>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
