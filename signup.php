<?php
require_once __DIR__ . '/includes/auth.php';

if (is_logged_in()) {
    header('Location: home.php');
    exit;
}

$errors = [];
$old = ['full_name' => '', 'email' => '', 'phone' => '', 'role' => 'donor',
        'organization_name' => '', 'ngo_name' => '', 'registration_no' => ''];

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
                $pdo->prepare('INSERT INTO ngos (owner_user_id, name, registration_no) VALUES (?, ?, ?)')
                    ->execute([$userId, $old['ngo_name'], $old['registration_no']]);
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
<main class="auth-card">
    <h1>Create your account</h1>
    <p class="muted">Donate surplus food or help collect it.</p>

    <?php if ($errors): ?>
        <div class="alert alert-error">
            <ul><?php foreach ($errors as $err): ?><li><?= e($err) ?></li><?php endforeach; ?></ul>
        </div>
    <?php endif; ?>

    <form method="post" id="signupForm" novalidate>
        <label for="role">I am a</label>
        <select id="role" name="role">
            <option value="donor"     <?= $old['role'] === 'donor' ? 'selected' : '' ?>>Donor (restaurant, wedding hall, household)</option>
            <option value="ngo"       <?= $old['role'] === 'ngo' ? 'selected' : '' ?>>NGO</option>
            <option value="volunteer" <?= $old['role'] === 'volunteer' ? 'selected' : '' ?>>Volunteer</option>
        </select>

        <label for="full_name">Full name</label>
        <input type="text" id="full_name" name="full_name" value="<?= e($old['full_name']) ?>" required>

        <label for="email">Email</label>
        <input type="email" id="email" name="email" value="<?= e($old['email']) ?>" required>

        <label for="phone">Phone</label>
        <input type="tel" id="phone" name="phone" value="<?= e($old['phone']) ?>">

        <div class="role-fields" data-role="donor">
            <label for="organization_name">Organization name (optional)</label>
            <input type="text" id="organization_name" name="organization_name" value="<?= e($old['organization_name']) ?>">
        </div>

        <div class="role-fields" data-role="ngo">
            <label for="ngo_name">NGO name</label>
            <input type="text" id="ngo_name" name="ngo_name" value="<?= e($old['ngo_name']) ?>">
            <label for="registration_no">Registration number</label>
            <input type="text" id="registration_no" name="registration_no" value="<?= e($old['registration_no']) ?>">
            <p class="hint">An admin must verify your NGO before you can claim food.</p>
        </div>

        <label for="password">Password (min 8 characters)</label>
        <div class="pw-wrap">
            <input type="password" id="password" name="password" required>
            <button type="button" class="pw-toggle" data-target="password">Show</button>
        </div>

        <label for="confirm_password">Confirm password</label>
        <input type="password" id="confirm_password" name="confirm_password" required>

        <button type="submit" class="btn btn-primary btn-block">Sign up</button>
    </form>
    <p class="switch">Already have an account? <a href="login.php">Log in</a></p>
</main>
<?php require __DIR__ . '/includes/footer.php'; ?>
