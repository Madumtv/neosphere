<?php
// list_services_debug.php
@ini_set('display_errors',1);
@ini_set('display_startup_errors',1);
error_reporting(E_ALL);
require_once __DIR__ . '/../inc/db.php';

?><!doctype html>
<html lang="fr">
<head>
<meta charset="utf-8">
<title>Debug - services</title>
<style>body{font-family:Arial,Helvetica,sans-serif;margin:20px}table{border-collapse:collapse;width:100%}th,td{border:1px solid #ccc;padding:8px;text-align:left}th{background:#f5f5f5}</style>
</head>
<body>
<h1>Services (prestation)</h1>
<p>Ceci affiche les services disponibles (colonne id utilisée comme prestation_id dans les tests).</p>
<p><a href="list_appointments_debug.php">← Voir appointments</a></p>
<?php
if (!$pdo) {
    echo '<p style="color:red">Erreur: connexion DB non initialisée</p>';
    exit;
}
try {
    $stmt = $pdo->query('SELECT id, title, duration_minutes, active FROM services ORDER BY id ASC');
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    if (!$rows) {
        echo '<p>Aucun service trouvé.</p>';
    } else {
        echo '<table><tr><th>id</th><th>title</th><th>duration_minutes</th><th>active</th></tr>';
        foreach ($rows as $r) {
            echo '<tr>';
            echo '<td>'.htmlspecialchars($r['id'] ?? '').'</td>';
            echo '<td>'.htmlspecialchars($r['title'] ?? '').'</td>';
            echo '<td>'.htmlspecialchars($r['duration_minutes'] ?? '').'</td>';
            echo '<td>'.htmlspecialchars($r['active'] ?? '').'</td>';
            echo '</tr>';
        }
        echo '</table>';
    }
} catch (Throwable $e) {
    echo '<p style="color:red">Erreur SQL: '.htmlspecialchars($e->getMessage()).'</p>';
}
?>
</body>
</html>
