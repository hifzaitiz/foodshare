<?php
require_once __DIR__ . '/includes/auth.php';
require_role('donor');
require_post();

$pdo    = db();
$userId = $_SESSION['user_id'];
$postId = (int) ($_POST['post_id'] ?? 0);

$pdo->beginTransaction();
try {
    // Only the donor's own post, and only while nobody has claimed it.
    $stmt = $pdo->prepare("SELECT id FROM food_posts WHERE id = ? AND donor_id = ? AND status = 'available' FOR UPDATE");
    $stmt->execute([$postId, $userId]);

    if ($stmt->fetchColumn() === false) {
        $pdo->rollBack();
        flash('error', 'This post cannot be withdrawn. It may already be claimed.');
    } else {
        $pdo->prepare("UPDATE food_posts SET status = 'withdrawn' WHERE id = ?")->execute([$postId]);
        log_status($pdo, $postId, $userId, 'available', 'withdrawn');
        $pdo->commit();
        flash('success', 'Post withdrawn. It is no longer visible to NGOs and volunteers.');
    }
} catch (Throwable $ex) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    flash('error', 'Could not withdraw the post. Please try again.');
}
header('Location: home.php');
