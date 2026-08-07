<?php
// ============================================================
//  logout.php — Session Destroy & Redirect
// ============================================================
session_start();

if (!empty($_SESSION['user_id'])) {
    require_once __DIR__ . '/includes/db.php';
    $pdo->prepare(
        "INSERT INTO activity_log (user_id, action, description, ip_address)
         VALUES (?, 'logout', 'User logged out', ?)"
    )->execute([$_SESSION['user_id'], $_SERVER['REMOTE_ADDR'] ?? '']);
}

$_SESSION = [];

if (ini_get('session.use_cookies')) {
    $p = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $p['path'], $p['domain'], $p['secure'], $p['httponly']);
}

session_destroy();
header('Location: index.php');
exit;
