<?php
require_once __DIR__ . '/includes/auth.php';
require_role('donor');
require_post();

$pdo    = db();
$userId = $_SESSION['user_id'];
$postId = (int) ($_POST['post_id'] ?? 0);
$code   = preg_replace('/\D/', '', (string) ($_POST['code'] ?? ''));   // allow "482 731"
$back   = ($_POST['from'] ?? '') === 'post' ? 'post.php?id=' . $postId : 'home.php';

if ($locked = code_locked_for($postId)) {
    flash('error', 'Too many wrong codes. Try again in ' . ceil($locked / 60) . ' minutes.');
    header('Location: ' . $back);
    exit;
}

$pdo->beginTransaction();
try {
    // Lock the post and load its claim; only the donor of a claimed post can confirm pickup.
    $stmt = $pdo->prepare(
        "SELECT c.id, c.post_id, c.claimed_by, c.claimed_at
         FROM food_posts p JOIN claims c ON c.post_id = p.id
         WHERE p.id = ? AND p.donor_id = ? AND p.status = 'claimed' FOR UPDATE"
    );
    $stmt->execute([$postId, $userId]);
    $claim = $stmt->fetch();

    if (!$claim) {
        $pdo->rollBack();
        flash('error', 'This post cannot be marked as collected.');
    } elseif (!hash_equals(pickup_code((int) $claim['id'], (int) $claim['post_id'], (int) $claim['claimed_by'], $claim['claimed_at']), $code)) {
        $pdo->rollBack();
        code_failed($postId);
        $left = CODE_MAX_TRIES - ($_SESSION['code_tries'][$postId]['count'] ?? 0);
        flash('error', $code === ''
            ? 'Enter the 6-digit pickup code shown to the collector.'
            : 'That pickup code is not correct.' . ($left > 0 ? " $left tries left." : ' Locked for 10 minutes.'));
    } else {
        $pdo->prepare('UPDATE claims SET collected_at = NOW() WHERE post_id = ?')->execute([$postId]);
        $pdo->prepare("UPDATE food_posts SET status = 'collected' WHERE id = ?")->execute([$postId]);
        log_status($pdo, $postId, $userId, 'claimed', 'collected');
        $pdo->commit();
        unset($_SESSION['code_tries'][$postId]);
        flash('success', 'Code accepted. Marked as collected. Thank you for donating!');
    }
} catch (Throwable $ex) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    flash('error', 'Could not update the post. Please try again.');
}
header('Location: ' . $back);
