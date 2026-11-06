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
<main class="content">
    <header class="page-head">
        <div>
            <p class="eyebrow"><a href="home.php">Dashboard</a> / Post food</p>
            <h1>Post surplus food</h1>
            <p class="muted">NGOs and verified volunteers will see this right away and can claim it.</p>
        </div>
    </header>

    <?php if ($errors): ?>
        <div class="alert alert-error"><?= icon('alert') ?>
            <ul><?php foreach ($errors as $err): ?><li><?= e($err) ?></li><?php endforeach; ?></ul>
        </div>
    <?php endif; ?>

    <div class="form-layout">
        <form method="post" id="postForm" class="card form-card" novalidate>
            <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">

            <fieldset class="form-section">
                <legend><span class="step">1</span> What are you sharing?</legend>

                <label for="title">Food title</label>
                <input type="text" id="title" name="title" value="<?= e($old['title']) ?>" placeholder="e.g. Chicken biryani" required>

                <div class="row-2">
                    <div>
                        <label for="category_id">Category</label>
                        <select id="category_id" name="category_id" required>
                            <option value="">Choose...</option>
                            <?php foreach ($categories as $c): ?>
                                <option value="<?= (int) $c['id'] ?>" <?= (string) $c['id'] === $old['category_id'] ? 'selected' : '' ?>><?= e($c['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="row-2 row-tight">
                        <div>
                            <label for="quantity">Quantity</label>
                            <input type="number" id="quantity" name="quantity" min="1" value="<?= e($old['quantity']) ?>" placeholder="40" required>
                        </div>
                        <div>
                            <label for="unit">Unit</label>
                            <input type="text" id="unit" name="unit" value="<?= e($old['unit']) ?>" list="unit-options" required>
                            <datalist id="unit-options"><option value="plates"><option value="kg"><option value="packs"><option value="boxes"><option value="trays"></datalist>
                        </div>
                    </div>
                </div>

                <label for="description">Description <span class="opt">optional</span></label>
                <textarea id="description" name="description" rows="3" placeholder="How it is packed, ingredients, anything the collector should know"><?= e($old['description']) ?></textarea>
            </fieldset>

            <fieldset class="form-section">
                <legend><span class="step">2</span> When can it be picked up?</legend>
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
            </fieldset>

            <fieldset class="form-section">
                <legend><span class="step">3</span> Where should they come?</legend>
                <label for="address">Pickup address</label>
                <input type="text" id="address" name="address" value="<?= e($old['address']) ?>" placeholder="Street, building, landmark" required>

                <div class="row-2">
                    <div>
                        <label for="area">Area</label>
                        <input type="text" id="area" name="area" value="<?= e($old['area']) ?>" placeholder="e.g. Gulberg" required>
                    </div>
                    <div>
                        <label for="city">City</label>
                        <input type="text" id="city" name="city" value="<?= e($old['city']) ?>" placeholder="e.g. Lahore" required>
                    </div>
                </div>
            </fieldset>

            <div class="form-actions">
                <a class="btn btn-ghost" href="home.php">Cancel</a>
                <button type="submit" class="btn btn-primary btn-lg"><?= icon('check') ?> Publish post</button>
            </div>
        </form>

        <aside class="card tips">
            <h3><?= icon('leaf') ?> Tips for a quick pickup</h3>
            <ul>
                <li><?= icon('clock') ?><span>Give at least <strong>2 hours</strong> in the pickup window so collectors can reach you.</span></li>
                <li><?= icon('box') ?><span>Pack food in <strong>sealed containers</strong> and mention it in the description.</span></li>
                <li><?= icon('pin') ?><span>Add a <strong>landmark</strong> to the address to avoid delays.</span></li>
                <li><?= icon('check-circle') ?><span>When the food is picked up, press <strong>Mark collected</strong> on your dashboard.</span></li>
            </ul>
        </aside>
    </div>
</main>
<?php require __DIR__ . '/includes/footer.php'; ?>
