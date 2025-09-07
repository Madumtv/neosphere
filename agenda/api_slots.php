<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);
header('Content-Type: application/json');
require_once __DIR__ . '/../inc/db.php';

$date = isset($_GET['date']) ? $_GET['date'] : '';
$prestationId = isset($_GET['prestation_id']) ? intval($_GET['prestation_id']) : 0;
if (!$date) {
  echo json_encode(['error' => 'date manquante']);
  exit;
}
if (!$prestationId) {
  echo json_encode(['error' => 'prestation_id requis']);
  exit;
}
// Créneaux SQL : retourne les créneaux pour la prestation et la date
$sql = "SELECT start_datetime, end_datetime, status FROM appointments WHERE service_id = ? AND DATE(start_datetime) = ?";
$stmt = $pdo->prepare($sql);
$stmt->execute([$prestationId, $date]);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Convertir en format attendu par le front : ajouter un champ 'booked' qui vaut true sauf si status === 'cancelled'
$slots = [];
foreach ($rows as $r) {
  $status = isset($r['status']) ? $r['status'] : null;
  $booked = !($status === 'cancelled');
  $slots[] = [
    'start_datetime' => $r['start_datetime'] ?? null,
    'end_datetime' => $r['end_datetime'] ?? null,
    'status' => $status,
    'booked' => $booked
  ];
}

echo json_encode(['slots' => $slots]);
exit;
