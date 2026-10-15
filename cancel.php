<?php
require_once __DIR__ . '/includes/auth.php';
require_role('ngo', 'volunteer');
require_post();

$pdo    = db();
$userId = $_SESSION['user_id'];
$postId = (int) ($_POST['post_id'] ?? 0);

$pdo->beginTransaction();
try {
    $stmt = $pdo->prepare(
        "SELECT c.id FROM claims c JOIN food_posts p ON p.id = c.post_id
         WHERE c.post_id = ? AND c.claimed_by = ? AND p.status = 'claimed' FOR UPDATE"
    );
    $stmt->execute([$postId, $userId]);
    $claimId = $stmt->fetchColumn();

    if ($claimId === false) {
        $pdo->rollBack();
        flash('error', 'This claim cannot be cancelled.');
    } else {
        // The claim row is removed so the post can be claimed again.
        $pdo->prepare('DELETE FROM claims WHERE id = ?')->execute([$claimId]);
        $pdo->prepare("UPDATE food_posts SET status = 'available' WHERE id = ?")->execute([$postId]);
        log_status($pdo, $postId, $userId, 'claimed', 'available');
        $pdo->commit();
        flash('success', 'Claim cancelled. The food is available again.');
    }
} catch (Throwable $ex) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    flash('error', 'Could not cancel the claim. Please try again.');
}
header('Location: home.php');
