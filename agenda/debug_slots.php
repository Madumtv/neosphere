<?php
// debug_slots.php
// Affiche les créneaux calculés côté serveur et l'état réservé/libre en base
@ini_set('display_errors',1);
@ini_set('display_startup_errors',1);
error_reporting(E_ALL);
require_once __DIR__ . '/../inc/db.php';
require_once __DIR__ . '/lib_services.php';

$date = isset($_GET['date']) ? $_GET['date'] : date('Y-m-d');
$prestationId = isset($_GET['prestation_id']) ? intval($_GET['prestation_id']) : 0;

?><!doctype html>
<html lang="fr">
<head>
<meta charset="utf-8">
<title>Debug slots</title>
<style>body{font-family:Arial,Helvetica,sans-serif;margin:16px}table{border-collapse:collapse;width:100%;margin-bottom:16px}th,td{border:1px solid #ddd;padding:8px;text-align:left}th{background:#f4f4f4} .booked{background:#fee} .free{background:#efe}</style>
</head>
<body>
<h1>Debug créneaux</h1>
<p>Date: <strong><?php echo htmlspecialchars($date) ?></strong> &nbsp; Prestation_id: <strong><?php echo $prestationId ?: '(aucune)' ?></strong></p>
<p><a href="list_services_debug.php">Voir services</a> | <a href="list_appointments_debug.php?date=<?php echo $date?>">Voir appointments</a></p>

<?php if (!$pdo) { echo '<p style="color:red">Erreur: connexion DB non initialisée</p>'; exit; } ?>

<?php
// Sélectionner un service si non fourni
$services = agenda_fetch_services($pdo);
if (!$prestationId) {
    echo '<h2>Choisir une prestation</h2>';
    if (empty($services)) { echo '<p>Aucun service détecté.</p>'; }
    else {
        echo '<ul>';
        foreach ($services as $s) {
            echo '<li><a href="?date='.urlencode($date).'&prestation_id='.(int)$s['id'].'">'.htmlspecialchars($s['id'].' — '.$s['name'].' ('.$s['duration_minutes'].'min)').'</a></li>';
        }
        echo '</ul>';
    }
    exit;
}

// Trouver la prestation
$selected = null;
foreach ($services as $s) if ((int)$s['id'] === (int)$prestationId) { $selected = $s; break; }
if (!$selected) { echo '<p style="color:red">Prestation non trouvée (id='.$prestationId.').</p>'; exit; }

$duration = $selected['duration_minutes'] ?? 60;
$startHour = 9; $endHour = 17;
$period = max(15, (int)$duration);
$dt = new DateTime($date . ' ' . sprintf('%02d:00', $startHour));
$endDt = new DateTime($date . ' ' . sprintf('%02d:00', $endHour));

$slots = [];
while ($dt <= $endDt) {
    $time = $dt->format('H:i');
    $start_dt = $date . ' ' . $time . ':00';
    // Chercher si un RDV non-cancelled existe à ce start_datetime
    $q = $pdo->prepare("SELECT id, user_id, service_id, status FROM appointments WHERE service_id = ? AND start_datetime = ?");
    $q->execute([(int)$prestationId, $start_dt]);
    $rows = $q->fetchAll(PDO::FETCH_ASSOC);
    $booked = false; $rowInfo = [];
    foreach ($rows as $r) {
        $rowInfo[] = $r;
        if (!isset($r['status']) || $r['status'] === '' || $r['status'] === null) {
            // considérer vide comme bloquant (selon choix) — marquer ici
            $booked = true;
        } elseif ($r['status'] !== 'cancelled') {
            $booked = true;
        }
    }
    $slots[] = [ 'time'=>$time, 'start_datetime'=>$start_dt, 'booked'=>$booked, 'rows'=>$rowInfo ];
    $dt->modify("+{$period} minutes");
}

// Afficher tableau
echo '<h2>Prestation: '.htmlspecialchars($selected['name']).' ('.$duration.' min)</h2>';
echo '<table><tr><th>Heure</th><th>Status</th><th>Détails DB</th><th>Action</th></tr>';
foreach ($slots as $s) {
    $cls = $s['booked'] ? 'booked' : 'free';
    echo '<tr class="'.$cls.'">';
    echo '<td>'.htmlspecialchars($s['time']).'</td>';
    echo '<td>'.($s['booked'] ? '<strong>Occupé</strong>' : '<strong>Libre</strong>').'</td>';
    echo '<td>';
    if (empty($s['rows'])) echo '—';
    else {
        foreach ($s['rows'] as $r) {
            echo 'id:'.htmlspecialchars($r['id']).' user:'.htmlspecialchars($r['user_id']).' status:'.htmlspecialchars($r['status']).'<br>';
        }
    }
    echo '</td>';
    echo '<td>';
    $insUrl = 'insert_test_appointment.php?prestation_id='.(int)$prestationId.'&date='.urlencode($date).'&time='.urlencode($s['time']).'&user_id=1';
    echo '<a href="'.$insUrl.'">Insérer test</a> | ';
    $insCancel = $insUrl.'&status=cancelled';
    echo '<a href="'.$insCancel.'">Insérer cancelled</a>';
    echo '</td>';
    echo '</tr>';
}
echo '</table>';

// Afficher SQL brut utilisé
echo '<h3>SQL utilisé pour vérifier</h3>';
echo '<pre>'.htmlspecialchars("SELECT id, user_id, service_id, status FROM appointments WHERE service_id = {$prestationId} AND start_datetime = 'YYYY-MM-DD HH:MM:SS'").'</pre>';

// Appeler API pour comparaison
$apiUrl = dirname($_SERVER['SCRIPT_NAME']).'/api_slots.php?date='.urlencode($date).'&prestation_id='.(int)$prestationId;
$fullApi = (isset($_SERVER['REQUEST_SCHEME']) ? $_SERVER['REQUEST_SCHEME'] : (empty($_SERVER['HTTPS']) ? 'http' : 'https')).'://'.$_SERVER['HTTP_HOST'].$apiUrl;

echo '<h3>Appel API</h3>';
echo '<p>URL: <a href="'.htmlspecialchars($fullApi).'">'.htmlspecialchars($fullApi).'</a></p>';
$apiResp = @file_get_contents($fullApi);
if ($apiResp === false) {
    echo '<p style="color:red">Impossible d’appeler l’API localement (file_get_contents a échoué). Vérifiez chemins/permissions.</p>';
} else {
    echo '<pre>'.htmlspecialchars($apiResp).'</pre>';
}

?>
</body>
</html>
