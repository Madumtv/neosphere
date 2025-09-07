<?php
@ini_set('display_errors','1');
@ini_set('display_startup_errors','1');
error_reporting(E_ALL);
session_start();
if(!isset($_SESSION['user_id'])) { header('Location: ../membre/login.php'); exit; }
require_once __DIR__ . '/../inc/db.php';
require_once __DIR__ . '/lib_services.php';
if(!$pdo) die('DB');
// Récupérer l'id du soin passé en GET
$prestationId = isset($_GET['prestation_id']) ? intval($_GET['prestation_id']) : 0;
$services = array_filter(agenda_fetch_services($pdo), fn($s)=>$s['active']);
$selectedService = null;
if ($prestationId) {
  foreach ($services as $s) {
    if ((int)$s['id'] === $prestationId) {
      $selectedService = $s;
      break;
    }
  }
}
// Filtrer sur la grille par défaut si disponible
try {
  $defaultGridId = $pdo->query("SELECT id FROM grids WHERE is_default=1 ORDER BY id ASC LIMIT 1")->fetchColumn();
  if ($defaultGridId) {
    $services = array_filter($services, fn($s)=> isset($s['grid_id']) ? ((int)$s['grid_id']==(int)$defaultGridId) : true);
  }
} catch(Throwable $e) { /* ignore si table absente */ }
// Inclure le menu commun
include_once __DIR__ . '/../inc/menu.php';

// --- Rendu initial serveur : calculer créneaux pour la date du jour ---
$initialDate = date('Y-m-d');
$initialSlots = [];
// Si une prestation est sélectionnée, utiliser sa durée, sinon défaut 60
$duration = $selectedService['duration_minutes'] ?? 60;
// créneaux de 09:00 à 17:00 par pas égal à duration
$startHour = 9; $endHour = 17;
$period = max(15, (int)$duration); // minutes
$dt = new DateTime($initialDate . ' ' . sprintf('%02d:00', $startHour));
$endDt = new DateTime($initialDate . ' ' . sprintf('%02d:00', $endHour));
while ($dt <= $endDt) {
  $s = $dt->format('H:i');
  // vérifier réservation existante (ne pas compter les status 'cancelled')
  $start_datetime = $initialDate . ' ' . $s . ':00';
  $st = $pdo->prepare("SELECT COUNT(*) FROM appointments WHERE service_id=? AND start_datetime = ? AND (status IS NULL OR status != 'cancelled')");
  $st->execute([(int)$prestationId, $start_datetime]);
  $booked = $st->fetchColumn() > 0;
  $initialSlots[] = ['time'=>$s, 'booked'=>$booked, 'start_datetime'=>$start_datetime];
  $dt->modify("+{$period} minutes");
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
  <!-- Flatpickr CSS -->
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Prendre rendez-vous</title>
    <style>
        .appointment-container {
            display: flex;
            gap: 32px;
            background: #f5f5f5;
            border-radius: 16px;
            padding: 32px;
            max-width: 900px;
            margin: 40px auto;
            box-shadow: 0 2px 8px #ccc;
        }
        .calendar-section {
            flex: 1;
        }
        .form-section {
            flex: 1;
        }
        .calendar table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 16px;
        }
        .calendar th, .calendar td {
            width: 32px;
            height: 32px;
            text-align: center;
            border-radius: 6px;
            cursor: pointer;
        }
        .times {
            display: flex;
            flex-wrap: wrap;
            gap: 12px;
        }
        .times button {
            background: #fff;
            border: 1px solid #ccc;
            border-radius: 8px;
            padding: 12px 24px;
            min-width: 100px;
            font-size: 16px;
            cursor: pointer;
        }
        .times .booked {
            background: #eee;
            color: #888;
            cursor: not-allowed;
        }
        .form-section form {
            display: flex;
            flex-direction: column;
            gap: 12px;
        }
        .form-section input, .form-section textarea {
            padding: 8px;
            border: 1px solid #ccc;
            border-radius: 6px;
        }
        .form-section button {
            background: #333;
            color: #fff;
            border: none;
            border-radius: 8px;
            padding: 12px;
            font-size: 16px;
            cursor: pointer;
        }
    </style>
</head>
<body>
<div class="appointment-container">
  <div class="calendar-section">
    <h2>Prendre rendez-vous</h2>
    <div class="calendar">
  <label for="date">Choisissez une date :</label>
  <input type="text" id="date" name="date" placeholder="Sélectionnez une date">
    </div>
    <div class="times">
      <h3>Créneaux disponibles</h3>
      <div id="slots">
        <?php if (empty($initialSlots)): ?>
          <em>Aucun créneau disponible pour cette date.</em>
        <?php else: ?>
          <?php foreach ($initialSlots as $slot):
            $display = htmlspecialchars($slot['time']);
            $start_dt = htmlspecialchars($slot['start_datetime']);
            $isBooked = !empty($slot['booked']);
            $btnClass = $isBooked ? 'booked' : '';
            $disabled = $isBooked ? 'disabled' : '';
          ?>
            <button type="button" class="<?= $btnClass ?>" <?= $disabled ?> onclick="document.getElementById('selected_slot').value='<?= $start_dt ?>'; Array.from(document.getElementById('slots').children).forEach(function(b){b.classList.remove('selected');}); this.classList.add('selected');"><?= $display ?><?= $isBooked ? ' (Réservé)' : '' ?></button>
          <?php endforeach; ?>
        <?php endif; ?>
      </div>
    </div>
  </div>
  <div class="form-section">
    <form method="post" action="">
      <label>Nom</label>
      <input type="text" name="name" required>
      <label>Date de naissance</label>
      <input type="date" name="dob" required>
      <label>Téléphone</label>
      <input type="text" name="contact" required>
      <label>Email</label>
      <input type="email" name="email" required>
      <label>Symptômes</label>
      <textarea name="symptoms"></textarea>
      <input type="hidden" name="selected_slot" id="selected_slot">
      <button type="submit">Réserver</button>
    </form>
  </div>
