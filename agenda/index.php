<?php
@ini_set('display_errors','1');
@ini_set('display_startup_errors','1');
error_reporting(E_ALL);
session_start();
require_once __DIR__ . '/../inc/db.php';
require_once __DIR__ . '/lib_services.php';
if(!$pdo) { die('Base de données indisponible'); }
// Récupération services bruts
$allServices = agenda_fetch_services($pdo);
// Déterminer grille par défaut si table grids existe
$defaultGridId = null;
try {
  $gExists = $pdo->query("SHOW TABLES LIKE 'grids'")->fetch();
  if($gExists){
    $dg = $pdo->query("SELECT id FROM grids WHERE is_default=1 ORDER BY id ASC LIMIT 1")->fetchColumn();
    if($dg) $defaultGridId = (int)$dg;
  }
} catch(Throwable $e){}
if($defaultGridId){
  $services = array_filter($allServices, function($s) use ($defaultGridId){
    $gid = $s['grid_id'] ?? ($s['_raw']['grid_id'] ?? null);
    return $s['active'] && (int)$gid === $defaultGridId;
  });
} else {
  $services = array_filter($allServices, fn($s)=>$s['active']);
}
// Indiquer si source externe (prestations)
$srcTable = $services ? ($services[0]['_table'] ?? '') : '';
$logged = isset($_SESSION['user_id']);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="utf-8" />
  <title>Agenda – Prise de rendez-vous</title>
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <meta name="description" content="Prendre rendez-vous pour un soin" />
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bulma@0.9.4/css/bulma.min.css">
  <style>
    body{background:#f6f7f9;}
    .hero.is-light{background:linear-gradient(135deg,#eef2f7,#f9fbfd);} 
    .services-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(220px,1fr));gap:16px;margin-top:1.5rem;}
    .svc{background:#fff;border:1px solid #ddd;border-radius:8px;padding:14px;display:flex;flex-direction:column;gap:8px;box-shadow:0 2px 4px rgba(0,0,0,.05);} 
    .svc h3{font-size:1.05rem;margin:0;font-weight:600;}
    .slots-mini{margin-top:1rem;}
    .slot-pill{display:inline-block;margin:4px 4px 0 0;padding:4px 8px;font-size:.8rem;border:1px solid #3273dc;border-radius:999px;color:#3273dc;}
    .login-box{margin-top:2rem;padding:1rem;border:1px dashed #bbb;background:#fff;border-radius:6px;}
  </style>
</head>
<body>
<section class="hero is-light">
  <div class="hero-body">
    <div class="container">
      <h1 class="title is-3">Agenda des soins</h1>
      <p class="subtitle is-6">Consultez les soins disponibles et réservez un créneau en quelques clics.</p>
      <div>
        <?php if($logged): ?>
          <a href="prendre_rdv.php" class="button is-primary">Prendre un rendez-vous</a>
          <a href="mes_rendezvous.php" class="button is-link is-light">Mes rendez-vous</a>
        <?php else: ?>
          <a href="../membre/login.php" class="button is-primary">Se connecter</a>
          <a href="../membre/register.php" class="button is-link is-light">Créer un compte</a>
        <?php endif; ?>
        <a href="../index.php" class="button is-light">Accueil du site</a>
      </div>
    </div>
  </div>
</section>

<div class="container" style="padding:2rem 1rem 3rem;">
  <h2 class="title is-4">Soins proposés <?php if($defaultGridId) echo '<span class="tag is-light is-info" style="vertical-align:middle;">Grille par défaut</span>'; ?></h2>
  <?php if($srcTable && $srcTable!=='services'): ?>
    <p class="is-size-7 has-text-grey">Source: table <code><?php echo htmlspecialchars($srcTable); ?></code></p>
  <?php endif; ?>
  <?php if(empty($services)): ?>
    <div class="notification is-warning">Aucun soin actif pour le moment.</div>
  <?php else: ?>
    <div class="services-grid">
      <?php foreach($services as $s): ?>
        <div class="svc">
          <h3><?php echo htmlspecialchars($s['name']); ?></h3>
          <p style="margin:0;font-size:.85rem;color:#555;">Durée: <?php echo (int)$s['duration_minutes']; ?> min<br>Quota max / jour: <?php echo (int)$s['max_per_day']; ?></p>
          <?php if($logged): ?>
            <a class="button is-small is-link" href="prendre_rdv.php?service_id=<?php echo (int)$s['id']; ?>">Réserver</a>
          <?php else: ?>
            <a class="button is-small is-link is-light" href="../membre/login.php">Se connecter pour réserver</a>
          <?php endif; ?>
          <div class="slots-mini" data-service="<?php echo (int)$s['id']; ?>">
            <span class="is-size-7 has-text-grey">Chargement des prochains créneaux…</span>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>

  <?php if(!$logged): ?>
    <div class="login-box">
      <strong>Réservation en ligne :</strong> créez un compte ou connectez‑vous pour choisir un créneau et gérer vos rendez-vous.
    </div>
  <?php endif; ?>
</div>
<script>
// Charger pour chaque service les 3 prochains jours de créneaux (si slots déjà générés)
(function(){
  const blocks = document.querySelectorAll('.slots-mini');
  if(!blocks.length) return;
  const today = new Date();
  const from = today.toISOString().slice(0,10);
  const toDate = new Date(); toDate.setDate(today.getDate()+2); // 3 jours
  const to = toDate.toISOString().slice(0,10);
  blocks.forEach(b => {
    const sid = b.getAttribute('data-service');
    fetch('api_slots.php?service_id='+encodeURIComponent(sid)+'&from='+from+'&to='+to)
      .then(r=>r.json()).then(j=>{
        if(j.error){ b.innerHTML='<span class="is-size-7 has-text-danger">'+j.error+'</span>'; return; }
        if(!j.slots.length){ b.innerHTML='<span class="is-size-7 has-text-grey">Aucun créneau proche.</span>'; return; }
        b.innerHTML='';
        j.slots.slice(0,6).forEach(sl=>{
          const sp = document.createElement('span');
          sp.className='slot-pill';
          sp.textContent = sl.slot_date+' '+sl.start_time.substring(0,5);
          b.appendChild(sp);
        });
      }).catch(()=>{ b.innerHTML='<span class="is-size-7 has-text-danger">Erreur chargement</span>'; });
  });
})();
</script>
</body>
</html>
