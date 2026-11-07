<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/ui.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

function e(?string $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function is_logged_in(): bool
{
    return isset($_SESSION['user_id']);
}

function require_login(): void
{
    if (!is_logged_in()) {
        header('Location: login.php');
        exit;
    }
}

function require_role(string ...$roles): void
{
    require_login();
    if (!in_array($_SESSION['role'], $roles, true)) {
        http_response_code(403);
        exit('Access denied.');
    }
}

function fmt_time(?string $dt): string
{
    return $dt ? date('d M, g:i a', strtotime($dt)) : '-';
}

function csrf_token(): string
{
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(16));
    }
    return $_SESSION['csrf'];
}

// Every state-changing page is POST-only and must carry the session token.
function require_post(): void
{
    if ($_SERVER['REQUEST_METHOD'] !== 'POST'
        || !hash_equals($_SESSION['csrf'] ?? '', $_POST['csrf'] ?? '')) {
        http_response_code(400);
        exit('Invalid request.');
    }
}

function flash(string $type, string $message): void
{
    $_SESSION['flash'] = ['type' => $type, 'message' => $message];
}

function log_status(PDO $pdo, int $postId, int $userId, ?string $old, string $new): void
{
    $pdo->prepare('INSERT INTO post_status_logs (post_id, changed_by, old_status, new_status) VALUES (?, ?, ?, ?)')
        ->execute([$postId, $userId, $old, $new]);
}

// Id of a verified NGO the user belongs to, or null.
function verified_ngo_id(PDO $pdo, int $userId): ?int
{
    $stmt = $pdo->prepare(
        'SELECT n.id FROM ngo_members m JOIN ngos n ON n.id = m.ngo_id
         WHERE m.user_id = ? AND n.is_verified = 1 LIMIT 1'
    );
    $stmt->execute([$userId]);
    $id = $stmt->fetchColumn();
    return $id === false ? null : (int) $id;
}

// A claimer is eligible if they belong to a verified NGO, or are a verified volunteer.
function can_claim(PDO $pdo, int $userId, string $role): bool
{
    if (verified_ngo_id($pdo, $userId) !== null) {
        return true;
    }
    if ($role !== 'volunteer') {
        return false;
    }
    $stmt = $pdo->prepare('SELECT is_verified FROM users WHERE id = ?');
    $stmt->execute([$userId]);
    return (int) $stmt->fetchColumn() === 1;
}

function login_user(array $user): void
{
    session_regenerate_id(true);
    $_SESSION['user_id'] = (int) $user['id'];
    $_SESSION['name']    = $user['full_name'];
    $_SESSION['role']    = $user['role'];
}
