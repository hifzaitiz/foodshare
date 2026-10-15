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
<main class="auth-card">
    <h1>Welcome back</h1>
    <p class="muted">Log in to share or claim surplus food.</p>

    <?php if (isset($_GET['registered'])): ?>
        <div class="alert alert-success">Account created. Please log in.</div>
    <?php endif; ?>
    <?php if ($error): ?>
        <div class="alert alert-error"><?= e($error) ?></div>
    <?php endif; ?>

    <form method="post" id="loginForm" novalidate>
        <label for="email">Email</label>
        <input type="email" id="email" name="email" value="<?= e($email) ?>" required>

        <label for="password">Password</label>
        <div class="pw-wrap">
            <input type="password" id="password" name="password" required>
            <button type="button" class="pw-toggle" data-target="password">Show</button>
        </div>

        <button type="submit" class="btn btn-primary btn-block">Log in</button>
    </form>
    <p class="switch">New here? <a href="signup.php">Create an account</a></p>
</main>
<?php require __DIR__ . '/includes/footer.php'; ?>
