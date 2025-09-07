<?php
// Auth helper centralisé
if (session_status() !== PHP_SESSION_ACTIVE) session_start();

function auth_init_csrf(): void {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
}

auth_init_csrf();

function auth_user(): ?array {
    if (!empty($_SESSION['user_id'])) {
        return [
            'id' => (int)$_SESSION['user_id'],
            'username' => $_SESSION['username'] ?? ($_SESSION['user'] ?? null),
            'role_id' => isset($_SESSION['role_id']) ? (int)$_SESSION['role_id'] : null,
        ];
    }
    if (!empty($_SESSION['user'])) {
        return [
            'id' => null,
            'username' => $_SESSION['user'],
            'role_id' => isset($_SESSION['role_id']) ? (int)$_SESSION['role_id'] : null,
        ];
    }
    return null;
}

function auth_is_admin(): bool {
    if (!empty($_SESSION['role']) && $_SESSION['role'] === 'admin') return true;
    if (isset($_SESSION['role_id']) && (int)$_SESSION['role_id'] === 1) return true;
    if (!empty($_SESSION['is_admin'])) return true;
    if (!empty($_SESSION['user']) && $_SESSION['user'] === 'admin') return true;
    return false;
}

function auth_require(): void {
    if (!auth_user()) {
        header('Location: /membre/login.php');
        exit;
    }
}

function auth_require_admin(): void {
    if (!auth_is_admin()) {
        http_response_code(403);
        echo 'Accès refusé';
        exit;
    }
}

function auth_csrf_token(): string { return $_SESSION['csrf_token']; }

function auth_csrf_check(?string $token): bool {
    return $token && hash_equals($_SESSION['csrf_token'] ?? '', $token);
}

function auth_regenerate_on_login(): void {
    if (empty($_SESSION['__regenerated'])) {
        session_regenerate_id(true);
        $_SESSION['__regenerated'] = true;
    }
}
