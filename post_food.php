<?php
require_once __DIR__ . '/includes/auth.php';
require_role('donor');

$pdo = db();
$categories = $pdo->query('SELECT id, name FROM food_categories ORDER BY name')->fetchAll();

$errors = [];
$old = ['title' => '', 'category_id' => '', 'quantity' => '', 'unit' => 'plates', 'description' => '',
        'pickup_from' => '', 'pickup_until' => '', 'address' => '', 'area' => '', 'city' => ''];

// Edit mode: post_food.php?id=N edits one of the donor's own posts, only while it is still available.
$editId = (int) ($_GET['id'] ?? 0);
if ($editId > 0) {
    $stmt = $pdo->prepare(
        "SELECT p.title, p.category_id, p.quantity, p.unit, p.description, p.pickup_from, p.pickup_until,
                l.address, l.area, l.city
         FROM food_posts p LEFT JOIN locations l ON l.id = p.pickup_location_id
         WHERE p.id = ? AND p.donor_id = ? AND p.status = 'available'"
    );
    $stmt->execute([$editId, $_SESSION['user_id']]);
    $existing = $stmt->fetch();
    if (!$existing) {
        flash('error', 'This post can no longer be edited (it may have been claimed or withdrawn).');
        header('Location: home.php');
        exit;
    }
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        foreach ($old as $k => $_) {
            $old[$k] = (string) ($existing[$k] ?? '');
        }
        // datetime-local inputs need the "2026-11-07T14:30" format
        $old['pickup_from']  = date('Y-m-d\TH:i', strtotime($existing['pickup_from']));
        $old['pickup_until'] = date('Y-m-d\TH:i', strtotime($existing['pickup_until']));
    }
}

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

    if (!$errors && $editId > 0) {
        $pdo->beginTransaction();
        try {
            // Re-check under a row lock: someone may have claimed it while the form was open.
            $lock = $pdo->prepare("SELECT id FROM food_posts WHERE id = ? AND donor_id = ? AND status = 'available' FOR UPDATE");
            $lock->execute([$editId, $_SESSION['user_id']]);
            if ($lock->fetchColumn() === false) {
                $pdo->rollBack();
                flash('error', 'This post was claimed or withdrawn before your changes were saved.');
                header('Location: home.php');
                exit;
            }
            // New location row, so posts that shared the old address are not changed.
            $pdo->prepare('INSERT INTO locations (address, area, city) VALUES (?, ?, ?)')
                ->execute([$old['address'], $old['area'], $old['city']]);
            $locationId = (int) $pdo->lastInsertId();

            $pdo->prepare(
                'UPDATE food_posts SET category_id = ?, pickup_location_id = ?, title = ?, description = ?,
                        quantity = ?, unit = ?, pickup_from = ?, pickup_until = ?
                 WHERE id = ?'
            )->execute([(int) $old['category_id'], $locationId, $old['title'], $old['description'] ?: null,
                        (int) $old['quantity'], $old['unit'], date('Y-m-d H:i:s', $from), date('Y-m-d H:i:s', $until), $editId]);
            $pdo->commit();
            flash('success', 'Your post has been updated.');
            header('Location: home.php');
            exit;
        } catch (Throwable $ex) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $errors[] = 'Could not save your changes. Please try again.';
        }
    }

    if (!$errors && $editId === 0) {
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

$pageTitle = $editId ? 'Edit post' : 'Post food';
$activeNav = $editId ? 'home' : 'post_food';
require __DIR__ . '/includes/header.php';
?>
<main class="content">
    <header class="page-head">
        <div>
            <p class="eyebrow"><a href="home.php">Dashboard</a> / <?= $editId ? 'Edit post' : 'Post food' ?></p>
            <h1><?= $editId ? 'Edit your post' : 'Post surplus food' ?></h1>
            <p class="muted"><?= $editId
                ? 'You can change details while nobody has claimed it. Extending the pickup time makes an expired post claimable again.'
                : 'NGOs and verified volunteers will see this right away and can claim it.' ?></p>
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
                <button type="submit" class="btn btn-primary btn-lg"><?= icon('check') ?> <?= $editId ? 'Save changes' : 'Publish post' ?></button>
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
