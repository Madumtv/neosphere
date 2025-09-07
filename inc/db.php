<?php
// Connexion PDO à la base neosphere
// Hôte de la base; peut être "host" ou "host:port"
$DB_HOST = 'localhost:3306';
$DB_NAME = 'neosphere';
$DB_USER = 'neosphere'; // ajuster si besoin
$DB_PASS = 'didilulu2815';     // ajuster si besoin
$DB_CHARSET = 'utf8mb4';

$pdo = null;
try {
    // Gérer le cas "host:port"
    $host = $DB_HOST;
    $port = null;
    if (strpos($DB_HOST, ':') !== false) {
        list($host, $port) = explode(':', $DB_HOST, 2);
        $port = (int)$port;
    }
    $dsn = "mysql:host={$host};";
    if ($port) {
        $dsn .= "port={$port};";
    }
    $dsn .= "dbname={$DB_NAME};charset={$DB_CHARSET}";

    $pdo = new PDO($dsn, $DB_USER, $DB_PASS, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
} catch (Throwable $e) {
    // On ne casse pas l'affichage public si la DB est HS; tracer discrètement.
    $pdo = null;
    error_log('[DB] Connexion échouée: '.$e->getMessage());
    // Si le projet a le fichier ENABLE_DEBUG à la racine, afficher l'erreur dans le navigateur pour debug local
    if (file_exists(__DIR__ . '/../ENABLE_DEBUG')) {
        echo '<pre style="color:red">[DB] Connexion échouée: '.htmlspecialchars($e->getMessage())."\nDSN: ".htmlspecialchars(
            isset($dsn) ? $dsn : 'non disponible'
        ).'</pre>';
    }
}
