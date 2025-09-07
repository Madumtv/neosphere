<?php
// Client léger pour interagir avec le micro-service Better Auth (Node)
// Hypothèses: service lancé sur http://localhost:4001
if (!defined('BETTER_AUTH_URL')) {
    define('BETTER_AUTH_URL', getenv('BETTER_AUTH_URL') ?: 'http://localhost:4001');
}

function better_auth_http_post(string $path, array $data): array {
    $url = rtrim(BETTER_AUTH_URL,'/').$path;
    $ctx = stream_context_create([
        'http' => [
            'method' => 'POST',
            'header' => "Content-Type: application/json\r\n",
            'content' => json_encode($data),
            'timeout' => 5,
        ]
    ]);
    $raw = @file_get_contents($url, false, $ctx);
    if ($raw === false) {
        return ['error' => 'fetch_failed'];
    }
    $decoded = json_decode($raw, true);
    if (!is_array($decoded)) return ['error' => 'invalid_json'];
    return $decoded;
}

function better_auth_signup(string $email, string $password): array {
    return better_auth_http_post('/api/auth/signup', compact('email','password'));
}

function better_auth_login(string $email, string $password): array {
    return better_auth_http_post('/api/auth/login', compact('email','password'));
}

function better_auth_verify(string $token): array {
    return better_auth_http_post('/api/auth/verify', compact('token'));
}
