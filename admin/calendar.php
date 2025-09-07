<?php
@ini_set('display_errors','1'); @ini_set('display_startup_errors','1'); error_reporting(E_ALL);
session_start();
@setlocale(LC_TIME,'fr_FR.UTF-8','fr_FR','fr'); // Pour noms de mois/jours en français si disponible
if(!isset($_SESSION['user'])) { header('Location: ../membre/login.php'); exit; }
// Autorisation basique admin (adapter si besoin)
$isAdmin = false;
if (!empty($_SESSION['is_admin']) || (isset($_SESSION['role_id']) && $_SESSION['role_id']==1) || (isset($_SESSION['role']) && $_SESSION['role']==='admin')) $isAdmin=true;
if(!$isAdmin){ header('Location: index.php'); exit; }
require_once __DIR__.'/../inc/db.php';
require_once __DIR__.'/../agenda/lib_services.php';
if(!$pdo) die('DB');

// Détection table services / prestations
$svcTable = agenda_detect_services_table($pdo) ?: 'services';
$map = agenda_map_service_columns($pdo,$svcTable);
$idCol=$map['id']; $nameCol=$map['name']; $activeCol=$map['active'];

// Paramètres mois
$year = isset($_GET['y']) ? (int)$_GET['y'] : (int)date('Y');
$month = isset($_GET['m']) ? (int)$_GET['m'] : (int)date('n');
if($month<1||$month>12){ $month=(int)date('n'); $year=(int)date('Y'); }
$firstDay = DateTime::createFromFormat('Y-m-d', sprintf('%04d-%02d-01',$year,$month));
$lastDay = (clone $firstDay)->modify('last day of this month');
$startCal = (clone $firstDay)->modify('monday this week');
$endCal = (clone $lastDay)->modify('sunday this week');

// Services actifs (limite 200)
$services=[]; $svcIndex=[];
try {
  $sqlS = "SELECT `$idCol` AS id, `$nameCol` AS name".
    ($activeCol?", `$activeCol` AS active":"").
    " FROM `$svcTable`".($activeCol?" WHERE `$activeCol`=1":"")." ORDER BY name ASC LIMIT 200";
  $services = $pdo->query($sqlS)->fetchAll(PDO::FETCH_ASSOC);
  foreach($services as $s){ $svcIndex[$s['id']]=$s; }
} catch(Throwable $e){}

// Slots agrégés sur le mois
$slotsByDate = [];
// Slots détaillés (pour affichage modal)
$slotsFullByDate = [];
try {
  $stmt = $pdo->prepare("SELECT service_id, slot_date, COUNT(*) total, SUM(CASE WHEN booked_count>=capacity THEN 1 ELSE 0 END) full_cnt, SUM(GREATEST(capacity-booked_count,0)) remaining FROM service_slots WHERE slot_date BETWEEN ? AND ? GROUP BY service_id, slot_date");
  $stmt->execute([$firstDay->format('Y-m-d'), $lastDay->format('Y-m-d')]);
  while($r=$stmt->fetch(PDO::FETCH_ASSOC)){
    $d=$r['slot_date'];
    if(!isset($slotsByDate[$d])) $slotsByDate[$d]=[];
    $slotsByDate[$d][$r['service_id']]=$r;
  }
  // Récupération slots détaillés
  $stmt2 = $pdo->prepare("SELECT id, service_id, slot_date, start_time, end_time, capacity, booked_count, status FROM service_slots WHERE slot_date BETWEEN ? AND ? ORDER BY slot_date,start_time ASC");
  $stmt2->execute([$firstDay->format('Y-m-d'), $lastDay->format('Y-m-d')]);
  while($r=$stmt2->fetch(PDO::FETCH_ASSOC)){
    $d=$r['slot_date'];
    if(!isset($slotsFullByDate[$d])) $slotsFullByDate[$d]=[];
    $slotsFullByDate[$d][]=$r;
  }
} catch(Throwable $e){}

