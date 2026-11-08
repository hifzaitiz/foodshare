<?php
// Expects $pageTitle (string). Optional: $bodyClass, $activeNav.
require_once __DIR__ . '/ui.php';

$pageTitle = $pageTitle ?? 'Save & Serve';
$bodyClass = $bodyClass ?? '';
$appShell  = is_logged_in() && $bodyClass !== 'auth-page';
$current   = $activeNav ?? basename($_SERVER['SCRIPT_NAME'], '.php');

// Sidebar links per role: [key, label, href, icon]
$navItems = [];
if ($appShell) {
    $navItems[] = ['home', 'Dashboard', 'home.php', 'home'];
    switch ($_SESSION['role']) {
        case 'donor':
            $navItems[] = ['post_food', 'Post food', 'post_food.php', 'plus'];
            break;
        case 'ngo':
        case 'volunteer':
            $navItems[] = ['available', 'Available food', 'home.php#available', 'search'];
            $navItems[] = ['claims', 'My claims', 'home.php#claims', 'box'];
            if ($_SESSION['role'] === 'ngo') {
                $navItems[] = ['team', 'My team', 'team.php', 'users'];
            }
            break;
        case 'admin':
            $navItems[] = ['ngos', 'NGO requests', 'home.php#ngos', 'building'];
            $navItems[] = ['volunteers', 'Volunteer requests', 'home.php#volunteers', 'users'];
            break;
    }
    $navItems[] = ['impact', 'Impact', 'impact.php', 'leaf'];
    $navItems[] = ['profile', 'Profile', 'profile.php', 'user'];
}
// Short labels for the mobile bottom tab bar
$shortLabels = ['home' => 'Home', 'post_food' => 'Post', 'available' => 'Food', 'claims' => 'Pickups', 'team' => 'Team',
                'ngos' => 'NGOs', 'volunteers' => 'Volunteers', 'impact' => 'Impact', 'profile' => 'Me'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($pageTitle) ?> | Save &amp; Serve</title>
    <link rel="icon" type="image/png" href="assets/img/favicon.png">
    <link rel="apple-touch-icon" href="assets/img/logo-192.png">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Archivo:wdth,wght@75..100,500..800&family=DM+Sans:opsz,wght@9..40,400;9..40,500;9..40,600;9..40,700&family=Fraunces:ital,opsz,wght@1,9..144,500&family=JetBrains+Mono:wght@500;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/style.css?v=<?= filemtime(__DIR__ . '/../assets/css/style.css') ?>">
    <script>
        document.documentElement.classList.add('js');
        // Apply the saved theme before the page paints, so there is no flash of the wrong colours.
        try { var t = localStorage.getItem('theme'); if (t === 'dark' || t === 'light') { document.documentElement.setAttribute('data-theme', t); } } catch (e) {}
    </script>
</head>
<body class="<?= e($bodyClass) ?>">
<a class="skip-link" href="#main">Skip to main content</a>
<?php if ($appShell): ?>
<div class="app">
    <aside class="sidebar">
        <a class="brand" href="home.php">
            <img class="brand-logo" src="assets/img/logo-192.png" alt="">
            <span>Save <b>&amp;</b> Serve</span>
        </a>

        <div>
        <p class="nav-label">Menu</p>
        <nav class="side-nav" aria-label="Main">
            <?php foreach ($navItems as [$key, $label, $href, $ico]): ?>
                <a href="<?= e($href) ?>" class="<?= trim(($key === $current ? 'active ' : '') . (in_array($key, ['post_food', 'available'], true) ? 'nav-primary' : '')) ?>">
                    <?= icon($ico) ?><span class="nav-long"><?= e($label) ?></span><span class="nav-short"><?= e($shortLabels[$key] ?? $label) ?></span>
                </a>
            <?php endforeach; ?>
        </nav>
        </div>

        <div class="side-user">
            <span class="avatar avatar-<?= e($_SESSION['role']) ?>"><?= e(initials($_SESSION['name'])) ?></span>
            <div class="side-user-info">
                <strong><?= e($_SESSION['name']) ?></strong>
                <span class="role-label"><?= $_SESSION['role'] === 'ngo' ? 'NGO' : e(ucfirst($_SESSION['role'])) ?></span>
            </div>
            <?= theme_toggle() ?>
            <form method="post" action="logout.php" class="inline-form">
                <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                <button class="icon-btn" type="submit" title="Log out" aria-label="Log out"><?= icon('logout') ?></button>
            </form>
        </div>
    </aside>
    <div class="main" id="main" tabindex="-1">
<?php endif; ?>
