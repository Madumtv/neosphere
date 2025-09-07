<?php
// insert_test_appointment.php
// Usage (ex depuis le navigateur):
// http://your-host/agenda/insert_test_appointment.php?prestation_id=20&date=2025-09-08&time=09:00&user_id=1

@ini_set('display_errors',1);
@ini_set('display_startup_errors',1);
error_reporting(E_ALL);
require_once __DIR__ . '/../inc/db.php';
if (!$pdo) {
    echo "Erreur: connexion DB non initialisée (\$pdo vide)";
    exit;
}

$prestationId = isset($_GET['prestation_id']) ? intval($_GET['prestation_id']) : 0;
$date = isset($_GET['date']) ? $_GET['date'] : '';
$time = isset($_GET['time']) ? $_GET['time'] : '';
$userId = isset($_GET['user_id']) ? intval($_GET['user_id']) : 1;

if (!$prestationId || !$date || !$time) {
    echo "Paramètres manquants. Exemple: ?prestation_id=20&date=2025-09-08&time=09:00&user_id=1";
    exit;
}

// Construire start_datetime et end_datetime (par défaut durée 60 minutes)
$start = $date . ' ' . $time . ':00';
$durationMinutes = 60; // ajuster si besoin
$dt = new DateTime($start);
$endDt = clone $dt;
$endDt->modify("+{$durationMinutes} minutes");
$end = $endDt->format('Y-m-d H:i:s');

try {
    $stmt = $pdo->prepare("INSERT INTO appointments (user_id, service_id, slot_id, start_datetime, end_datetime, status, notes, created_at, updated_at) VALUES (?, ?, NULL, ?, ?, 'confirmed', ?, NOW(), NOW())");
    $notes = 'Insertion test via insert_test_appointment.php';
    $stmt->execute([$userId, $prestationId, $start, $end, $notes]);
    $id = $pdo->lastInsertId();
    echo "OK: rendez-vous inséré id={$id} start={$start} end={$end} service_id={$prestationId}\n";
    echo "SQL row: ";
    $q = $pdo->prepare("SELECT * FROM appointments WHERE id=?");
    $q->execute([$id]);
    $row = $q->fetch(PDO::FETCH_ASSOC);
    echo '<pre>' . print_r($row, true) . '</pre>';
} catch (Exception $e) {
    echo "Erreur insertion: " . $e->getMessage();
}
