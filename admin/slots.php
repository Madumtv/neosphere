<?php
@ini_set('display_errors','1'); @ini_set('display_startup_errors','1'); error_reporting(E_ALL);
session_start();
require_once __DIR__.'/../inc/db.php';
require_once __DIR__.'/../agenda/lib_services.php';
if(!$pdo) die('DB');
// Auth simple (adapter selon votre système)
$isAdmin = (!empty($_SESSION['is_admin']) || (isset($_SESSION['role']) && $_SESSION['role']==='admin') || (isset($_SESSION['role_id']) && $_SESSION['role_id']==1));
if(!$isAdmin){ header('Location: ../membre/login.php'); exit; }

// CSRF simple
if(empty($_SESSION['csrf_token'])){ $_SESSION['csrf_token']=bin2hex(random_bytes(16)); }
$csrfToken = $_SESSION['csrf_token'];

$svcTable = agenda_detect_services_table($pdo) ?: 'services';
$map = agenda_map_service_columns($pdo,$svcTable);
$servicesAll = agenda_fetch_services($pdo);
// Déterminer grille par défaut si grid_id présent
$defaultGridId = null; $hasGrid=false;
foreach($servicesAll as $s){ if(isset($s['grid_id']) || isset($s['_raw']['grid_id'])){ $hasGrid=true; break; } }
if($hasGrid){
  try { $defaultGridId = $pdo->query("SELECT id FROM grids WHERE is_default=1 ORDER BY id ASC LIMIT 1")->fetchColumn(); } catch(Throwable $e){}
}
if($defaultGridId){
  // Limiter services à la grille par défaut si aucun paramètre explicite ?all=1
  if(!isset($_GET['all'])){
    $servicesAll = array_filter($servicesAll, function($s) use ($defaultGridId){
      $gid = $s['grid_id'] ?? ($s['_raw']['grid_id'] ?? null);
      return (int)$gid === (int)$defaultGridId;
    });
  }

// Filtre service / période
$serviceFilter = isset($_GET['service'])?intval($_GET['service']):0;
$from = isset($_GET['from']) && preg_match('/^\d{4}-\d{2}-\d{2}$/',$_GET['from'])?$_GET['from']:date('Y-m-d');
$to = isset($_GET['to']) && preg_match('/^\d{4}-\d{2}-\d{2}$/',$_GET['to'])?$_GET['to']:date('Y-m-d',strtotime('+21 days'));
$slots=[]; $stats=['total'=>0,'booked'=>0,'remaining'=>0]; $aggregateByService=[]; $flash=[];
$isPartial = isset($_GET['partial']);

// CRUD actions (seulement si un service est filtré)
if($serviceFilter && $_SERVER['REQUEST_METHOD']==='POST' && $isAdmin){
  if(!isset($_POST['csrf']) || $_POST['csrf']!==$csrfToken){ $flash[]=['type'=>'danger','msg'=>'Token CSRF invalide']; }
  else {
    try {
      $pdo->exec("CREATE TABLE IF NOT EXISTS service_slots (id INT AUTO_INCREMENT PRIMARY KEY, service_id INT NOT NULL, slot_date DATE NOT NULL, start_time TIME NOT NULL, end_time TIME NOT NULL, capacity INT NOT NULL DEFAULT 1, booked_count INT NOT NULL DEFAULT 0, status ENUM('open','closed') NOT NULL DEFAULT 'open', UNIQUE KEY uniq_slot(service_id,slot_date,start_time,end_time)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    } catch(Throwable $e) { $flash[]=['type'=>'danger','msg'=>'Création table impossible']; }
    $act = $_POST['act'] ?? '';
    if($act==='create'){
  $d = $_POST['slot_date'] ?? '';
  $st = $_POST['start_time'] ?? '';
  $et = $_POST['end_time'] ?? '';
      $cap = max(1, intval($_POST['capacity'] ?? 1));
      $recur = !empty($_POST['recur']);
      $recurType = in_array($_POST['recur_type'] ?? 'daily', ['daily','weekly']) ? ($_POST['recur_type'] ?? 'daily') : 'daily';
      $endDate = $_POST['end_date'] ?? '';
      $recurDays = isset($_POST['recur_days']) && is_array($_POST['recur_days']) ? array_map('intval', $_POST['recur_days']) : [];
      if(!preg_match('/^\d{4}-\d{2}-\d{2}$/',$d) || !preg_match('/^\d{2}:\d{2}$/',$st) || !preg_match('/^\d{2}:\d{2}$/',$et)){
        $flash[]=['type'=>'danger','msg'=>'Données horaire invalides'];
      } else if(!empty($_POST['auto_generate'])) {
        // Génération automatique des créneaux selon la durée du soin
        $autoStart = $_POST['auto_start'] ?? '09:00';
        $autoEnd = $_POST['auto_end'] ?? '18:00';
        $autoDuration = max(5, min(180, intval($_POST['auto_duration'] ?? 30)));
        if(!preg_match('/^\d{2}:\d{2}$/',$autoStart) || !preg_match('/^\d{2}:\d{2}$/',$autoEnd) || $autoDuration < 5){
          $flash[]=['type'=>'danger','msg'=>'Paramètres auto invalides'];
        } else {
          $startTS = strtotime($d.' '.$autoStart);
          $endTS = strtotime($d.' '.$autoEnd);
          if($endTS <= $startTS){
            $flash[]=['type'=>'danger','msg'=>'Fin de journée doit être après début'];
          } else {
            $ins=$pdo->prepare('INSERT INTO service_slots(service_id,slot_date,start_time,end_time,capacity,status) VALUES(?,?,?,?,?,"open")');
            $added=0; $dups=0; $errs=0;
            for($curTS=$startTS; $curTS + ($autoDuration*60) <= $endTS; $curTS += ($autoDuration*60)){
              $slotStart = date('H:i:s', $curTS);
              $slotEnd = date('H:i:s', $curTS + ($autoDuration*60));
              try {
                $ins->execute([$serviceFilter,$d,$slotStart,$slotEnd,$cap]);
                $added++;
              } catch(PDOException $e){
                if($e->getCode()==='23000') $dups++; else $errs++;
              }
            }
            $flash[]=['type'=>'success','msg'=>'Créneaux générés: '.$added.' / doublons '.$dups.($errs?(' / erreurs '.$errs):'')];
          }
        }
      }
    } else {
  // Initialisation des variables pour éviter les warnings
  $d = $_POST['slot_date'] ?? '';
  $st = $_POST['start_time'] ?? '';
  $et = $_POST['end_time'] ?? '';
  $recur = !empty($_POST['recur']);
  $cap = max(1, intval($_POST['capacity'] ?? 1));
        if(strtotime($d.' '.$et) <= strtotime($d.' '.$st)){
          $flash[]=['type'=>'danger','msg'=>'Fin doit être après début'];
        } else {
          // Gestion récurrence
          if($recur && preg_match('/^\d{4}-\d{2}-\d{2}$/',$endDate) && strtotime($endDate) >= strtotime($d)){
            $maxSpanDays = 370; // sûreté
            $dates=[]; $startTS=strtotime($d); $endTS=strtotime($endDate); $spanDays = (int)floor(($endTS - $startTS)/86400)+1;
            if($spanDays > $maxSpanDays){
              $flash[]=['type'=>'warning','msg'=>'Période trop longue (max '.$maxSpanDays.' jours)'];
            } else {
              for($ts=$startTS;$ts<=$endTS;$ts+=86400){
                $curDate=date('Y-m-d',$ts);
                if($recurType==='daily') $dates[]=$curDate; else { // weekly
                  $dowN = (int)date('N',$ts); // 1 (lun) - 7 (dim)
                  if(in_array($dowN,$recurDays,true)) $dates[]=$curDate;
                }
                if(count($dates) > 500){ break; } // hard cap
              }
              if(!$dates){
                $flash[]=['type'=>'warning','msg'=>'Aucune date correspondante (jours non cochés ?)'];
              } else {
                $ins=$pdo->prepare('INSERT INTO service_slots(service_id,slot_date,start_time,end_time,capacity,status) VALUES(?,?,?,?,?,"open")');
                $added=0; $dups=0; $errs=0;
                foreach($dates as $dt){
                  try {
                    $ins->execute([$serviceFilter,$dt,$st.':00',$et.':00',$cap]);
                    $added++;
                  } catch(PDOException $e){
                    if($e->getCode()==='23000') $dups++; else $errs++;
                  }
                }
                $flash[]=['type'=>'success','msg'=>'Récurrence: ajoutés '.$added.' / doublons '.$dups.($errs?(' / erreurs '.$errs):'')];
              }
            }
          } else {
            // Simple (pas de récurrence)
            try {
              $ins=$pdo->prepare('INSERT INTO service_slots(service_id,slot_date,start_time,end_time,capacity,status) VALUES(?,?,?,?,?,"open")');
              $ins->execute([$serviceFilter,$d,$st.':00',$et.':00',$cap]);
              $flash[]=['type'=>'success','msg'=>'Créneau ajouté'];
            } catch(PDOException $e){
              if($e->getCode()==='23000') $flash[]=['type'=>'warning','msg'=>'Créneau déjà existant']; else $flash[]=['type'=>'danger','msg'=>'Erreur ajout'];
            }
          }
        }
      }
    }
    // ...existing code...
    if($act==='update'){
      $id = intval($_POST['id']??0);
      $cap = max(1, intval($_POST['capacity']??1));
      $status = in_array($_POST['status']??'open',['open','closed'])?($_POST['status']) : 'open';
      $newDate = $_POST['slot_date'] ?? '';
      $newStart = $_POST['start_time'] ?? '';
      $newEnd = $_POST['end_time'] ?? '';
  $applyAll = !empty($_POST['apply_all']);
  $applyPattern = !empty($_POST['apply_pattern']);
  $applyPatternUntil = isset($_POST['apply_pattern_until']) && preg_match('/^\d{4}-\d{2}-\d{2}$/',$_POST['apply_pattern_until']) ? $_POST['apply_pattern_until'] : $to;
      // Récup slot pour validation
      $row = $pdo->prepare('SELECT slot_date,start_time,end_time,booked_count FROM service_slots WHERE id=? AND service_id=? LIMIT 1');
      $row->execute([$id,$serviceFilter]); $cur=$row->fetch(PDO::FETCH_ASSOC);
      if(!$cur){ $flash[]=['type'=>'danger','msg'=>'Créneau introuvable']; }
      else if(!$applyAll && $cap < (int)$cur['booked_count']){ $flash[]=['type'=>'danger','msg'=>'Capacité < réservations']; }
      else {
        if($applyAll){
          // Mise à jour en lot (sur la période filtrée actuelle) — start/end si fournis et valides et pas de réservations >0 sur slots concernés
          $changeTime=false; $newStartFull=null; $newEndFull=null; $timeError=false;
          if($newDate && $newStart && $newEnd){ /* on ne déplace pas la date pour all (trop risqué) */ }
          if($newStart && $newEnd){
            if(preg_match('/^\d{2}:\d{2}$/',$newStart) && preg_match('/^\d{2}:\d{2}$/',$newEnd) && strtotime('2000-01-01 '.$newEnd) > strtotime('2000-01-01 '.$newStart)){
              $newStartFull=$newStart.':00'; $newEndFull=$newEnd.':00';
              // Vérifier qu'aucun slot ayant des réservations ne serait déplacé
              $chkB=$pdo->prepare('SELECT COUNT(*) FROM service_slots WHERE service_id=? AND slot_date BETWEEN ? AND ? AND booked_count>0');
              $chkB->execute([$serviceFilter,$from,$to]);
              $hasBookings = (int)$chkB->fetchColumn();
              if($hasBookings>0){
                // On interdit modification horaire globale s'il y a des bookings
                $flash[]=['type'=>'warning','msg'=>'Heure non modifiée globalement (réservations existantes)'];
              } else {
                $changeTime=true;
              }
            } else { $timeError=true; $flash[]=['type'=>'danger','msg'=>'Heures invalides pour modification globale']; }
          }
          try {
            $chk=$pdo->prepare('SELECT MAX(booked_count) max_b FROM service_slots WHERE service_id=? AND slot_date BETWEEN ? AND ?');
            $chk->execute([$serviceFilter,$from,$to]);
            $maxBooked = (int)($chk->fetchColumn() ?: 0);
            if($cap < $maxBooked){
              $flash[]=['type'=>'danger','msg'=>'Capacité trop basse pour au moins un créneau (réservations existantes)'];
            } else if(!$timeError){
              if($changeTime){
                $up=$pdo->prepare('UPDATE service_slots SET start_time=?, end_time=?, capacity=?, status=? WHERE service_id=? AND slot_date BETWEEN ? AND ?');
                $up->execute([$newStartFull,$newEndFull,$cap,$status,$serviceFilter,$from,$to]);
              } else {
                $up=$pdo->prepare('UPDATE service_slots SET capacity=?, status=? WHERE service_id=? AND slot_date BETWEEN ? AND ?');
                $up->execute([$cap,$status,$serviceFilter,$from,$to]);
              }
              $count = $up->rowCount();
              $flash[]=['type'=>'success','msg'=>'Mise à jour en lot: '.$count.' créneau(x) modifié(s)'];
            }
          } catch(PDOException $e){
            $flash[]=['type'=>'danger','msg'=>'Erreur mise à jour lot'];
          }
        } elseif($applyPattern){
          // Mise à jour récurrente: même start/end, service, à partir de la date du créneau jusqu'à applyPatternUntil
          try {
            if(strtotime($applyPatternUntil) < strtotime($cur['slot_date'])) $applyPatternUntil = $cur['slot_date'];
            // Sécurité: limite absolue 370 jours à partir du slot courant
            $hardLimit = date('Y-m-d', strtotime($cur['slot_date'].' +370 days'));
            if(strtotime($applyPatternUntil) > strtotime($hardLimit)) $applyPatternUntil = $hardLimit;
            // Détermination des nouveaux horaires pour pattern
            $patternStart=$cur['start_time']; $patternEnd=$cur['end_time'];
            $changeTime=false;
            if($newStart && $newEnd && preg_match('/^\d{2}:\d{2}$/',$newStart) && preg_match('/^\d{2}:\d{2}$/',$newEnd) && strtotime('2000-01-01 '.$newEnd) > strtotime('2000-01-01 '.$newStart)){
              $patternStart=$newStart.':00'; $patternEnd=$newEnd.':00';
              // Sécurité: ne pas changer l'heure des slots déjà bookés
              $bk=$pdo->prepare('SELECT COUNT(*) FROM service_slots WHERE service_id=? AND start_time=? AND end_time=? AND slot_date BETWEEN ? AND ? AND booked_count>0');
              $bk->execute([$serviceFilter,$cur['start_time'],$cur['end_time'],$cur['slot_date'],$applyPatternUntil]);
              if((int)$bk->fetchColumn()>0){
                $flash[]=['type'=>'warning','msg'=>'Heures non modifiées sur occurrences réservées'];
                // On n'applique pas de changement d'horaire dans ce cas
                $patternStart=$cur['start_time']; $patternEnd=$cur['end_time'];
              } else {
                $changeTime=true;
              }
            }
            $chk=$pdo->prepare('SELECT MAX(booked_count) FROM service_slots WHERE service_id=? AND start_time=? AND end_time=? AND slot_date BETWEEN ? AND ?');
            $chk->execute([$serviceFilter,$cur['start_time'],$cur['end_time'],$cur['slot_date'],$applyPatternUntil]);
            $maxBooked=(int)($chk->fetchColumn()?:0);
            if($cap < $maxBooked){
              $flash[]=['type'=>'danger','msg'=>'Capacité trop basse pour au moins un créneau du motif (réservations existantes)'];
            } else {
              if($changeTime){
                // On met à jour d'abord la série existante (cap/status) puis on modifie les heures si toutes libres
                $up=$pdo->prepare('UPDATE service_slots SET start_time=?, end_time=?, capacity=?, status=? WHERE service_id=? AND start_time=? AND end_time=? AND slot_date BETWEEN ? AND ?');
                $up->execute([$patternStart,$patternEnd,$cap,$status,$serviceFilter,$cur['start_time'],$cur['end_time'],$cur['slot_date'],$applyPatternUntil]);
                $cnt=$up->rowCount();
              } else {
                $up=$pdo->prepare('UPDATE service_slots SET capacity=?, status=? WHERE service_id=? AND start_time=? AND end_time=? AND slot_date BETWEEN ? AND ?');
                $up->execute([$cap,$status,$serviceFilter,$cur['start_time'],$cur['end_time'],$cur['slot_date'],$applyPatternUntil]);
                $cnt=$up->rowCount();
              }
              $cnt=$up->rowCount();
              $flash[]=['type'=>'success','msg'=>'Récurrence future mise à jour: '.$cnt.' créneau(x)'];
            }
          } catch(PDOException $e){
            $flash[]=['type'=>'danger','msg'=>'Erreur mise à jour récurrente'];
          }
        } else {
        $needDateChange = false; $dateError=false;
        if($newDate && $newStart && $newEnd){
          if(!preg_match('/^\d{4}-\d{2}-\d{2}$/',$newDate) || !preg_match('/^\d{2}:\d{2}$/',$newStart) || !preg_match('/^\d{2}:\d{2}$/',$newEnd)){
            $dateError=true; $flash[]=['type'=>'danger','msg'=>'Format date/heure invalide'];
          } elseif(strtotime($newDate.' '.$newEnd) <= strtotime($newDate.' '.$newStart)){
            $dateError=true; $flash[]=['type'=>'danger','msg'=>'Fin doit être après début'];
          } else {
            $newStartFull=$newStart.':00'; $newEndFull=$newEnd.':00';
            if($newDate!==$cur['slot_date'] || $newStartFull!==$cur['start_time'] || $newEndFull!==$cur['end_time']){
              // Interdire modification horaire si des réservations existent
              if((int)$cur['booked_count']>0){
                $dateError=true; $flash[]=['type'=>'warning','msg'=>'Impossible de modifier date/heure: réservations existantes'];
              } else {
                $needDateChange=true;
              }
            }
          }
        }
        if(!$dateError){
          try {
            if($needDateChange){
              $up=$pdo->prepare('UPDATE service_slots SET slot_date=?, start_time=?, end_time=?, capacity=?, status=? WHERE id=? AND service_id=?');
              $up->execute([$newDate,$newStart.':00',$newEnd.':00',$cap,$status,$id,$serviceFilter]);
            } else {
              $up=$pdo->prepare('UPDATE service_slots SET capacity=?, status=? WHERE id=? AND service_id=?');
              $up->execute([$cap,$status,$id,$serviceFilter]);
            }
    $flash[]=['type'=>'success','msg'=>'Créneau mis à jour'];
          } catch(PDOException $e){
            if($e->getCode()==='23000') $flash[]=['type'=>'danger','msg'=>'Conflit: un créneau identique existe déjà']; else $flash[]=['type'=>'danger','msg'=>'Erreur maj'];
          }
        }
      }
  }
    } elseif($act==='delete'){
      $id = intval($_POST['id']??0);
      $row = $pdo->prepare('SELECT booked_count FROM service_slots WHERE id=? AND service_id=?');
      $row->execute([$id,$serviceFilter]); $cur=$row->fetch(PDO::FETCH_ASSOC);
      if(!$cur){ $flash[]=['type'=>'danger','msg'=>'Créneau introuvable']; }
      elseif((int)$cur['booked_count']>0){ $flash[]=['type'=>'warning','msg'=>'Impossible de supprimer: réservations existantes']; }
      else {
        $del=$pdo->prepare('DELETE FROM service_slots WHERE id=? AND service_id=? LIMIT 1');
        $del->execute([$id,$serviceFilter]);
        $flash[]=['type'=>'success','msg'=>'Créneau supprimé'];
      }
    } elseif($act==='toggle'){
      $id = intval($_POST['id']??0);
      $row = $pdo->prepare('SELECT status FROM service_slots WHERE id=? AND service_id=?');
      $row->execute([$id,$serviceFilter]); $cur=$row->fetch(PDO::FETCH_ASSOC);
      if(!$cur){ $flash[]=['type'=>'danger','msg'=>'Créneau introuvable']; }
      else {
        $ns = $cur['status']==='open'?'closed':'open';
        $up=$pdo->prepare('UPDATE service_slots SET status=? WHERE id=? AND service_id=?');
        $up->execute([$ns,$id,$serviceFilter]);
        $flash[]=['type'=>'success','msg'=>'Statut changé'];
      }
    }
  }
}
if($serviceFilter){
  try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS service_slots (id INT AUTO_INCREMENT PRIMARY KEY, service_id INT NOT NULL, slot_date DATE NOT NULL, start_time TIME NOT NULL, end_time TIME NOT NULL, capacity INT NOT NULL DEFAULT 1, booked_count INT NOT NULL DEFAULT 0, status ENUM('open','closed') NOT NULL DEFAULT 'open', UNIQUE KEY uniq_slot(service_id,slot_date,start_time,end_time)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    $st=$pdo->prepare('SELECT * FROM service_slots WHERE service_id=? AND slot_date BETWEEN ? AND ? ORDER BY slot_date,start_time LIMIT 1500');
    $st->execute([$serviceFilter,$from,$to]);
    while($r=$st->fetch(PDO::FETCH_ASSOC)){
      $slots[]=$r; $stats['total']++; $stats['booked']+=(int)$r['booked_count']; $stats['remaining']+=max(0,(int)$r['capacity']-(int)$r['booked_count']);
    }
  } catch(Throwable $e){}
} elseif($defaultGridId){
  // Vue agrégée sur la grille par défaut
  try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS service_slots (id INT AUTO_INCREMENT PRIMARY KEY, service_id INT NOT NULL, slot_date DATE NOT NULL, start_time TIME NOT NULL, end_time TIME NOT NULL, capacity INT NOT NULL DEFAULT 1, booked_count INT NOT NULL DEFAULT 0, status ENUM('open','closed') NOT NULL DEFAULT 'open', UNIQUE KEY uniq_slot(service_id,slot_date,start_time,end_time)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    // Récupérer agrégat par service
    $ids = array_map(fn($s)=> (int)$s['id'], $servicesAll);
    if($ids){
      $in = implode(',', array_fill(0,count($ids),'?'));
      $q = $pdo->prepare("SELECT service_id, COUNT(*) slots, SUM(booked_count) booked, SUM(GREATEST(capacity-booked_count,0)) remaining FROM service_slots WHERE service_id IN ($in) AND slot_date BETWEEN ? AND ? GROUP BY service_id");
      $params = array_merge($ids, [$from,$to]);
      $q->execute($params);
      while($r=$q->fetch(PDO::FETCH_ASSOC)){ $aggregateByService[$r['service_id']]=$r; }
    }
  } catch(Throwable $e){}
}
// Sortie partielle AJAX (modal) : retourne uniquement le bloc CRUD service
if($isPartial && $serviceFilter){
  $actionBase='?service='.$serviceFilter.'&from='.urlencode($from).'&to='.urlencode($to).'&partial=1';
  ?>
  <?php foreach($flash as $f): ?>
    <div class="notification is-<?php echo $f['type']==='danger'?'danger':($f['type']==='success'?'success':($f['type']==='warning'?'warning':'info')); ?> is-light" style="padding:.5rem .75rem;font-size:.7rem;">
      <?php echo htmlspecialchars($f['msg']); ?>
    </div>
  <?php endforeach; ?>
  <form method="post" action="<?php echo $actionBase; ?>" class="box" style="padding:.75rem;margin-bottom:.75rem;">
    <input type="hidden" name="csrf" value="<?php echo $csrfToken; ?>">
    <input type="hidden" name="act" value="create">
    <div class="columns is-mobile is-gapless" style="gap:8px;align-items:flex-end;">
      <div class="column is-2">
        <label class="label" style="font-size:.55rem;">Date</label>
        <input required class="input is-small" type="date" name="slot_date" value="<?php echo htmlspecialchars($from); ?>">
      </div>
      <div class="column is-2">
        <label class="label" style="font-size:.55rem;">Début</label>
        <input required class="input is-small" type="time" name="start_time" value="09:00">
      </div>
      <div class="column is-2">
        <label class="label" style="font-size:.55rem;">Fin</label>
        <input required class="input is-small" type="time" name="end_time" value="09:30">
      </div>
      <div class="column is-2">
        <label class="label" style="font-size:.55rem;">Capacité</label>
        <input required class="input is-small" type="number" min="1" name="capacity" value="1">
      </div>
      <div class="column is-2">
        <button class="button is-small is-primary" style="margin-top:.95rem;" type="submit">Ajouter</button>
      </div>
    </div>
    <div class="columns is-mobile" style="margin-top:8px;align-items:center;">
      <div class="column is-12">
        <label class="checkbox" style="font-size:.55rem;">
          <input type="checkbox" name="auto_generate" value="1" onchange="document.getElementById('autoGenBox')?.classList.toggle('is-hidden', !this.checked);"> Générer automatiquement les créneaux selon la durée du soin
        </label>
        <div id="autoGenBox" class="is-hidden" style="border:1px solid #ddd;padding:6px;margin-top:4px;border-radius:4px;">
          <div style="display:flex;flex-wrap:wrap;gap:8px;align-items:flex-end;">
            <div>
              <label style="font-size:.55rem;">Début journée</label>
              <input class="input is-small" type="time" name="auto_start" value="09:00">
            </div>
            <div>
              <label style="font-size:.55rem;">Fin journée</label>
              <input class="input is-small" type="time" name="auto_end" value="18:00">
            </div>
            <div>
              <label style="font-size:.55rem;">Durée du soin (minutes)</label>
              <input class="input is-small" type="number" min="5" max="180" name="auto_duration" value="30">
            </div>
            <div style="flex:1 0 100%;font-size:.55rem;" class="has-text-grey">Génère tous les créneaux de la journée selon la durée du soin. Doublons ignorés.</div>
          </div>
        </div>
      </div>
    </div>
        <div class="columns is-mobile" style="margin-top:4px;">
          <div class="column is-12">
            <label class="checkbox" style="font-size:.55rem;">
                    <input type="hidden" name="apply_all" value="0">
                    <input type="hidden" name="apply_pattern" value="0">
                    <input type="hidden" name="apply_pattern_until" value="<?php echo htmlspecialchars($to); ?>">
                    <button class="button is-small is-link" style="font-size:.55rem;" title="Sauver" type="submit" onclick="return confirmUpdateAll(this.form,this);">Sauver</button>
              <input type="checkbox" name="recur" value="1" onchange="document.getElementById('recurBox')?.classList.toggle('is-hidden', !this.checked);"> Récurrence
            </label>
            <div id="recurBox" class="is-hidden" style="border:1px solid #ddd;padding:6px;margin-top:4px;border-radius:4px;">
              <div style="display:flex;flex-wrap:wrap;gap:8px;align-items:flex-end;">
                <div>
                  <label style="font-size:.55rem;">Type</label>
                  <div class="select is-small">
                    <select name="recur_type" onchange="document.getElementById('recurDays')?.classList.toggle('is-hidden', this.value!=='weekly');">
                      <option value="daily">Quotidien</option>
                      <option value="weekly">Hebdomadaire</option>
                    </select>
                  </div>
                </div>
                <div>
                  <label style="font-size:.55rem;">Jusqu'au</label>
                  <input class="input is-small" type="date" name="end_date" value="<?php echo htmlspecialchars($to); ?>">
                </div>
                <div id="recurDays" class="is-hidden">
                  <label style="font-size:.55rem;display:block;">Jours</label>
                  <div style="display:flex;gap:4px;flex-wrap:wrap;font-size:.6rem;">
                    <?php $jours=['L','M','M','J','V','S','D']; for($i=1;$i<=7;$i++): ?>
                      <label class="checkbox" style="font-size:.55rem;">
                        <input type="checkbox" name="recur_days[]" value="<?php echo $i; ?>"> <?php echo $jours[$i-1]; ?>
                      </label>
                    <?php endfor; ?>
                  </div>
                </div>
                <div style="flex:1 0 100%;font-size:.55rem;" class="has-text-grey">Créera tous les créneaux correspondants entre la date de départ et la date de fin (max 370 jours). Doublons ignorés.</div>
              </div>
            </div>
          </div>
        </div>
  </form>
  <?php if(!$slots): ?>
    <p class="is-size-7 has-text-grey">Aucun créneau sur la période.</p>
  <?php else: ?>
    <div style="max-height:480px;overflow:auto;border:1px solid #ddd;border-radius:4px;">
  <form method="post" action="<?php echo $actionBase; ?>" id="bulkForm">
    <table class="table is-fullwidth is-hoverable is-narrow" data-slot-table>
      <thead class="sticky-head">
        <tr>
          <th><input type="checkbox" id="selectAllSlots" onclick="document.querySelectorAll('[name=slot_bulk[]]').forEach(cb=>cb.checked=this.checked)"></th>
          <th>Date</th><th>Début</th><th>Fin</th><th>Capacité</th><th>Réservé</th><th>Statut</th><th style="width:130px;">Actions</th>
        </tr>
      </thead>
      <tbody>
      <?php foreach($slots as $sl): ?>
        <tr <?php if($sl['status']!=='open') echo 'class="has-background-light"'; ?>>
          <td><input type="checkbox" name="slot_bulk[]" value="<?php echo (int)$sl['id']; ?>"></td>
            <td>
              <form method="post" action="<?php echo $actionBase; ?>" style="display:inline-flex;gap:4px;align-items:center;">
                <input type="hidden" name="csrf" value="<?php echo $csrfToken; ?>">
                <input type="hidden" name="act" value="update">
                <input type="hidden" name="id" value="<?php echo (int)$sl['id']; ?>">
                <input class="input is-small" style="width:115px;" type="date" name="slot_date" value="<?php echo htmlspecialchars($sl['slot_date']); ?>">
            </td>
            <td><input class="input is-small" style="width:70px;" type="time" name="start_time" value="<?php echo htmlspecialchars(substr($sl['start_time'],0,5)); ?>"></td>
            <td><input class="input is-small" style="width:70px;" type="time" name="end_time" value="<?php echo htmlspecialchars(substr($sl['end_time'],0,5)); ?>"></td>
            <td>
                <input class="input is-small" style="width:60px;" type="number" min="1" name="capacity" value="<?php echo (int)$sl['capacity']; ?>" <?php if((int)$sl['booked_count']> (int)$sl['capacity']) echo 'title="Capacité anomalie"'; ?>>
            </td>
            <td><?php echo (int)$sl['booked_count']; ?></td>
            <td>
                <div class="select is-small" style="width:90px;">
                  <select name="status">
                    <option value="open" <?php echo $sl['status']==='open'?'selected':''; ?>>open</option>
                    <option value="closed" <?php echo $sl['status']==='closed'?'selected':''; ?>>closed</option>
                  </select>
                </div>
            </td>
      <td style="white-space:nowrap;">
        <input type="hidden" name="apply_all" value="0">
        <button class="button is-small is-link" style="font-size:.55rem;" title="Sauver" type="submit" onclick="return confirmUpdateAll(this.form,this);">Sauver</button>
      </form>
      <form method="post" action="<?php echo $actionBase; ?>" style="display:inline;">
        <input type="hidden" name="csrf" value="<?php echo $csrfToken; ?>">
        <input type="hidden" name="act" value="toggle">
        <input type="hidden" name="id" value="<?php echo (int)$sl['id']; ?>">
        <button class="button is-small is-warning" style="font-size:.55rem;" type="submit" title="Basculer">↺</button>
      </form>
      </td>
      <td style="white-space:nowrap;">
        <form method="post" action="<?php echo $actionBase; ?>" style="display:inline;">
          <input type="hidden" name="csrf" value="<?php echo $csrfToken; ?>">
          <input type="hidden" name="act" value="delete">
          <input type="hidden" name="id" value="<?php echo (int)$sl['id']; ?>">
          <button class="button is-small is-danger" style="font-size:.55rem;" type="button" title="Supprimer" <?php if((int)$sl['booked_count']>0) echo 'disabled'; ?> onclick="if(confirm('Supprimer ce créneau ?')) this.form.submit();">✕</button>
        </form>
      </td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
  <p class="is-size-7 has-text-grey" style="margin-top:8px;">CRUD actif (modal) : ajouter, modifier date/début/fin (si aucune réservation), capacité, statut, basculer statut, supprimer (si aucune réservation).</p>
  <?php
  exit;
}
?>
<!DOCTYPE html><html lang="fr"><head><meta charset="utf-8"><title>Créneaux - Admin</title>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bulma@0.9.4/css/bulma.min.css">
<style>
  body{background:#f5f6fa;margin:0;padding:0;}
  .wrap{max-width:1200px;margin:0 auto;padding:0 20px 30px;}
  table td, table th{font-size:.75rem;}
  .sticky-head{position:sticky;top:0;background:#fff;z-index:10;}
</style>
</head><body>
<?php include_once __DIR__.'/../inc/menu.php'; ?>
<div class="wrap">
  <h1 class="title is-3">Créneaux</h1>
  <p><a href="services.php" class="button is-small">← Services</a> <a href="calendar.php" class="button is-small is-light">Calendrier</a><?php if($defaultGridId): ?> <span class="tag is-info is-light" style="margin-left:6px;">Grille par défaut</span><?php endif; ?></p>
  <div class="card"><div class="card-content">
    <form method="get" class="columns is-multiline is-gapless" style="align-items:flex-end;">
  <div class="column is-3">
        <label class="label" style="font-size:.65rem;text-transform:uppercase;letter-spacing:.5px;">Service</label>
                    <input type="hidden" name="apply_all" value="0">
                    <input type="hidden" name="apply_pattern" value="0">
                    <input type="hidden" name="apply_pattern_until" value="<?php echo htmlspecialchars($to); ?>">
                    <button class="button is-small is-link" style="font-size:.55rem;" title="Sauver" type="submit" onclick="return confirmUpdateAll(this.form,this);">Sauver</button>
        <div class="select is-fullwidth is-small"><select name="service" onchange="this.form.submit()">
          <option value="">-- Choisir --</option>
          <?php foreach($servicesAll as $svc): ?>
            <option value="<?php echo (int)$svc['id']; ?>" <?php echo $serviceFilter===(int)$svc['id']?'selected':''; ?>><?php echo htmlspecialchars($svc['name']); ?></option>
          <?php endforeach; ?>
        </select></div>
      </div>
      <div class="column is-2">
        <label class="label" style="font-size:.65rem;text-transform:uppercase;letter-spacing:.5px;">Du</label>
        <input class="input is-small" type="date" name="from" value="<?php echo htmlspecialchars($from); ?>">
      </div>
      <div class="column is-2">
        <label class="label" style="font-size:.65rem;text-transform:uppercase;letter-spacing:.5px;">Au</label>
        <input class="input is-small" type="date" name="to" value="<?php echo htmlspecialchars($to); ?>">
      </div>
      <div class="column is-2" style="display:flex;gap:6px;">
        <button class="button is-small is-link" type="submit">Afficher</button>
        <?php if($serviceFilter): ?><a class="button is-small" href="slots.php">Réinit.</a><?php endif; ?>
      </div>
      <div class="column is-3">
        <?php if($serviceFilter): ?>
          <p class="is-size-7 has-text-grey" style="margin-top:22px;">Total: <strong><?php echo $stats['total']; ?></strong> &nbsp; Réservés: <strong><?php echo $stats['booked']; ?></strong> &nbsp; Restants: <strong><?php echo $stats['remaining']; ?></strong></p>
        <?php elseif($defaultGridId): ?>
          <p class="is-size-7 has-text-grey" style="margin-top:22px;">Vue grille par défaut (agrégée)</p>
        <?php else: ?>
          <p class="is-size-7 has-text-grey" style="margin-top:22px;">Sélectionnez un service.</p>
        <?php endif; ?>
      </div>
    </form>
  <?php if($serviceFilter): ?>
      <?php $actionBase='?service='.$serviceFilter.'&from='.urlencode($from).'&to='.urlencode($to).($isPartial?'&partial=1':''); ?>
      <?php foreach($flash as $f): ?>
        <div class="notification is-<?php echo $f['type']==='danger'?'danger':($f['type']==='success'?'success':($f['type']==='warning'?'warning':'info')); ?> is-light" style="padding:.5rem .75rem;font-size:.7rem;">
          <?php echo htmlspecialchars($f['msg']); ?>
        </div>
      <?php endforeach; ?>
  <form method="post" action="<?php echo $actionBase; ?>" class="box" style="padding:.75rem;margin-bottom:.75rem;">
        <input type="hidden" name="csrf" value="<?php echo $csrfToken; ?>">
        <input type="hidden" name="act" value="create">
        <div class="columns is-mobile is-gapless" style="gap:8px;align-items:flex-end;">
          <div class="column is-2">
            <label class="label" style="font-size:.55rem;">Date</label>
            <input required class="input is-small" type="date" name="slot_date" value="<?php echo htmlspecialchars($from); ?>">
          </div>
          <div class="column is-2">
            <label class="label" style="font-size:.55rem;">Début</label>
            <input required class="input is-small" type="time" name="start_time" value="09:00">
          </div>
          <div class="column is-2">
            <label class="label" style="font-size:.55rem;">Fin</label>
            <input required class="input is-small" type="time" name="end_time" value="09:30">
          </div>
            <div class="column is-2">
            <label class="label" style="font-size:.55rem;">Capacité</label>
            <input required class="input is-small" type="number" min="1" name="capacity" value="1">
          </div>
          <div class="column is-2">
            <button class="button is-small is-primary" style="margin-top:.95rem;" type="submit">Ajouter</button>
          </div>
        </div>
    <div class="columns is-mobile" style="margin-top:4px;">
      <div class="column is-12">
        <label class="checkbox" style="font-size:.55rem;">
          <input type="checkbox" name="recur" value="1" onchange="this.closest('form').querySelector('#recurBoxPartial')?.classList.toggle('is-hidden', !this.checked);"> Récurrence
        </label>
        <div id="recurBoxPartial" class="is-hidden" style="border:1px solid #ddd;padding:6px;margin-top:4px;border-radius:4px;">
          <div style="display:flex;flex-wrap:wrap;gap:8px;align-items:flex-end;">
            <div>
              <label style="font-size:.55rem;">Type</label>
              <div class="select is-small">
                <select name="recur_type" onchange="this.closest('form').querySelector('#recurDaysPartial')?.classList.toggle('is-hidden', this.value!=='weekly');">
                  <option value="daily">Quotidien</option>
                  <option value="weekly">Hebdomadaire</option>
                </select>
              </div>
            </div>
            <div>
              <label style="font-size:.55rem;">Jusqu'au</label>
              <input class="input is-small" type="date" name="end_date" value="<?php echo htmlspecialchars($to); ?>">
            </div>
            <div id="recurDaysPartial" class="is-hidden">
              <label style="font-size:.55rem;display:block;">Jours</label>
              <div style="display:flex;gap:4px;flex-wrap:wrap;font-size:.6rem;">
                <?php $jours=['L','M','M','J','V','S','D']; for($i=1;$i<=7;$i++): ?>
                  <label class="checkbox" style="font-size:.55rem;">
                    <input type="checkbox" name="recur_days[]" value="<?php echo $i; ?>"> <?php echo $jours[$i-1]; ?>
                  </label>
                <?php endfor; ?>
              </div>
            </div>
            <div style="flex:1 0 100%;font-size:.55rem;" class="has-text-grey">Récurrence sur période; doublons ignorés.</div>
          </div>
        </div>
      </div>
    </div>
      </form>
      <?php if(!$slots): ?>
        <p class="is-size-7 has-text-grey">Aucun créneau sur la période.</p>
      <?php else: ?>
        <div style="max-height:480px;overflow:auto;border:1px solid #ddd;border-radius:4px;">
          <table class="table is-fullwidth is-hoverable is-narrow" data-slot-table>
            <thead class="sticky-head"><tr><th>Date</th><th>Début</th><th>Fin</th><th>Capacité</th><th>Réservé</th><th>Statut</th><th style="width:130px;">Actions</th></tr></thead>
            <tbody>
            <?php foreach($slots as $sl): ?>
              <tr <?php if($sl['status']!=='open') echo 'class="has-background-light"'; ?>>
                <td>
                  <form method="post" action="<?php echo $actionBase; ?>" style="display:inline-flex;gap:4px;align-items:center;">
                    <input type="hidden" name="csrf" value="<?php echo $csrfToken; ?>">
                    <input type="hidden" name="act" value="update">
                    <input type="hidden" name="id" value="<?php echo (int)$sl['id']; ?>">
                    <input class="input is-small" style="width:115px;" type="date" name="slot_date" value="<?php echo htmlspecialchars($sl['slot_date']); ?>">
                </td>
                <td><input class="input is-small" style="width:70px;" type="time" name="start_time" value="<?php echo htmlspecialchars(substr($sl['start_time'],0,5)); ?>"></td>
                <td><input class="input is-small" style="width:70px;" type="time" name="end_time" value="<?php echo htmlspecialchars(substr($sl['end_time'],0,5)); ?>"></td>
                <td>
                    <input class="input is-small" style="width:60px;" type="number" min="1" name="capacity" value="<?php echo (int)$sl['capacity']; ?>" <?php if((int)$sl['booked_count']> (int)$sl['capacity']) echo 'title="Capacité anomalie"'; ?>>
                </td>
                <td><?php echo (int)$sl['booked_count']; ?></td>
                <td>
                    <div class="select is-small" style="width:90px;">
                      <select name="status">
                        <option value="open" <?php echo $sl['status']==='open'?'selected':''; ?>>open</option>
                        <option value="closed" <?php echo $sl['status']==='closed'?'selected':''; ?>>closed</option>
                      </select>
                    </div>
                </td>
        <td style="white-space:nowrap;">
          <input type="hidden" name="apply_all" value="0">
          <button class="button is-small is-link" style="font-size:.55rem;" title="Sauver" type="submit" onclick="return confirmUpdateAll(this.form,this);">Sauver</button>
                  </form>
                  <form method="post" action="<?php echo $actionBase; ?>" style="display:inline;">
                    <input type="hidden" name="csrf" value="<?php echo $csrfToken; ?>">
                    <input type="hidden" name="act" value="toggle">
                    <input type="hidden" name="id" value="<?php echo (int)$sl['id']; ?>">
                    <button class="button is-small is-warning" style="font-size:.55rem;" type="submit" title="Basculer">↺</button>
                  </form>
                  <form method="post" action="<?php echo $actionBase; ?>" style="display:inline;">
                    <input type="hidden" name="csrf" value="<?php echo $csrfToken; ?>">
                    <input type="hidden" name="act" value="delete">
                    <input type="hidden" name="id" value="<?php echo (int)$sl['id']; ?>">
                    <button class="button is-small is-danger" style="font-size:.55rem;" type="button" title="Supprimer" <?php if((int)$sl['booked_count']>0) echo 'disabled'; ?> onclick="if(confirm('Supprimer ce créneau ?')) this.form.submit();">✕</button>
                  </form>
                </td>
              </tr>
            <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      <?php endif; ?>
    <?php elseif($defaultGridId): ?>
      <h2 class="title is-6" style="margin-top:1rem;">Synthèse (<?php echo htmlspecialchars($from); ?> → <?php echo htmlspecialchars($to); ?>)</h2>
      <?php if(!$aggregateByService): ?>
        <p class="is-size-7 has-text-grey">Aucun créneau pour la grille par défaut sur la période.</p>
      <?php else: ?>
        <table class="table is-fullwidth is-narrow is-striped" style="font-size:.7rem;">
          <thead><tr><th>Service</th><th>Créneaux</th><th>Réservés</th><th>Restants</th><th style="width:90px;">Actions</th></tr></thead>
          <tbody>
          <?php foreach($servicesAll as $svc): $agg=$aggregateByService[$svc['id']]??null; ?>
            <tr>
              <td><?php echo htmlspecialchars($svc['name']); ?></td>
              <td><?php echo $agg?(int)$agg['slots']:0; ?></td>
              <td><?php echo $agg?(int)$agg['booked']:0; ?></td>
              <td><?php echo $agg?(int)$agg['remaining']:0; ?></td>
              <td>
                <button type="button" data-manage-service="<?php echo (int)$svc['id']; ?>" class="button is-small is-link" style="font-size:.55rem;" title="Gérer">Gérer</button>
              </td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      <?php endif; ?>
    <?php endif; ?>
  <p class="is-size-7 has-text-grey" style="margin-top:8px;">CRUD actif : ajouter, modifier date/début/fin (si aucune réservation), capacité, statut, basculer statut, supprimer (si aucune réservation).</p>
  </div></div>
  <?php if(!$serviceFilter): // Modal + JS seulement sur vue agrégée ?>
  <div class="modal" id="slotModal">
    <div class="modal-background"></div>
    <div class="modal-card" style="width:900px;max-width:95vw;">
      <header class="modal-card-head" style="padding:8px 12px;">
        <p class="modal-card-title" style="font-size:1rem;">Gestion des créneaux</p>
        <button class="delete" aria-label="close" id="slotModalClose"></button>
      </header>
      <section class="modal-card-body" id="slotModalBody" style="padding:10px;">
        <p class="is-size-7 has-text-grey">Chargement…</p>
      </section>
      <footer class="modal-card-foot" style="padding:8px 12px;justify-content:flex-end;">
        <button class="button" id="slotModalClose2">Fermer</button>
      </footer>
    </div>
  </div>
  <script>
    // Confirmation lot pour vue agrégée (modal géré plus bas aussi)
    function confirmUpdateAll(form,btn){
      if(!form) return true;
      if(!form.querySelector('input[name="act"][value="update"]')) return true;
      const fromInput=document.querySelector('input[name="from"]');
      const toInput=document.querySelector('input[name="to"]');
      const fromDate=fromInput?fromInput.value:''; const toDate=toInput?toInput.value:'';
      const choice=prompt('Appliquer ce changement de capacité / statut ?\n1 = seulement ce créneau\n2 = tous les créneaux de la période ('+fromDate+' → '+toDate+')\n3 = récurrence future (même horaire) jusqu\'au '+toDate+'\nAnnuler = annuler','1');
      if(choice===null) return false; // annuler submit
      if(choice.trim()==='2'){
        form.querySelector('input[name="apply_all"]').value='1';
      } else if(choice.trim()==='3'){
        let ap=form.querySelector('input[name="apply_pattern"]'); if(!ap){ ap=document.createElement('input'); ap.type='hidden'; ap.name='apply_pattern'; form.appendChild(ap);} ap.value='1';
        let apu=form.querySelector('input[name="apply_pattern_until"]'); if(!apu){ apu=document.createElement('input'); apu.type='hidden'; apu.name='apply_pattern_until'; form.appendChild(apu);} apu.value=toDate;
      }
      // Auto propagation lors modification capacité ou statut (vue complète)
      document.addEventListener('change', function(e){
        const tgt=e.target;
        if(!(tgt instanceof HTMLElement)) return;
        if(tgt.closest('#slotModal')) return; // modal géré séparément
        if(tgt.name==='capacity' || tgt.name==='status' || tgt.name==='start_time' || tgt.name==='end_time'){
          const form=tgt.closest('form');
          if(!form || !form.querySelector('input[name="act"][value="update"]')) return;
          const fromDate=document.querySelector('input[name="from"]').value;
          const toDate=document.querySelector('input[name="to"]').value;
          const choice=prompt('Appliquer ce changement immédiatement ?\n1 = seulement ce créneau (Sauver auto)\n2 = tous les créneaux de la période\n3 = récurrence future (même horaire) jusqu\'au '+toDate+'\nAnnuler = ignorer','1');
          if(choice===null) return; // pas d'action
          if(choice.trim()==='2'){
            let aa=form.querySelector('input[name="apply_all"]'); if(!aa){ aa=document.createElement('input'); aa.type='hidden'; aa.name='apply_all'; form.appendChild(aa);} aa.value='1';
          } else if(choice.trim()==='3'){
            let ap=form.querySelector('input[name="apply_pattern"]'); if(!ap){ ap=document.createElement('input'); ap.type='hidden'; ap.name='apply_pattern'; form.appendChild(ap);} ap.value='1';
            let apu=form.querySelector('input[name="apply_pattern_until"]'); if(!apu){ apu=document.createElement('input'); apu.type='hidden'; apu.name='apply_pattern_until'; form.appendChild(apu);} apu.value=toDate;
          }
          form.submit();
        }
      });
      return true;
    }
  (function(){
    const modal=document.getElementById('slotModal');
    if(!modal) return;
    const body=document.getElementById('slotModalBody');
    function openSlotModal(serviceId){
      const from=document.querySelector('input[name="from"]').value;
      const to=document.querySelector('input[name="to"]').value;
      modal.classList.add('is-active');
      loadPartial(serviceId, from, to);
    }
    function closeSlotModal(){ modal.classList.remove('is-active'); }
    function loadPartial(id, from, to){
      body.innerHTML='<p class="is-size-7 has-text-grey">Chargement…</p>';
      fetch('slots.php?service='+id+'&from='+encodeURIComponent(from)+'&to='+encodeURIComponent(to)+'&partial=1',{credentials:'same-origin'})
        .then(r=>r.text())
        .then(html=>{ body.innerHTML=html; attachHandlers(); })
        .catch(()=>{ body.innerHTML='<p class="has-text-danger is-size-7">Erreur de chargement.</p>'; });
    }
    function attachHandlers(){
      body.querySelectorAll('form').forEach(f=>{
        f.addEventListener('submit', function(ev){
          // Vérifie si le bouton submit est celui de suppression
          const activeElement = document.activeElement;
          if(f.querySelector('input[name="act"][value="delete"]')){
            if(activeElement && activeElement.type === 'button' && activeElement.title === 'Supprimer'){
              if(!confirm('Supprimer ce créneau ?')) { ev.preventDefault(); return; }
            }
          }
          if(f.querySelector('input[name="act"][value="update"]')){
            ev.preventDefault();
            const fromDate = document.querySelector('input[name="from"]').value;
            const toDate = document.querySelector('input[name="to"]').value;
            const choice = prompt('Appliquer ce changement de capacité / statut ?\n1 = seulement ce créneau\n2 = tous les créneaux de la période ('+fromDate+' → '+toDate+')\n3 = récurrence future (même horaire) jusqu\'au '+toDate+'\nAnnuler = annuler','1');
            if(choice===null) return; // abort
            if(choice.trim()==='2'){
              let aa=f.querySelector('input[name="apply_all"]'); if(!aa){ aa=document.createElement('input'); aa.type='hidden'; aa.name='apply_all'; f.appendChild(aa);} aa.value='1';
            } else if(choice.trim()==='3'){
              let ap=f.querySelector('input[name="apply_pattern"]'); if(!ap){ ap=document.createElement('input'); ap.type='hidden'; ap.name='apply_pattern'; f.appendChild(ap);} ap.value='1';
              let apu=f.querySelector('input[name="apply_pattern_until"]'); if(!apu){ apu=document.createElement('input'); apu.type='hidden'; apu.name='apply_pattern_until'; f.appendChild(apu);} apu.value=toDate;
            }
            const fd=new FormData(f);
            fetch(f.getAttribute('action')+(f.getAttribute('action').indexOf('partial=1')===-1?'&partial=1':''), {method:'POST', body:fd, credentials:'same-origin'})
              .then(r=>r.text())
              .then(html=>{ body.innerHTML=html; attachHandlers(); })
              .catch(()=>{ alert('Erreur réseau'); });
          }
        });
      });
      // Auto change dans modal
  // Suppression du listener auto-submit sur les champs capacité/statut dans le modal
  // Correction : éviter le double prompt dans la modal
  // On retire tout listener 'change' dans la modal, le prompt est déjà géré dans le submit
  // S'assurer qu'aucun listener 'change' n'est ajouté dans la modal
    }
    document.querySelectorAll('[data-manage-service]').forEach(btn=>{
      btn.addEventListener('click', ()=> openSlotModal(btn.getAttribute('data-manage-service')));
    });
    document.getElementById('slotModalClose').addEventListener('click', closeSlotModal);
    document.getElementById('slotModalClose2').addEventListener('click', closeSlotModal);
    modal.querySelector('.modal-background').addEventListener('click', closeSlotModal);
    window.addEventListener('keydown', (e)=>{ if(e.key==='Escape') closeSlotModal(); });
  })();
  </script>
  <?php endif; ?>
</div>
</body></html>
