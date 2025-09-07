<?php
// Auth V2 utilisant Better Auth microservice
if (session_status() !== PHP_SESSION_ACTIVE) session_start();
require_once __DIR__.'/better_auth_client.php';

// Stocke le token de session Better Auth dans un cookie HttpOnly
function authv2_store_token(string $token): void {
    setcookie('ba_session', $token, [
        'expires' => time() + 60*60*24*7,
        'path' => '/',
        'secure' => isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on',
        'httponly' => true,
        'samesite' => 'Lax'
    ]);
    $_SESSION['ba_session'] = $token;
}

function authv2_current_session(): ?array {
    $token = $_COOKIE['ba_session'] ?? ($_SESSION['ba_session'] ?? null);
    if (!$token) return null;
    static $cache = null; static $cacheToken = null;
    if ($cache && $cacheToken === $token) return $cache;
    $res = better_auth_verify($token);
    if (!empty($res['valid']) && !empty($res['session'])) {
        $cache = $res['session'];
        $cacheToken = $token;
        return $cache;
    }
    return null;
}

function authv2_require(): void {
    if (!authv2_current_session()) {
        header('Location: /membrev2/login.php');
        exit;
    }
}

function authv2_logout(): void {
    setcookie('ba_session', '', time()-3600, '/');
    unset($_SESSION['ba_session']);
}
