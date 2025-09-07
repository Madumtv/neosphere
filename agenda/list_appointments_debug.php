<?php
// list_appointments_debug.php
// Usage: open dans le navigateur, optionnel: ?date=YYYY-MM-DD&prestation_id=20
@ini_set('display_errors',1);
@ini_set('display_startup_errors',1);
error_reporting(E_ALL);
require_once __DIR__ . '/../inc/db.php';

$date = isset($_GET['date']) ? $_GET['date'] : date('Y-m-d');
$prestationId = isset($_GET['prestation_id']) ? intval($_GET['prestation_id']) : 0;

?><!doctype html>
<html lang="fr">
<head>
<meta charset="utf-8">
<title>Debug - appointments</title>
<style>body{font-family:Arial,Helvetica,sans-serif;margin:20px}table{border-collapse:collapse;width:100%}th,td{border:1px solid #ccc;padding:8px;text-align:left}th{background:#f5f5f5}</style>
</head>
<body>
<h1>Debug appointments</h1>
<p>Date: <strong><?php echo htmlspecialchars($date) ?></strong> &nbsp; Prestation_id: <strong><?php echo $prestationId ?: '(toutes)' ?></strong></p>
<p>
<a href="insert_test_appointment.php?prestation_id=20&date=<?php echo $date ?>&time=09:00&user_id=1">Insérer test prestation 20 09:00</a> |
<a href="insert_test_appointment.php?prestation_id=20&date=<?php echo $date ?>&time=10:00&user_id=1">Insérer test prestation 20 10:00</a>
</p>
<?php
if (!$pdo) {
    echo '<p style="color:red">Erreur: connexion DB non initialisée</p>';
    exit;
}

try {
    if ($prestationId) {
        $stmt = $pdo->prepare("SELECT * FROM appointments WHERE service_id = ? AND DATE(start_datetime) = ? ORDER BY start_datetime ASC");
        $stmt->execute([$prestationId, $date]);
    } else {
        $stmt = $pdo->prepare("SELECT * FROM appointments WHERE DATE(start_datetime) = ? ORDER BY start_datetime ASC");
        $stmt->execute([$date]);
    }
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    if (!$rows) {
        echo '<p>Aucun rendez-vous trouvé pour ces critères.</p>';
    } else {
        echo '<table><tr><th>id</th><th>user_id</th><th>service_id</th><th>start_datetime</th><th>end_datetime</th><th>status</th><th>notes</th></tr>';
        foreach ($rows as $r) {
            echo '<tr>';
            echo '<td>'.htmlspecialchars($r['id'] ?? '') . '</td>';
            echo '<td>'.htmlspecialchars($r['user_id'] ?? '') . '</td>';
            echo '<td>'.htmlspecialchars($r['service_id'] ?? '') . '</td>';
            echo '<td>'.htmlspecialchars($r['start_datetime'] ?? '') . '</td>';
            echo '<td>'.htmlspecialchars($r['end_datetime'] ?? '') . '</td>';
            echo '<td>'.htmlspecialchars($r['status'] ?? '') . '</td>';
            echo '<td>'.htmlspecialchars($r['notes'] ?? '') . '</td>';
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
