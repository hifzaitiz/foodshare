<?php
require_once __DIR__ . '/includes/auth.php';
require_role('donor');

$pdo = db();
$categories = $pdo->query('SELECT id, name FROM food_categories ORDER BY name')->fetchAll();

$errors = [];
$old = ['title' => '', 'category_id' => '', 'quantity' => '', 'unit' => 'plates', 'description' => '',
        'pickup_from' => '', 'pickup_until' => '', 'address' => '', 'area' => '', 'city' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_post();
    foreach ($old as $k => $_) {
        $old[$k] = trim($_POST[$k] ?? '');
    }

    $from  = strtotime($old['pickup_from']);
    $until = strtotime($old['pickup_until']);
    $catIds = array_column($categories, 'id');

    if ($old['title'] === '' || mb_strlen($old['title']) > 150) {
        $errors[] = 'Title is required (max 150 characters).';
    }
    if (!in_array((int) $old['category_id'], array_map('intval', $catIds), true)) {
        $errors[] = 'Choose a food category.';
    }
    if (!ctype_digit($old['quantity']) || (int) $old['quantity'] < 1) {
        $errors[] = 'Quantity must be a whole number of at least 1.';
    }
    if ($old['unit'] === '' || mb_strlen($old['unit']) > 30) {
        $errors[] = 'Unit is required (for example plates, kg, packs).';
    }
    if (!$from || !$until) {
        $errors[] = 'Enter both pickup times.';
    } elseif ($until <= $from) {
        $errors[] = 'Pickup end time must be after the start time.';
    } elseif ($until <= time()) {
        $errors[] = 'Pickup end time must be in the future.';
    }
    if ($old['address'] === '' || $old['area'] === '' || $old['city'] === '') {
        $errors[] = 'Pickup address, area and city are required.';
    }

    if (!$errors) {
        $pdo->beginTransaction();
        try {
            $pdo->prepare('INSERT INTO locations (address, area, city) VALUES (?, ?, ?)')
                ->execute([$old['address'], $old['area'], $old['city']]);
            $locationId = (int) $pdo->lastInsertId();

            $pdo->prepare(
                "INSERT INTO food_posts (donor_id, category_id, pickup_location_id, title, description, quantity, unit, pickup_from, pickup_until, status)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'available')"
            )->execute([$_SESSION['user_id'], (int) $old['category_id'], $locationId, $old['title'],
                        $old['description'] ?: null, (int) $old['quantity'], $old['unit'],
                        date('Y-m-d H:i:s', $from), date('Y-m-d H:i:s', $until)]);
            $postId = (int) $pdo->lastInsertId();

            log_status($pdo, $postId, $_SESSION['user_id'], null, 'available');
            $pdo->commit();
            flash('success', 'Your food has been posted.');
            header('Location: home.php');
            exit;
        } catch (Throwable $ex) {
            $pdo->rollBack();
            $errors[] = 'Could not save your post. Please try again.';
        }
    }
}

$pageTitle = 'Post food';
require __DIR__ . '/includes/header.php';
?>
<main class="container">
    <div class="card form-card">
        <h1>Post surplus food</h1>
        <p class="muted">NGOs and verified volunteers will see this and can claim it.</p>

        <?php if ($errors): ?>
            <div class="alert alert-error">
                <ul><?php foreach ($errors as $err): ?><li><?= e($err) ?></li><?php endforeach; ?></ul>
            </div>
        <?php endif; ?>

        <form method="post" id="postForm" novalidate>
            <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">

            <label for="title">Food title</label>
            <input type="text" id="title" name="title" value="<?= e($old['title']) ?>" placeholder="e.g. Chicken biryani" required>

            <label for="category_id">Category</label>
            <select id="category_id" name="category_id" required>
                <option value="">Choose...</option>
                <?php foreach ($categories as $c): ?>
                    <option value="<?= (int) $c['id'] ?>" <?= (string) $c['id'] === $old['category_id'] ? 'selected' : '' ?>><?= e($c['name']) ?></option>
                <?php endforeach; ?>
            </select>

            <div class="row-2">
                <div>
                    <label for="quantity">Quantity</label>
                    <input type="number" id="quantity" name="quantity" min="1" value="<?= e($old['quantity']) ?>" required>
                </div>
                <div>
                    <label for="unit">Unit</label>
                    <input type="text" id="unit" name="unit" value="<?= e($old['unit']) ?>" required>
                </div>
            </div>

            <label for="description">Description (optional)</label>
            <input type="text" id="description" name="description" value="<?= e($old['description']) ?>">

            <div class="row-2">
                <div>
                    <label for="pickup_from">Pickup from</label>
                    <input type="datetime-local" id="pickup_from" name="pickup_from" value="<?= e($old['pickup_from']) ?>" required>
                </div>
                <div>
                    <label for="pickup_until">Pickup until</label>
                    <input type="datetime-local" id="pickup_until" name="pickup_until" value="<?= e($old['pickup_until']) ?>" required>
                </div>
            </div>

            <label for="address">Pickup address</label>
            <input type="text" id="address" name="address" value="<?= e($old['address']) ?>" required>

            <div class="row-2">
                <div>
                    <label for="area">Area</label>
                    <input type="text" id="area" name="area" value="<?= e($old['area']) ?>" required>
                </div>
                <div>
                    <label for="city">City</label>
                    <input type="text" id="city" name="city" value="<?= e($old['city']) ?>" required>
                </div>
            </div>

            <button type="submit" class="btn btn-primary">Post food</button>
            <a class="btn btn-outline" href="home.php">Cancel</a>
        </form>
    </div>
</main>
<?php require __DIR__ . '/includes/footer.php'; ?>
