<?php
// Debug: afficher toutes les erreurs pour le développement
@ini_set('display_errors', '1');
@ini_set('display_startup_errors', '1');
error_reporting(E_ALL);

// Connexion PDO — adapter user/password si nécessaire
$host = 'localhost:3306';
$db   = 'neosphere';
$user = 'neosphere';
$pass = 'didilulu2815';
$charset = 'utf8mb4';

$dsn = "mysql:host={$host};dbname={$db};charset={$charset}";
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
];

try {
    $pdo = new PDO($dsn, $user, $pass, $options);
} catch (Exception $e) {
    http_response_code(500);
    echo "Erreur de connexion BDD.";
    exit;
}