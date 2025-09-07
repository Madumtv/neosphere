<?php
@ini_set('display_errors','1'); @ini_set('display_startup_errors','1'); error_reporting(E_ALL);
session_start();
require_once __DIR__ . '/../inc/db.php';
require_once __DIR__ . '/../agenda/lib_services.php';
if(!$pdo) die('DB');
// TODO: vérifier rôle admin si système de rôles
$svcTable = agenda_detect_services_table($pdo) ?: 'services';
$map = agenda_map_service_columns($pdo,$svcTable); $idCol=$map['id']; $nameCol=$map['name'];
$filterStatus = isset($_GET['status'])?trim($_GET['status']):'pending';
$validStatuses = ['en attente','confirmé','refusé','annulé'];
if($filterStatus && !in_array($filterStatus,$validStatuses)) $filterStatus='pending';
$msg=''; $err='';
if(isset($_POST['action']) && isset($_POST['id'])){
  $id = (int)$_POST['id']; $action = $_POST['action'];
  $targetStatus = null;
  if($action==='confirm') $targetStatus='confirmed';
  elseif($action==='decline') $targetStatus='declined';
  elseif($action==='cancel') $targetStatus='cancelled';
  if($targetStatus){
    try {
      $pdo->beginTransaction();
      $appt = $pdo->prepare('SELECT * FROM appointments WHERE id=? FOR UPDATE');
      $appt->execute([$id]); $appt=$appt->fetch();
      if(!$appt) throw new Exception('Introuvable');
      if($appt['status']!==$targetStatus){
        $pdo->prepare('UPDATE appointments SET status=? WHERE id=?')->execute([$targetStatus,$id]);
        if($targetStatus==='declined' || $targetStatus==='cancelled'){
          if($appt['slot_id']){
            $pdo->prepare('UPDATE service_slots SET booked_count=GREATEST(booked_count-1,0), status="open" WHERE id=?')->execute([$appt['slot_id']]);
          }
        }
        $msg='Statut mis à jour.'; 
      } else { $msg='Aucun changement.'; }
      $pdo->commit();
    } catch(Exception $e){ if($pdo->inTransaction()) $pdo->rollBack(); $err='Erreur: '.$e->getMessage(); }
  }
}
// Liste des rendez-vous
$where='1'; $params=[];
if($filterStatus){ $where.=' AND a.status=?'; $params[]=$filterStatus; }
$sql = "SELECT a.*, u.email, s.`{$nameCol}` AS service_name FROM appointments a JOIN users u ON a.user_id=u.id JOIN `{$svcTable}` s ON a.service_id=s.`{$idCol}` WHERE $where ORDER BY a.start_datetime ASC LIMIT 300";
$stmt=$pdo->prepare($sql); $stmt->execute($params); $rows=$stmt->fetchAll();
?><!DOCTYPE html><html lang='fr'><head><meta charset='utf-8'><title>Validation rendez-vous</title>
<link rel='stylesheet' href='https://cdn.jsdelivr.net/npm/bulma@0.9.4/css/bulma.min.css'>
<style>
  body{background:#fafafa;margin:0;padding:0 20px 20px;} /* retrait marge + padding top */
  .wrap{max-width:1200px;margin:0 auto;}
  table td,table th{font-size:0.85rem;}
  .status-pending{background:#fff8e5;}
  .status-confirmed{background:#e6ffed;}
  .status-declined{background:#ffe6e6;}
  .status-cancelled{background:#f0f0f0;text-decoration:line-through;}
  .tag-status{font-size:0.65rem;text-transform:uppercase;letter-spacing:.5px;}
 </style>
</head><body>
<?php include_once __DIR__ . '/../inc/menu.php'; ?>
<div class='wrap' style='padding-top:18px;'>
  <h1 class='title is-3'>Validation des rendez-vous</h1>
  <p><a href='index.php' class='button is-small'>← Admin</a> <a href='services.php' class='button is-small is-light'>Services</a></p>
  <form method='get' class='field is-grouped'>
    <div class='control'>
      <div class='select is-small'>
        <select name='status' onchange='this.form.submit()'>
          <option value=''>Tous statuts</option>
          <?php foreach($validStatuses as $st): ?>
            <option value='<?php echo $st; ?>' <?php echo $filterStatus===$st?'selected':''; ?>><?php echo $st; ?></option>
          <?php endforeach; ?>
        </select>
      </div>
    </div>
    <?php if($filterStatus): ?>
      <div class='control'><a href='?' class='button is-small'>Réinitialiser</a></div>
    <?php endif; ?>
  </form>
  <?php if($err): ?><div class='notification is-danger'><?php echo htmlspecialchars($err); ?></div><?php endif; ?>
  <?php if($msg): ?><div class='notification is-primary'><?php echo htmlspecialchars($msg); ?></div><?php endif; ?>
  <table class='table is-fullwidth is-striped is-hoverable'>
    <thead><tr><th>ID</th><th>Début</th><th>Fin</th><th>Service</th><th>Utilisateur</th><th>Statut</th><th>Actions</th></tr></thead>
    <tbody>
      <?php foreach($rows as $r): $cls='status-'.$r['status']; ?>
        <tr class='<?php echo $cls; ?>'>
          <td><?php echo (int)$r['id']; ?></td>
          <td><?php echo htmlspecialchars($r['start_datetime']); ?></td>
          <td><?php echo htmlspecialchars($r['end_datetime']); ?></td>
          <td><?php echo htmlspecialchars($r['service_name']); ?></td>
          <td><?php echo htmlspecialchars($r['email']); ?></td>
          <td><span class='tag is-info is-light tag-status'><?php echo htmlspecialchars($r['status']); ?></span></td>
          <td style='white-space:nowrap;'>
            <?php if($r['status']==='pending'): ?>
              <form method='post' style='display:inline;'>
                <input type='hidden' name='id' value='<?php echo (int)$r['id']; ?>'>
                <button class='button is-success is-small' name='action' value='confirm'>Confirmer</button>
              </form>
              <form method='post' style='display:inline;' onsubmit="return confirm('Refuser ce rendez-vous ?');">
                <input type='hidden' name='id' value='<?php echo (int)$r['id']; ?>'>
                <button class='button is-danger is-light is-small' name='action' value='decline'>Refuser</button>
              </form>
            <?php elseif($r['status']==='confirmed'): ?>
              <form method='post' style='display:inline;' onsubmit="return confirm('Annuler ce rendez-vous confirmé ?');">
                <input type='hidden' name='id' value='<?php echo (int)$r['id']; ?>'>
                <button class='button is-warning is-light is-small' name='action' value='cancel'>Annuler</button>
              </form>
            <?php endif; ?>
          </td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
  <?php if(!count($rows)): ?><p class='has-text-grey'>Aucun rendez-vous.</p><?php endif; ?>
</div>
</body></html>
