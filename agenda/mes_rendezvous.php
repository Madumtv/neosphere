<?php
@ini_set('display_errors','1');
@ini_set('display_startup_errors','1');
error_reporting(E_ALL);
session_start();
if(!isset($_SESSION['user_id'])) { header('Location: ../membre/login.php'); exit; }
require_once __DIR__ . '/../inc/db.php';
require_once __DIR__ . '/lib_services.php';
if(!$pdo) die('DB');
$user_id = $_SESSION['user_id'];
$cancelMsg='';
if(isset($_POST['cancel']) && isset($_POST['id'])){
  $id=intval($_POST['id']);
  try {
    $pdo->beginTransaction();
    $appt = $pdo->prepare('SELECT * FROM appointments WHERE id=? AND user_id=? FOR UPDATE');
    $appt->execute([$id,$user_id]);
    $appt=$appt->fetch();
    if($appt){
      if($appt['status']!=='cancelled'){
        $pdo->prepare('UPDATE appointments SET status="cancelled" WHERE id=?')->execute([$id]);
        if($appt['slot_id']){
          // décrémenter le slot et le rouvrir potentiellement
          $pdo->prepare('UPDATE service_slots SET booked_count=GREATEST(booked_count-1,0), status="open" WHERE id=?')->execute([$appt['slot_id']]);
        }
        $pdo->commit();
        $cancelMsg='Rendez-vous annulé.';
      } else { $cancelMsg='Déjà annulé.'; $pdo->rollBack(); }
    } else { $cancelMsg='Introuvable.'; $pdo->rollBack(); }
  } catch(Exception $e){ if($pdo->inTransaction()) $pdo->rollBack(); $cancelMsg='Erreur: '.$e->getMessage(); }
}
$svcTable = agenda_detect_services_table($pdo) ?: 'services';
$map = agenda_map_service_columns($pdo,$svcTable);
$idCol = $map['id'];
$nameCol = $map['name'];
$sqlAppt = "SELECT a.*, s.`{$nameCol}` AS service_name FROM appointments a JOIN `{$svcTable}` s ON a.service_id=s.`{$idCol}` WHERE a.user_id=? ORDER BY a.start_datetime DESC";
$apptsStmt = $pdo->prepare($sqlAppt);
$apptsStmt->execute([$user_id]);
$appts = $apptsStmt->fetchAll();
?><!DOCTYPE html><html lang="fr"><head><meta charset="utf-8"><title>Mes rendez-vous</title>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bulma@0.9.4/css/bulma.min.css">
<style>body{background:#fafafa;padding:20px;} .wrap{max-width:900px;margin:0 auto;} .status-cancelled{opacity:0.6;text-decoration:line-through;} </style>
</head><body>
<div class="wrap">
  <h1 class="title is-3">Mes rendez-vous</h1>
  <p><a href="../membre/espace.php" class="button is-small">← Espace membre</a></p>
  <?php if($cancelMsg): ?><div class="notification is-info"><?php echo htmlspecialchars($cancelMsg); ?></div><?php endif; ?>
  <table class="table is-fullwidth is-striped">
    <thead><tr><th>Date début</th><th>Date fin</th><th>Service</th><th>Statut</th><th></th></tr></thead>
    <tbody>
      <?php foreach($appts as $a): $cls = $a['status']==='cancelled'?'status-cancelled':''; ?>
        <tr class="<?php echo $cls; ?>">
          <td><?php echo htmlspecialchars($a['start_datetime']); ?></td>
          <td><?php echo htmlspecialchars($a['end_datetime']); ?></td>
          <td><?php echo htmlspecialchars($a['service_name']); ?></td>
          <td><?php echo htmlspecialchars($a['status']); ?></td>
          <td>
            <?php if($a['status']!=='cancelled'): ?>
              <form method="post" onsubmit="return confirm('Annuler ce rendez-vous ?');" style="display:inline;">
                <input type="hidden" name="id" value="<?php echo $a['id']; ?>">
                <button class="button is-danger is-light is-small" type="submit" name="cancel">Annuler</button>
              </form>
            <?php endif; ?>
          </td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>
</body></html>
