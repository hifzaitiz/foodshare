<?php
require_once __DIR__ . '/includes/auth.php';
require_role('admin');
require_post();

$pdo  = db();
$id   = (int) ($_POST['id'] ?? 0);
$type = $_POST['type'] ?? '';

if ($type === 'ngo') {
    $done = $pdo->prepare('UPDATE ngos SET is_verified = 1 WHERE id = ?');
    $done->execute([$id]);
    flash('success', 'NGO verified.');
} elseif ($type === 'volunteer') {
    $done = $pdo->prepare("UPDATE users SET is_verified = 1 WHERE id = ? AND role = 'volunteer'");
    $done->execute([$id]);
    flash('success', 'Volunteer verified.');
} else {
    flash('error', 'Unknown verification request.');
}
header('Location: home.php');
