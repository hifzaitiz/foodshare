<?php
require_once __DIR__ . '/includes/auth.php';
require_role('admin');
require_post();

$pdo  = db();
$id   = (int) ($_POST['id'] ?? 0);
$type = $_POST['type'] ?? '';

if ($type === 'ngo') {
    $done = $pdo->prepare('UPDATE ngos SET is_verified = 1 WHERE id = ? AND is_verified = 0');
    $done->execute([$id]);
    if ($done->rowCount() > 0) {
        flash('success', 'NGO verified.');
    } else {
        flash('error', 'NGO not found or already verified.');
    }
} elseif ($type === 'volunteer') {
    $done = $pdo->prepare("UPDATE users SET is_verified = 1 WHERE id = ? AND role = 'volunteer' AND is_verified = 0");
    $done->execute([$id]);
    if ($done->rowCount() > 0) {
        flash('success', 'Volunteer verified.');
    } else {
        flash('error', 'Volunteer not found or already verified.');
    }
} else {
    flash('error', 'Unknown verification request.');
}
header('Location: home.php');