function svc_color_css($id){
  $h = ($id * 57) % 360; return "hsl($h,70%,50%)";
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="utf-8" />
  <title>Calendrier créneaux</title>
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bulma@0.9.4/css/bulma.min.css">
  <style>
    body{margin:0;background:#f5f6fa;font-family:system-ui,Arial,sans-serif;}
    .wrap{max-width:1300px;margin:0 auto;padding:0 20px 40px;}
    .cal-grid{display:grid;grid-template-columns:repeat(7,1fr);border:1px solid #ddd;border-right:0;border-bottom:0;width:100%;}
    .cal-head{display:grid;grid-template-columns:repeat(7,1fr);text-align:center;font-size:.75rem;text-transform:uppercase;letter-spacing:.5px;margin-bottom:4px;width:100%;}
    .cal-cell{min-height:110px;border-right:1px solid #ddd;border-bottom:1px solid #ddd;background:#fff;position:relative;padding:4px 4px 6px;display:flex;flex-direction:column;gap:2px;}
    .cal-cell.other-month{background:#fafafa;color:#bbb;}
    .day-number{font-size:.75rem;font-weight:600;}
    .badge{display:inline-flex;align-items:center;font-size:.55rem;font-weight:600;line-height:1;padding:3px 6px;border-radius:12px;color:#fff;gap:4px;white-space:nowrap;}
    .badge small{font-weight:400;font-size:.55rem;opacity:.9;}
    .legend{display:flex;flex-wrap:wrap;gap:6px;margin:12px 0 20px;}
    .legend .badge{font-size:.6rem;}
    .month-nav{display:flex;align-items:center;justify-content:space-between;margin:12px 0 10px;}
    .cal-cell:hover{outline:2px solid #3273dc50;z-index:2;}
    .empty-msg{font-size:.8rem;color:#999;}
  /* Force orientation horizontale si un style externe impose un writing-mode vertical */
  .month-nav h1, .cal-cell, .cal-cell * , .legend, .legend * {writing-mode:horizontal-tb;text-orientation:mixed;}
  /* Sécurité: empêcher transform inattendu qui casserait l’affichage */
  .month-nav h1, .cal-cell{transform:none !important;}
  </style>
</head>
<body>
<?php include_once __DIR__.'/../inc/menu.php'; ?>
<div class="wrap">
  <div class="month-nav">
    <?php
      $prev=(clone $firstDay)->modify('-1 month');
      $next=(clone $firstDay)->modify('+1 month');
      function fmt_month(DateTime $d, bool $short=false){
        static $hasIntl=null; if($hasIntl===null) $hasIntl=class_exists('IntlDateFormatter');
        if($hasIntl){
          $pattern = $short ? 'MMM yyyy' : 'MMMM yyyy';
          $fmt = new IntlDateFormatter('fr_FR', IntlDateFormatter::NONE, IntlDateFormatter::NONE, $d->getTimezone()->getName(), IntlDateFormatter::GREGORIAN, $pattern);
          $txt=$fmt->format($d);
          if($txt!==false) return mb_convert_case($txt, MB_CASE_TITLE, 'UTF-8');
        }
        // Fallback manuel
        $mois=[1=>'janvier','février','mars','avril','mai','juin','juillet','août','septembre','octobre','novembre','décembre'];
        $m = $mois[(int)$d->format('n')] ?? $d->format('F');
        if($short){ $m = mb_substr($m,0,3); }
        return $m.' '.$d->format('Y');
      }
    ?>
    <a class="button is-small" href="?y=<?php echo $prev->format('Y'); ?>&m=<?php echo $prev->format('n'); ?>">← <?php echo htmlspecialchars(fmt_month($prev,true)); ?></a>
    <h1 class="title is-4" style="margin:0;">Calendrier des créneaux – <?php echo htmlspecialchars(fmt_month($firstDay,false)); ?></h1>
    <a class="button is-small" href="?y=<?php echo $next->format('Y'); ?>&m=<?php echo $next->format('n'); ?>"><?php echo htmlspecialchars(fmt_month($next,true)); ?> →</a>
  </div>
  <p class="is-size-7 has-text-grey">Badges: service (créneaux restants / total). Survoler un jour pour mettre en évidence.</p>
  <div class="legend">
    <?php foreach($services as $s): $c=svc_color_css($s['id']); ?>
      <span class="badge" style="background:<?php echo $c; ?>;"><?php echo htmlspecialchars($s['name']); ?></span>
    <?php endforeach; if(!count($services)) echo '<span class="empty-msg">Aucun service actif.</span>'; ?>
  </div>
  <div class="cal-head">
    <?php $days=["Lun","Mar","Mer","Jeu","Ven","Sam","Dim"]; foreach($days as $d) echo '<div>'.$d.'</div>'; ?>
  </div>
  <div class="cal-grid">
    <?php
    $cur=(clone $startCal);
    while($cur <= $endCal){
      $dStr=$cur->format('Y-m-d');
      $other=$cur->format('n')!=$month;
      echo '<div class="cal-cell'.($other?' other-month':'').'" data-date="'.$dStr.'" style="cursor:pointer;">';
      echo '<div class="day-number">'.$cur->format('j').'</div>';
      if(isset($slotsByDate[$dStr])){
        foreach($slotsByDate[$dStr] as $svcId=>$info){
          if(!isset($svcIndex[$svcId])) continue; $c=svc_color_css($svcId);
          $remaining = (int)$info['remaining']; $total=(int)$info['total'];
          $label = htmlspecialchars($svcIndex[$svcId]['name']);
          echo '<span class="badge" style="background:'.$c.';" title="'.$label.'">'.htmlspecialchars(mb_strimwidth($label,0,12,'…')).' <small>'.$remaining.'/'.$total.'</small></span>';
        }
      } else {
        echo '<span class="empty-msg" style="margin-top:auto;">–</span>';
      }
      echo '</div>';
      $cur->modify('+1 day');
    }
    ?>
  </div>
  <div style="margin-top:18px;">
    <a href="services.php" class="button is-small">← Services</a>
    <a href="appointments_validate.php" class="button is-small is-light">Validation RDV</a>
  </div>
  <!-- Modal détail jour -->
  <div class="modal" id="dayModal">
    <div class="modal-background"></div>
    <div class="modal-card" style="width:640px;max-width:95vw;">
      <header class="modal-card-head">
        <p class="modal-card-title" id="dayModalTitle" style="font-size:1.05rem;">Créneaux</p>
        <button class="delete" aria-label="close" id="dayModalClose"></button>
      </header>
      <section class="modal-card-body" id="daySlotsBody" style="padding-top:10px;">
        <p class="has-text-grey is-size-7">Chargement...</p>
      </section>
      <footer class="modal-card-foot" style="justify-content:space-between;">
        <div class="is-size-7 has-text-grey" id="daySummary"></div>
        <button class="button" id="dayModalClose2">Fermer</button>
      </footer>
    </div>
  </div>
</div>
<script>
// Données PHP -> JS
const CAL_SLOTS = <?php echo json_encode($slotsFullByDate, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES); ?>;
const CAL_SERVICES = <?php echo json_encode(array_map(function($s){return ['id'=>$s['id'],'name'=>$s['name']];}, $services), JSON_UNESCAPED_UNICODE); ?>;
const CAL_COLORS = <?php echo json_encode(array_map(function($s){return svc_color_css($s['id']);}, $services), JSON_UNESCAPED_UNICODE); ?>;
function colorForService(id){ return CAL_COLORS[id] || '#888'; }
function fmtTime(t){ return t ? t.substring(0,5) : ''; }
function remaining(slot){ return Math.max(0, slot.capacity - slot.booked_count); }
function buildDay(dateStr){
  const slots = (CAL_SLOTS[dateStr]||[]).slice().sort((a,b)=>a.start_time.localeCompare(b.start_time));
  if(!slots.length) return '<p class="has-text-grey">Aucun créneau.</p>';
  // Grouper par service
  const bySvc={};
  slots.forEach(s=>{ (bySvc[s.service_id]=bySvc[s.service_id]||[]).push(s); });
  let html='';
  Object.keys(bySvc).sort((a,b)=>a-b).forEach(sid=>{
    const svc = CAL_SERVICES.find(o=>o.id==sid);
    const name = svc?svc.name:('Service #'+sid);
    const cols = colorForService(sid);
    html += '<div style="margin-bottom:10px;">';
    html += '<h4 style="margin:0 0 4px;font-size:.8rem;text-transform:uppercase;letter-spacing:.5px;color:'+cols+';">'+name+'</h4>';
    html += '<table class="table is-narrow is-fullwidth" style="font-size:.7rem;">';
    html += '<thead><tr><th style="width:70px;">Début</th><th style="width:70px;">Fin</th><th style="width:70px;">Cap.</th><th style="width:70px;">Restant</th><th>Statut</th></tr></thead><tbody>';
    bySvc[sid].forEach(sl=>{
      const rem=remaining(sl); const full = rem===0 || sl.status==='closed';
      html += '<tr'+(full?' class="has-background-light"':'')+'>'+
        '<td>'+fmtTime(sl.start_time)+'</td>'+
        '<td>'+fmtTime(sl.end_time)+'</td>'+
        '<td>'+sl.capacity+'</td>'+
        '<td>'+(rem)+'</td>'+
        '<td><span class="tag is-'+(full?'danger':'success')+' is-light" style="font-size:.55rem;">'+(sl.status==='closed'?'fermé':(full?'complet':'ouvert'))+'</span></td>'+
      '</tr>';
    });
    html+='</tbody></table></div>';
  });
  return html;
}
document.addEventListener('DOMContentLoaded',()=>{
  const modal=document.getElementById('dayModal');
  const body=document.getElementById('daySlotsBody');
  const title=document.getElementById('dayModalTitle');
  const summary=document.getElementById('daySummary');
  function openDay(dateStr){
    title.textContent='Créneaux du '+dateStr;
    body.innerHTML=buildDay(dateStr);
    const all=(CAL_SLOTS[dateStr]||[]); let tot=all.length; let rem=0; all.forEach(s=>rem+=remaining(s));
    summary.textContent = tot+' créneau(x), '+rem+' place(s) restante(s) totales.';
    modal.classList.add('is-active');
  }
  function close(){ modal.classList.remove('is-active'); }
  document.querySelectorAll('.cal-cell').forEach(cell=>{
    cell.addEventListener('click',()=>{ openDay(cell.getAttribute('data-date')); });
  });
  document.getElementById('dayModalClose').addEventListener('click',close);
  document.getElementById('dayModalClose2').addEventListener('click',close);
  modal.querySelector('.modal-background').addEventListener('click',close);
  document.addEventListener('keydown',e=>{ if(e.key==='Escape') close(); });
});
</script>
</body>
</html>