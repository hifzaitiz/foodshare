<?php
require_once __DIR__ . '/includes/auth.php';
require_role('donor');
require_post();

$pdo    = db();
$userId = $_SESSION['user_id'];
$postId = (int) ($_POST['post_id'] ?? 0);

$pdo->beginTransaction();
try {
    $stmt = $pdo->prepare("SELECT id FROM food_posts WHERE id = ? AND donor_id = ? AND status = 'claimed' FOR UPDATE");
    $stmt->execute([$postId, $userId]);

    if ($stmt->fetchColumn() === false) {
        $pdo->rollBack();
        flash('error', 'This post cannot be marked as collected.');
    } else {
        $pdo->prepare('UPDATE claims SET collected_at = NOW() WHERE post_id = ?')->execute([$postId]);
        $pdo->prepare("UPDATE food_posts SET status = 'collected' WHERE id = ?")->execute([$postId]);
        log_status($pdo, $postId, $userId, 'claimed', 'collected');
        $pdo->commit();
        flash('success', 'Marked as collected. Thank you for donating!');
    }
} catch (Throwable $ex) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    flash('error', 'Could not update the post. Please try again.');
}
header('Location: home.php');