</div>
<script>
// Exemple de créneaux horaires par jour (à remplacer par un appel AJAX/PHP)
const slotsData = {
  '2025-09-08': [
    { time: '09:00', booked: false },
    { time: '11:00', booked: true },
    { time: '14:00', booked: false },
    { time: '15:00', booked: false }
  ],
  '2025-09-09': [
    { time: '10:00', booked: false },
    { time: '13:00', booked: false },
    { time: '16:00', booked: true }
  ]
};

const dateInput = document.getElementById('date');
const slotsDiv = document.getElementById('slots');
const selectedSlotInput = document.getElementById('selected_slot');

dateInput.addEventListener('change', function() {
  let selectedDate = this.value;
  // Récupérer l'id de la prestation depuis PHP
  const prestationId = <?= intval($prestationId) ?>;
  if (!selectedDate) {
    slotsDiv.innerHTML = '<em>Veuillez sélectionner une date.</em>';
    return;
  }
  // Vérification du format (YYYY-MM-DD)
  const dateRegex = /^\d{4}-\d{2}-\d{2}$/;
  if (!dateRegex.test(selectedDate)) {
    slotsDiv.innerHTML = '<em>Date invalide.</em>';
    return;
  }
  slotsDiv.innerHTML = '';
  selectedSlotInput.value = '';
  fetch('api_slots.php?date=' + encodeURIComponent(selectedDate) + '&prestation_id=' + prestationId)
    .then(response => response.text())
    .then(raw => {
      // Pour debug, afficher la réponse brute
      try {
        const data = JSON.parse(raw.replace(/<[^>]+>/g, ''));
        if (data.error) {
          slotsDiv.innerHTML = '<em>' + data.error + '</em>';
          return;
        }
        if (!data.slots.length) {
          slotsDiv.innerHTML = '<em>Aucun créneau disponible pour cette date.</em>';
          return;
        }
        data.slots.forEach(slot => {
          // Déterminer le texte à afficher (heure) : utiliser slot.time si présent, sinon extraire de start_datetime
          let displayTime = slot.time;
          if (!displayTime && slot.start_datetime) {
            // start_datetime attendu au format 'YYYY-MM-DD HH:MM:SS'
            displayTime = slot.start_datetime.substring(11,16);
          }
          // Déterminer si le créneau est réservé
          let isBooked = false;
          if (typeof slot.booked !== 'undefined') isBooked = !!slot.booked;
          else if (typeof slot.status !== 'undefined') isBooked = (slot.status !== '' && slot.status !== null);

          const btn = document.createElement('button');
          btn.type = 'button';
          btn.textContent = (displayTime || '—') + (isBooked ? ' (Réservé)' : '');
          btn.disabled = !!isBooked;
          if (isBooked) btn.classList.add('booked');
          btn.onclick = function() {
            // stocker la valeur utile : si start_datetime disponible, on la stocke, sinon l'heure
            selectedSlotInput.value = slot.start_datetime ? slot.start_datetime : (slot.time || displayTime);
            Array.from(slotsDiv.children).forEach(b => b.classList.remove('selected'));
            btn.classList.add('selected');
          };
          slotsDiv.appendChild(btn);
        });
      } catch(e) {
        slotsDiv.innerHTML = '<em>Réponse API non valide :<br>' + raw + '</em>';
      }
    })
    .catch(() => {
      slotsDiv.innerHTML = '<em>Erreur lors du chargement des créneaux.</em>';
    });
});
</script>
</body>
<!-- Flatpickr JS -->
<script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
<!-- Flatpickr locale FR -->
<script src="https://cdn.jsdelivr.net/npm/flatpickr/dist/l10n/fr.js"></script>
<script>
var fp = flatpickr("#date", {
  minDate: "today",
  dateFormat: "Y-m-d",
  inline: true,
  // Utiliser explicitement la localisation française fournie par flatpickr
  locale: Object.assign({}, flatpickr.l10ns.fr || {}, { firstDayOfWeek: 1 }),
  onChange: function(selectedDates, dateStr, instance) {
    // Déclenche le chargement des créneaux à chaque sélection
    const event = new Event('change');
    dateInput.value = dateStr;
    dateInput.dispatchEvent(event);
  }
});
// Charger automatiquement l'agenda pour la date du jour
fp.setDate(new Date(), true); // true -> trigger change
</script>
</html>
