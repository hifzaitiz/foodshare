<?php
// Expects $pageTitle (string). Optional: $bodyClass.
$pageTitle = $pageTitle ?? 'FoodShare';
$bodyClass = $bodyClass ?? '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($pageTitle) ?> | FoodShare</title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body class="<?= e($bodyClass) ?>">
<?php if (is_logged_in()): ?>
<nav class="navbar">
    <a class="brand" href="home.php">&#127858; FoodShare</a>
    <div class="nav-right">
        <span class="user-name"><?= e($_SESSION['name']) ?></span>
        <span class="badge badge-<?= e($_SESSION['role']) ?>"><?= e($_SESSION['role']) ?></span>
        <form method="post" action="logout.php" class="inline-form">
            <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
            <button class="btn btn-outline" type="submit">Log out</button>
        </form>
    </div>
</nav>
<?php endif; ?>
