<?php
// Expects $pageTitle (string). Optional: $bodyClass, $activeNav.
require_once __DIR__ . '/ui.php';

$pageTitle = $pageTitle ?? 'FoodShare';
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
            break;
        case 'admin':
            $navItems[] = ['ngos', 'NGO requests', 'home.php#ngos', 'building'];
            $navItems[] = ['volunteers', 'Volunteer requests', 'home.php#volunteers', 'users'];
            break;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($pageTitle) ?> | FoodShare</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Plus+Jakarta+Sans:wght@600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body class="<?= e($bodyClass) ?>">
<?php if ($appShell): ?>
<div class="app">
    <aside class="sidebar">
        <a class="brand" href="home.php">
            <span class="brand-mark"><?= icon('leaf') ?></span>
            <span>Food<b>Share</b></span>
        </a>

        <div>
        <p class="nav-label">Menu</p>
        <nav class="side-nav" aria-label="Main">
            <?php foreach ($navItems as [$key, $label, $href, $ico]): ?>
                <a href="<?= e($href) ?>" class="<?= $key === $current ? 'active' : '' ?>">
                    <?= icon($ico) ?><span><?= e($label) ?></span>
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
            <form method="post" action="logout.php" class="inline-form">
                <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                <button class="icon-btn" type="submit" title="Log out" aria-label="Log out"><?= icon('logout') ?></button>
            </form>
        </div>
    </aside>
    <div class="main">
<?php endif; ?>
