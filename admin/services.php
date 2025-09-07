<?php
// Fichier renommé depuis admin_services.php
@ini_set('display_errors','1');
@ini_set('display_startup_errors','1');
error_reporting(E_ALL);
session_start();
$isAdmin = false;
if (!empty($_SESSION['is_admin']) && $_SESSION['is_admin']) $isAdmin = true;
if (isset($_SESSION['role']) && $_SESSION['role']==='admin') $isAdmin = true;
if (isset($_SESSION['role_id']) && $_SESSION['role_id']==1) $isAdmin = true;
if (!$isAdmin) { header('Location: ../membre/login.php'); exit; }
require_once __DIR__ . '/../inc/db.php';
// Lib agenda maintenant dans le dossier agenda
require_once __DIR__ . '/../agenda/lib_services.php';
if (!$pdo) { die('DB indisponible'); }

$detectedTable = agenda_detect_services_table($pdo);
if (!$detectedTable) {
  try { $pdo->exec("CREATE TABLE IF NOT EXISTS services (id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY, name VARCHAR(120) NOT NULL, duration_minutes INT NOT NULL DEFAULT 30, max_per_day INT NOT NULL DEFAULT 5, active TINYINT(1) NOT NULL DEFAULT 1)"); } catch(Throwable $e) {}
  $detectedTable = 'services';
}

