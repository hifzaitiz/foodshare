<?php
require_once __DIR__ . '/includes/auth.php';
require_role('admin');
require_post();

$pdo    = db();
$id     = (int) ($_POST['id'] ?? 0);
$type   = $_POST['type'] ?? '';
$action = $_POST['action'] ?? 'verify';   // verify | reject

// Both actions only touch requests that are still pending (not verified, not rejected).
$queries = [
    'ngo' => [
        'verify' => 'UPDATE ngos SET is_verified = 1 WHERE id = ? AND is_verified = 0 AND rejected_at IS NULL',
        'reject' => 'UPDATE ngos SET rejected_at = NOW() WHERE id = ? AND is_verified = 0 AND rejected_at IS NULL',
    ],
    'volunteer' => [
        'verify' => "UPDATE users SET is_verified = 1 WHERE id = ? AND role = 'volunteer' AND is_verified = 0 AND rejected_at IS NULL",
        'reject' => "UPDATE users SET rejected_at = NOW() WHERE id = ? AND role = 'volunteer' AND is_verified = 0 AND rejected_at IS NULL",
    ],
];

if (!isset($queries[$type][$action])) {
    flash('error', 'Unknown verification request.');
} else {
    $done = $pdo->prepare($queries[$type][$action]);
    $done->execute([$id]);
    $label = $type === 'ngo' ? 'NGO' : 'Volunteer';
    if ($done->rowCount() > 0) {
        flash('success', $label . ($action === 'verify' ? ' verified.' : ' rejected.'));
    } else {
        flash('error', $label . ' not found or already handled.');
    }
}
header('Location: home.php');
