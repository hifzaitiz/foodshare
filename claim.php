<?php
require_once __DIR__ . '/includes/auth.php';
require_role('ngo', 'volunteer');
require_post();

$pdo    = db();
$userId = $_SESSION['user_id'];
$postId = (int) ($_POST['post_id'] ?? 0);

if (!can_claim($pdo, $userId, $_SESSION['role'])) {
    flash('error', 'Only verified volunteers and members of verified NGOs can claim food.');
    header('Location: home.php');
    exit;
}

$pdo->beginTransaction();
try {
    // Lock the row so two simultaneous claims cannot both succeed.
    $stmt = $pdo->prepare('SELECT status, pickup_until FROM food_posts WHERE id = ? FOR UPDATE');
    $stmt->execute([$postId]);
    $post = $stmt->fetch();

    if (!$post || $post['status'] !== 'available' || strtotime($post['pickup_until']) <= time()) {
        $pdo->rollBack();
        flash('error', 'Sorry, this food is no longer available.');
    } else {
        $pdo->prepare('INSERT INTO claims (post_id, claimed_by, ngo_id) VALUES (?, ?, ?)')
            ->execute([$postId, $userId, verified_ngo_id($pdo, $userId)]);
        $pdo->prepare("UPDATE food_posts SET status = 'claimed' WHERE id = ?")->execute([$postId]);
        log_status($pdo, $postId, $userId, 'available', 'claimed');
        $pdo->commit();
        flash('success', 'Food claimed. Please collect it before the pickup time ends.');
    }
} catch (Throwable $ex) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    flash('error', 'Could not claim this food. Please try again.');
}
header('Location: home.php');
