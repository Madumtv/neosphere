<?php
// Connexion PDO à la base neosphere
$DB_HOST = 'localhost:3306';
$DB_NAME = 'neosphere';
$DB_USER = 'neosphere'; // ajuster si besoin
$DB_PASS = 'didilulu2815';     // ajuster si besoin
$DB_CHARSET = 'utf8mb4';

$pdo = null;
try {
    $pdo = new PDO("mysql:host=$DB_HOST;dbname=$DB_NAME;charset=$DB_CHARSET", $DB_USER, $DB_PASS, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
} catch (Throwable $e) {
    // On ne casse pas l'affichage public si la DB est HS; tracer discrètement.
    $pdo = null;
    error_log('[DB] Connexion échouée: '.$e->getMessage());
}
