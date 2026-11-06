<?php
require_once __DIR__ . '/includes/auth.php';

if (is_logged_in()) {
    header('Location: home.php');
    exit;
}

$error = '';
$email = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email    = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($email === '' || $password === '') {
        $error = 'Please enter your email and password.';
    } else {
        $stmt = db()->prepare('SELECT id, full_name, password_hash, role FROM users WHERE email = ?');
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if (!$user || !password_verify($password, $user['password_hash'])) {
            $error = 'Invalid email or password.';
        } else {
            login_user($user);
            header('Location: home.php');
            exit;
        }
    }
}

$pageTitle = 'Log in';
$bodyClass = 'auth-page';
require __DIR__ . '/includes/header.php';
?>
<div class="auth-split">
    <?php require __DIR__ . '/includes/auth_hero.php'; ?>

    <main class="auth-panel">
        <div class="auth-card">
            <h1>Welcome back</h1>
            <p class="muted">Log in to share or claim surplus food.</p>

            <?php if (isset($_GET['registered'])): ?>
                <div class="alert alert-success"><?= icon('check-circle') ?><span>Account created. Please log in.</span></div>
            <?php endif; ?>
            <?php if ($error): ?>
                <div class="alert alert-error"><?= icon('alert') ?><span><?= e($error) ?></span></div>
            <?php endif; ?>

            <form method="post" id="loginForm" novalidate>
                <label for="email">Email</label>
                <input type="email" id="email" name="email" value="<?= e($email) ?>" placeholder="you@example.com" autocomplete="email" required>

                <label for="password">Password</label>
                <div class="pw-wrap">
                    <input type="password" id="password" name="password" placeholder="Your password" autocomplete="current-password" required>
                    <button type="button" class="pw-toggle" data-target="password">Show</button>
                </div>

                <button type="submit" class="btn btn-primary btn-block btn-lg">Log in <?= icon('arrow-right') ?></button>
            </form>
            <p class="switch">New here? <a href="signup.php">Create an account</a></p>

            <details class="demo-box">
                <summary>Demo accounts</summary>
                <p>Password for all: <code>password123</code></p>
                <ul>
                    <li><code>donor@foodshare.test</code></li>
                    <li><code>ngo@foodshare.test</code></li>
                    <li><code>volunteer@foodshare.test</code></li>
                    <li><code>admin@foodshare.test</code></li>
                </ul>
            </details>
        </div>
    </main>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