// --- Gestion flexible de la table service_meta (deux variantes possibles) ---
try {
  $exists = $pdo->query("SHOW TABLES LIKE 'service_meta'")->fetch();
  if(!$exists){
    // On crée le modèle clé/valeur moderne si absent
    $pdo->exec("CREATE TABLE service_meta (service_id INT NOT NULL, meta_key VARCHAR(64) NOT NULL, meta_value VARCHAR(255) NOT NULL, PRIMARY KEY(service_id, meta_key)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
  }
} catch(Throwable $e) { /* ignore */ }

if(!function_exists('meta_schema_mode')){
  function meta_schema_mode(PDO $pdo): string {
    static $mode=null; static $cols=null; if($mode!==null) return $mode;
    try {
      $cols = $pdo->query('DESCRIBE service_meta')->fetchAll(PDO::FETCH_COLUMN,0);
      if(in_array('meta_key',$cols)) $mode='kv';
      else $mode='legacy';
    } catch(Throwable $e){ $mode='kv'; }
    return $mode;
  }
}
if(!function_exists('meta_set')){
  function meta_set(PDO $pdo, int $service_id, string $key, $value, ?string $serviceTableDetected=null){
    $mode = meta_schema_mode($pdo);
    if($mode==='kv'){
      $st = $pdo->prepare('REPLACE INTO service_meta(service_id, meta_key, meta_value) VALUES (?,?,?)');
      $st->execute([$service_id,$key,(string)$value]);
      return;
    }
    // legacy: colonnes directes, potentiellement service_table
    try {
      $cols = $pdo->query('DESCRIBE service_meta')->fetchAll(PDO::FETCH_COLUMN,0);
      $hasServiceTable = in_array('service_table',$cols);
      // Créer ligne si absente
      if($hasServiceTable){
        $chk = $pdo->prepare('SELECT 1 FROM service_meta WHERE service_table=? AND service_id=? LIMIT 1');
        $chk->execute([$serviceTableDetected,$service_id]);
        if(!$chk->fetch()){
          // insérer base
            $pdo->prepare('INSERT INTO service_meta(service_table, service_id) VALUES (?,?)')->execute([$serviceTableDetected,$service_id]);
        }
      } else {
        $chk = $pdo->prepare('SELECT 1 FROM service_meta WHERE service_id=? LIMIT 1');
        $chk->execute([$service_id]);
        if(!$chk->fetch()) $pdo->prepare('INSERT INTO service_meta(service_id) VALUES (?)')->execute([$service_id]);
      }
      $colMap = ['max_per_day'=>'max_per_day','active'=>'active','duration_minutes_override'=>'duration_minutes_override'];
      if(isset($colMap[$key]) && in_array($colMap[$key],$cols)){
        if($hasServiceTable){
          $pdo->prepare("UPDATE service_meta SET `{$colMap[$key]}`=? WHERE service_table=? AND service_id=?")
              ->execute([(string)$value,$serviceTableDetected,$service_id]);
        } else {
          $pdo->prepare("UPDATE service_meta SET `{$colMap[$key]}`=? WHERE service_id=?")
              ->execute([(string)$value,$service_id]);
        }
      }
    } catch(Throwable $e){ /* ignore */ }
  }
}
if(!function_exists('meta_get')){
  function meta_get(PDO $pdo, int $service_id, string $key, ?string $serviceTableDetected=null){
    $mode = meta_schema_mode($pdo);
    if($mode==='kv'){
      $st=$pdo->prepare('SELECT meta_value FROM service_meta WHERE service_id=? AND meta_key=? LIMIT 1');
      $st->execute([$service_id,$key]);
      $v=$st->fetchColumn(); return $v===false?null:$v;
    }
    try {
      $cols = $pdo->query('DESCRIBE service_meta')->fetchAll(PDO::FETCH_COLUMN,0);
      $hasServiceTable = in_array('service_table',$cols);
      if(!in_array($key,$cols)) return null; // legacy colonne manquante
      if($hasServiceTable){
        $st=$pdo->prepare("SELECT `$key` FROM service_meta WHERE service_table=? AND service_id=? LIMIT 1");
        $st->execute([$serviceTableDetected,$service_id]);
      } else {
        $st=$pdo->prepare("SELECT `$key` FROM service_meta WHERE service_id=? LIMIT 1");
        $st->execute([$service_id]);
      }
      $v=$st->fetchColumn(); return $v===false?null:$v;
    } catch(Throwable $e){ return null; }
  }
}

$error=''; $success='';

if ($_SERVER['REQUEST_METHOD']==='POST') {
  $action = $_POST['action'] ?? '';
  try {
    if ($action==='create') {
      $name = trim($_POST['name']??'');
      $dur = intval($_POST['duration_minutes']??30);
      $max = intval($_POST['max_per_day']??5);
      $active = isset($_POST['active'])?1:0;
      if ($name==='') throw new Exception('Nom requis');
      $st=$pdo->prepare('INSERT INTO services (name,duration_minutes,max_per_day,active) VALUES (?,?,?,?)');
      $st->execute([$name,$dur,$max,$active]);
      $success='Service créé';
    } elseif ($action==='update') {
      $id=intval($_POST['id']??0); if(!$id) throw new Exception('ID manquant');
      $name = trim($_POST['name']??'');
      $dur = intval($_POST['duration_minutes']??30);
      $max = intval($_POST['max_per_day']??5);
      $active = isset($_POST['active'])?1:0;
      if ($detectedTable==='services') {
        $st=$pdo->prepare('UPDATE services SET name=?, duration_minutes=?, max_per_day=?, active=? WHERE id=?');
        $st->execute([$name,$dur,$max,$active,$id]);
      } else {
        // Table externe : mettre à jour si colonnes existantes sinon meta
        $map = agenda_map_service_columns($pdo, $detectedTable);
        // Créer table meta si absente
        $pdo->exec("CREATE TABLE IF NOT EXISTS service_meta (service_id INT NOT NULL, meta_key VARCHAR(64) NOT NULL, meta_value VARCHAR(255) NOT NULL, PRIMARY KEY(service_id, meta_key)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        $descCols = $pdo->query("DESCRIBE `{$detectedTable}`")->fetchAll(PDO::FETCH_COLUMN,0);
        $baseUpdates = [];$params=[];
        if ($name!=='' && isset($map['name']) && in_array($map['name'],$descCols)) { $baseUpdates[] = "`{$map['name']}`=?"; $params[]=$name; }
        if (isset($map['duration']) && in_array($map['duration'],$descCols)) { $baseUpdates[] = "`{$map['duration']}`=?"; $params[]=$dur; }
        if (isset($map['max_per_day']) && in_array($map['max_per_day'],$descCols)) { $baseUpdates[] = "`{$map['max_per_day']}`=?"; $params[]=$max; } else { meta_set($pdo,$id,'max_per_day',$max); }
        if (isset($map['active']) && in_array($map['active'],$descCols)) { $baseUpdates[] = "`{$map['active']}`=?"; $params[]=$active; } else { meta_set($pdo,$id,'active',$active); }
        if (!in_array($map['duration'],$descCols)) { meta_set($pdo,$id,'duration_minutes_override',$dur); }
        if ($baseUpdates) {
          $params[]=$id;
          $sql="UPDATE `{$detectedTable}` SET ".implode(',', $baseUpdates)." WHERE `{$map['id']}`=?";
          $st = $pdo->prepare($sql); $st->execute($params);
        }
      }
      $success='Service mis à jour';
    } elseif ($action==='delete') {
      $id=intval($_POST['id']??0); if(!$id) throw new Exception('ID manquant');
      $pdo->prepare('DELETE FROM services WHERE id=?')->execute([$id]);
      $success='Service supprimé';
    } elseif ($action==='gen_slots') {
      $service_id=intval($_POST['service_id']??0);
      $from = $_POST['from_date'] ?? '';
      $to = $_POST['to_date'] ?? '';
      $start_h = $_POST['start_hour'] ?? '09:00';
      $end_h = $_POST['end_hour'] ?? '17:00';
      $capacity = intval($_POST['capacity']??1);
      if(!$service_id||!$from||!$to) throw new Exception('Paramètres manquants');
      // Utiliser table détectée + mapping
      $svcTableGen = $detectedTable; // peut être prestations
      $mapGen = agenda_map_service_columns($pdo,$svcTableGen);
      $idColGen = $mapGen['id']; $durColGen=$mapGen['duration']; $maxColGen=$mapGen['max_per_day']; $actColGen=$mapGen['active'];
      $stmtS = $pdo->prepare("SELECT * FROM `{$svcTableGen}` WHERE `{$idColGen}`=?");
      $stmtS->execute([$service_id]);
      $svc = $stmtS->fetch(PDO::FETCH_ASSOC);
      if(!$svc) throw new Exception('Service introuvable');
      // Récupération meta si colonnes absentes
      $maxPerDayVal = null; $activeVal = 1;
      if(!$mapGen['_has_max_per_day'] || !$mapGen['_has_active']) {
        try {
          $metaQ = $pdo->prepare('SELECT max_per_day,active FROM service_meta WHERE service_table=? AND service_id=?');
          $metaQ->execute([$svcTableGen,$service_id]);
          $meta = $metaQ->fetch(PDO::FETCH_ASSOC);
          if($meta){ $maxPerDayVal = (int)$meta['max_per_day']; $activeVal = (int)$meta['active']; }
        } catch(Throwable $e) {}
      }
      if($mapGen['_has_active']) $activeVal = (int)($svc[$actColGen] ?? 1);
      if(!$activeVal) throw new Exception('Service inactif');
      $durRaw = $svc[$durColGen] ?? 30;
      $dur = is_numeric($durRaw) ? intval($durRaw) : agenda_parse_duration_to_minutes($durRaw);
      $maxPerDay = $mapGen['_has_max_per_day'] ? (int)($svc[$maxColGen] ?? 8) : ($maxPerDayVal ?? 8);
      if($dur<=0) $dur=30;
      // Création table slots si manquante
      $pdo->exec("CREATE TABLE IF NOT EXISTS service_slots (id INT AUTO_INCREMENT PRIMARY KEY, service_id INT NOT NULL, slot_date DATE NOT NULL, start_time TIME NOT NULL, end_time TIME NOT NULL, capacity INT NOT NULL DEFAULT 1, booked_count INT NOT NULL DEFAULT 0, status ENUM('open','closed') NOT NULL DEFAULT 'open', UNIQUE KEY uniq_slot(service_id,slot_date,start_time,end_time)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
      $cur = new DateTime($from); $endDate = new DateTime($to); $endDate->setTime(23,59,59);
      $inserted=0; $skipped=0;
      while($cur <= $endDate) {
        $slotDate = $cur->format('Y-m-d');
        $tStart = DateTime::createFromFormat('Y-m-d H:i', $slotDate.' '.$start_h);
        $tEndDay = DateTime::createFromFormat('Y-m-d H:i', $slotDate.' '.$end_h);
        while($tStart < $tEndDay) {
          $tEnd = (clone $tStart)->modify('+'.$dur.' minutes');
          if ($tEnd > $tEndDay) break;
          $cnt = $pdo->prepare('SELECT COUNT(*) c FROM service_slots WHERE service_id=? AND slot_date=?');
          $cnt->execute([$service_id,$slotDate]);
          $nb = (int)$cnt->fetch()['c'];
          if ($nb >= $maxPerDay) { $skipped++; break; }
          $ins = $pdo->prepare('INSERT IGNORE INTO service_slots(service_id,slot_date,start_time,end_time,capacity) VALUES (?,?,?,?,?)');
          $ok = $ins->execute([$service_id,$slotDate,$tStart->format('H:i:s'),$tEnd->format('H:i:s'),$capacity]);
          if ($ok && $ins->rowCount()>0) $inserted++; else $skipped++;
          $tStart = $tEnd;
        }
        $cur->modify('+1 day');
      }
      $success = 'Créneaux générés: '.$inserted.' / ignorés: '.$skipped;
    }
  } catch(Exception $e){ $error=$e->getMessage(); }
}

$map = agenda_map_service_columns($pdo, $detectedTable);
try {
  $descCols = $pdo->query("DESCRIBE `{$detectedTable}`")->fetchAll(PDO::FETCH_COLUMN,0);
  if ($detectedTable === 'services') {
    if (!in_array($map['duration'],$descCols)) { $pdo->exec("ALTER TABLE `{$detectedTable}` ADD COLUMN `duration_minutes` INT NOT NULL DEFAULT 30"); $map['duration']='duration_minutes'; }
    if (!in_array($map['max_per_day'],$descCols)) { $pdo->exec("ALTER TABLE `{$detectedTable}` ADD COLUMN `max_per_day` INT NOT NULL DEFAULT 8"); $map['max_per_day']='max_per_day'; }
    if (!in_array($map['active'],$descCols)) { $pdo->exec("ALTER TABLE `{$detectedTable}` ADD COLUMN `active` TINYINT(1) NOT NULL DEFAULT 1"); $map['active']='active'; }
  }
} catch(Throwable $e) {}
// Filtre grilles (grid_id) si présent dans la table source
$selectedGrid = isset($_GET['grid']) ? trim($_GET['grid']) : '';
$servicesAll = agenda_fetch_services($pdo);
$hasGrid = false; $grids = [];
foreach ($servicesAll as $s) {
  if (isset($s['_raw']['grid_id'])) { $hasGrid = true; break; }
}
if ($hasGrid) {
  // Charger les grilles (table grids) si existe
  try {
    $gExists = $pdo->query("SHOW TABLES LIKE 'grids'")->fetch();
    if ($gExists) {
      $stmtG = $pdo->query("SELECT g.id,g.name,g.is_default, COUNT(p.id) AS cnt FROM grids g LEFT JOIN prestations p ON p.grid_id=g.id GROUP BY g.id ORDER BY g.is_default DESC, g.name ASC");
      $grids = $stmtG->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }
  } catch(Throwable $e) { /* ignore */ }
}
// Appliquer automatiquement la grille par défaut si aucune sélection donnée dans l'URL
if($hasGrid && $selectedGrid===''){
  foreach($grids as $g){ if(!empty($g['is_default'])){ $selectedGrid = (string)$g['id']; break; } }
}
if ($hasGrid && $selectedGrid !== '') {
  $services = array_filter($servicesAll, function($row) use ($selectedGrid) {
    return (string)($row['_raw']['grid_id'] ?? '') === $selectedGrid;
  });
} else {
  $services = $servicesAll;
}

// Helper meta (défini après usage dans update mais chargé à l'inclusion du script entier)
if (!function_exists('meta_set')) {
  function meta_set(PDO $pdo, int $service_id, string $key, $value) {
    $stmt = $pdo->prepare("REPLACE INTO service_meta(service_id, meta_key, meta_value) VALUES (?,?,?)");
    $stmt->execute([$service_id,$key,(string)$value]);
  }
}
// --- Préparation visualisation des créneaux existants ---
$viewSlotsService = isset($_GET['slots_service']) ? intval($_GET['slots_service']) : 0;
$viewFrom = isset($_GET['slots_from']) && preg_match('/^\\d{4}-\\d{2}-\\d{2}$/', $_GET['slots_from']) ? $_GET['slots_from'] : date('Y-m-d');
$viewTo = isset($_GET['slots_to']) && preg_match('/^\\d{4}-\\d{2}-\\d{2}$/', $_GET['slots_to']) ? $_GET['slots_to'] : date('Y-m-d', strtotime('+14 days'));
$slotsList = [];$slotsStats=['total'=>0,'booked'=>0,'remaining'=>0];
if($viewSlotsService>0){
  try {
    // s'assurer table
    $pdo->exec("CREATE TABLE IF NOT EXISTS service_slots (id INT AUTO_INCREMENT PRIMARY KEY, service_id INT NOT NULL, slot_date DATE NOT NULL, start_time TIME NOT NULL, end_time TIME NOT NULL, capacity INT NOT NULL DEFAULT 1, booked_count INT NOT NULL DEFAULT 0, status ENUM('open','closed') NOT NULL DEFAULT 'open', UNIQUE KEY uniq_slot(service_id,slot_date,start_time,end_time)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    $stmtVS = $pdo->prepare('SELECT * FROM service_slots WHERE service_id=? AND slot_date BETWEEN ? AND ? ORDER BY slot_date,start_time LIMIT 800');
    $stmtVS->execute([$viewSlotsService,$viewFrom,$viewTo]);
    while($r=$stmtVS->fetch(PDO::FETCH_ASSOC)){
      $slotsList[]=$r;
      $slotsStats['total']++;
      $slotsStats['booked'] += (int)$r['booked_count'];
      $slotsStats['remaining'] += max(0, (int)$r['capacity'] - (int)$r['booked_count']);
    }
  } catch(Throwable $e){ /* ignore */ }
}
?><!DOCTYPE html>
<html lang="fr"><head><meta charset="utf-8"><title>Services - Agenda</title>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bulma@0.9.4/css/bulma.min.css">
<style>body{background:#f5f6fa;margin:0;padding:0;} .wrap{max-width:1100px;margin:0 auto;padding:0 20px 20px;box-sizing:border-box;} .card{margin-bottom:2rem;} </style>
<style>
  body{margin:0;}
  header{margin:0;}
  .wrap > h1.title{margin-top:0;padding-top:4px;}
  /* Accessibilité labels masqués */
  .sr-only{position:absolute;width:1px;height:1px;padding:0;margin:-1px;overflow:hidden;clip:rect(0,0,0,0);white-space:nowrap;border:0;}
  form.gen-slots .column{padding-top:0.25rem;padding-bottom:0.25rem;}
  form.gen-slots .input, form.gen-slots select{height:2.2rem;}
</style>
</head><body>
<?php include_once __DIR__ . '/../inc/menu.php'; ?>
<div class="wrap">
  <h1 class="title is-3">Services (Gestion)</h1>
  <p>
    <a href="index.php" class="button is-small">← Admin</a>
  <a href="calendar.php" class="button is-small is-link" style="margin-left:4px;">Calendrier</a>
  <a href="slots.php" class="button is-small is-warning is-light" style="margin-left:4px;">Créneaux</a>
    <a href="../agenda/index.php" class="button is-small is-light" style="margin-left:4px;">Vue publique agenda</a>
  </p>
  <?php if($error): ?><div class="notification is-danger"><?php echo htmlspecialchars($error); ?></div><?php endif; ?>
  <?php if($success): ?><div class="notification is-primary"><?php echo htmlspecialchars($success); ?></div><?php endif; ?>

  <?php if ($detectedTable==='services'): ?>
    <div class="card"><div class="card-content">
      <h2 class="title is-5">Créer un service</h2>
      <form method="post" class="columns is-multiline">
        <input type="hidden" name="action" value="create">
        <div class="column is-3"><input class="input" name="name" placeholder="Nom" required></div>
        <div class="column is-2"><input class="input" type="number" name="duration_minutes" value="30" min="5" step="5" placeholder="Durée (min)"></div>
        <div class="column is-2"><input class="input" type="number" name="max_per_day" value="8" min="1" step="1" placeholder="Max/jour"></div>
        <div class="column is-2"><label class="checkbox"><input type="checkbox" name="active" checked> Actif</label></div>
        <div class="column is-2"><button class="button is-primary" type="submit">Ajouter</button></div>
      </form>
    </div></div>
  <?php else: ?>
    <div class="notification is-light">Table détectée: <strong><?php echo htmlspecialchars($detectedTable); ?></strong> (lecture). Meta utilisée pour `max_per_day` & `active` si absent.</div>
  <?php endif; ?>

  <div class="card"><div class="card-content">
    <h2 class="title is-5">Générer des créneaux</h2>
    <div class="columns is-multiline is-gapless gen-slots-head" style="font-size:0.7rem; text-transform:uppercase; letter-spacing:.5px; margin:0 0 2px;">
      <?php if($hasGrid): ?><div class="column is-2">Grille</div><?php endif; ?>
      <div class="column is-2<?php if(!$hasGrid) echo ' is-3'; ?>">Service</div>
      <div class="column is-2">Date début</div>
      <div class="column is-2">Date fin</div>
      <div class="column is-1">H. début</div>
      <div class="column is-1">H. fin</div>
  <div class="column is-1">Capacité</div>
      <div class="column is-1" style="text-align:right;">&nbsp;</div>
    </div>
    <form method="post" class="columns is-multiline gen-slots" style="align-items:flex-end;">
      <input type="hidden" name="action" value="gen_slots">
      <?php if($hasGrid): ?>
      <div class="column is-2">
        <label class="sr-only" for="gs_grid">Grille</label>
        <div class="select is-fullwidth">
          <select id="gs_grid" title="Filtrer par grille">
            <option value="">Toutes grilles</option>
            <?php foreach($grids as $g): ?>
              <option value="<?php echo (int)$g['id']; ?>" <?php echo ($selectedGrid!=='' && (int)$selectedGrid===(int)$g['id'])?'selected':''; ?>><?php echo htmlspecialchars($g['name']); ?><?php if(!empty($g['is_default'])) echo ' ★'; ?></option>
            <?php endforeach; ?>
          </select>
        </div>
      </div>
      <?php endif; ?>
      <div class="column is-2<?php if(!$hasGrid) echo ' is-3'; ?>">
        <label class="sr-only" for="gs_service">Service</label>
        <div class="select is-fullwidth"><select id="gs_service" name="service_id" required title="Service concerné">
          <option value="">Service...</option>
          <?php foreach($services as $s): $gid = $s['grid_id'] ?? ($s['_raw']['grid_id'] ?? null); ?>
            <option value="<?php echo $s['id']; ?>" data-grid="<?php echo $gid!==null ? (int)$gid : ''; ?>"><?php echo htmlspecialchars($s['name']); ?></option>
          <?php endforeach; ?>
        </select></div>
      </div>
      <div class="column is-2">
        <label class="sr-only" for="gs_from">Date début</label>
        <input id="gs_from" class="input" type="date" name="from_date" required title="Premier jour inclus" placeholder="Date début">
      </div>
      <div class="column is-2">
        <label class="sr-only" for="gs_to">Date fin</label>
        <input id="gs_to" class="input" type="date" name="to_date" required title="Dernier jour inclus" placeholder="Date fin">
      </div>
      <div class="column is-1">
        <label class="sr-only" for="gs_hstart">Heure début</label>
        <input id="gs_hstart" class="input" type="time" name="start_hour" value="09:00" title="Heure de début" placeholder="Début">
      </div>
      <div class="column is-1">
        <label class="sr-only" for="gs_hend">Heure fin</label>
        <input id="gs_hend" class="input" type="time" name="end_hour" value="17:00" title="Heure limite" placeholder="Fin">
      </div>
      <div class="column is-1">
        <label class="sr-only" for="gs_cap">Capacité (nombre de places par créneau)</label>
        <input id="gs_cap" class="input" type="number" name="capacity" value="1" min="1" title="Capacité (nombre de rendez-vous possibles sur ce créneau)" placeholder="Places" aria-label="Capacité (places par créneau)">
      </div>
      <div class="column is-1" style="display:flex;align-items:flex-end;">
        <button class="button is-link is-fullwidth" type="submit" title="Générer les créneaux pour la période">Générer</button>
      </div>
    </form>
  <p class="is-size-7 has-text-grey">Max/jour respecté, créneaux existants ignorés. Capacité = nombre de rendez-vous possibles simultanément sur chaque créneau.</p>
    <?php if($hasGrid): ?>
    <script>
    (function(){
      const selGrid=document.getElementById('gs_grid');
      const selSvc=document.getElementById('gs_service');
      if(!selGrid||!selSvc) return;
      const allOptions=[...selSvc.querySelectorAll('option')].slice(1); // skip placeholder
      function filter(){
        const gid=selGrid.value;
        const current=selSvc.value;
        selSvc.innerHTML='<option value="">Service...</option>';
        allOptions.forEach(o=>{
          if(!gid || o.dataset.grid==gid){ selSvc.appendChild(o); }
        });
        if(![...selSvc.options].some(o=>o.value===current)) selSvc.selectedIndex=0;
      }
      selGrid.addEventListener('change', filter);
      filter();
    })();
    </script>
    <?php endif; ?>
  </div></div>

  <div class="card"><div class="card-content">
    <h2 class="title is-5">Liste / CRUD (édition directe)</h2>
    <?php // Sélecteur (haut) réassurance si manquant
    if($hasGrid && isset($grids) && $grids): ?>
      <form method="get" class="field is-grouped is-grouped-multiline" style="margin:6px 0 14px; align-items:flex-end;">
        <div class="control">
          <label class="label is-small" style="font-size:0.7rem; text-transform:uppercase; letter-spacing:.5px;">Grille</label>
          <div class="select is-small">
            <select name="grid" onchange="this.form.submit()">
              <option value="">Toutes</option>
              <?php foreach($grids as $g): ?>
                <option value="<?php echo (int)$g['id']; ?>" <?php echo ($selectedGrid!=='' && (int)$selectedGrid===(int)$g['id'])?'selected':''; ?>>
                  <?php echo htmlspecialchars($g['name']); ?><?php if(!empty($g['is_default'])) echo ' ★'; ?> (<?php echo (int)$g['cnt']; ?>)
                </option>
              <?php endforeach; ?>
            </select>
          </div>
        </div>
        <?php if($selectedGrid!==''): ?>
          <div class="control" style="padding-top:1.2rem;">
            <a class="button is-small" href="?">Réinitialiser</a>
          </div>
        <?php endif; ?>
      </form>
    <?php endif; ?>
    <?php if($detectedTable !== 'services'): ?>
    <?php endif; ?>
    <table class="table is-fullwidth is-striped is-narrow"><thead><tr><th>#</th><th>Nom</th><th>Durée (min)</th><th>Max/jour</th><th>Actif</th><th>Actions</th></tr></thead><tbody>
      <?php foreach($services as $svc): ?>
        <tr>
          <form method="post">
            <td><?php echo $svc['id']; ?><input type="hidden" name="action" value="update"><input type="hidden" name="id" value="<?php echo $svc['id']; ?>"></td>
            <td><input class="input is-small" name="name" value="<?php echo htmlspecialchars($svc['name']); ?>" required></td>
            <td style="width:110px;"><input class="input is-small" type="number" name="duration_minutes" value="<?php echo (int)$svc['duration_minutes']; ?>" min="5" step="5"></td>
            <td style="width:110px;"><input class="input is-small" type="number" name="max_per_day" value="<?php echo (int)$svc['max_per_day']; ?>" min="1" step="1"></td>
            <td style="width:90px; text-align:center;">
              <label class="checkbox"><input type="checkbox" name="active" <?php echo $svc['active']?'checked':''; ?>></label>
            </td>
            <td>
              <button class="button is-primary is-small" type="submit">Sauver</button>
              <?php if ($detectedTable==='services'): ?>
                <button class="button is-danger is-small" name="action" value="delete" onclick="return confirm('Supprimer ce service ?');">Suppr</button>
              <?php endif; ?>
            </td>
          </form>
        </tr>
      <?php endforeach; ?>
    </tbody></table>
    <div class="field" style="margin-top:10px;">
      <label class="label" style="font-size:.7rem;text-transform:uppercase;letter-spacing:.5px;">Recherche</label>
      <input type="text" id="svcFilter" class="input is-small" placeholder="Filtrer les services (nom)..." autocomplete="off">
    </div>
    <script>
    (function(){
      const input = document.getElementById('svcFilter');
      const table = input && input.closest('.card-content').querySelector('table');
      if(!input || !table) return;
      const rows = Array.from(table.tBodies[0].rows);
      function norm(s){ return s.normalize('NFD').replace(/\p{Diacritic}/gu,'').toLowerCase(); }
      input.addEventListener('input',()=>{
        const q = norm(input.value.trim());
        rows.forEach(r=>{
          if(!q){ r.style.display=''; return; }
          const nameCell = r.querySelector('input[name="name"]');
          const txt = nameCell ? norm(nameCell.value) : norm(r.textContent||'');
          r.style.display = txt.includes(q) ? '' : 'none';
        });
      });
    })();
    </script>
  <?php // On garde une seule sélection (celle du haut déjà ajoutée plus tôt) ?>
  </div></div>
</div>
</body></html>
