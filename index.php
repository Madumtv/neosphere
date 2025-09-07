<?php
// Session & base de données
if (session_status() === PHP_SESSION_NONE) {
	session_start();
}
// ===== Mode debug (activer avec ?debug=1) =====
$__debugMode = isset($_GET['debug']) && $_GET['debug'] == '1';
if ($__debugMode) {
	ini_set('display_errors', '1');
	ini_set('display_startup_errors', '1');
	error_reporting(E_ALL);
	// Capture erreurs fatales pour les voir plutôt qu'un 500 blanc
	register_shutdown_function(function(){
		$e = error_get_last();
		if($e && in_array($e['type'], [E_ERROR,E_PARSE,E_CORE_ERROR,E_COMPILE_ERROR])){
			echo "<div style='background:#300;color:#f99;padding:14px;font-family:monospace;'><strong>FATAL</strong> ".htmlspecialchars($e['message'])."<br><small>".htmlspecialchars($e['file']).":".$e['line']."</small></div>";
		}
	});
	set_exception_handler(function(Throwable $ex){
		echo "<div style='background:#112;color:#9ef;padding:14px;font-family:monospace;'><strong>EXCEPTION</strong> ".htmlspecialchars($ex->getMessage())."<br><small>".htmlspecialchars($ex->getFile()).":".$ex->getLine()."</small><pre style='white-space:pre-wrap;'>".htmlspecialchars($ex->getTraceAsString())."</pre></div>";});
	set_error_handler(function($severity,$message,$file,$line){
		if(!(error_reporting() & $severity)) return false;
		echo "<div style='background:#221;color:#fd6;padding:8px;font-family:monospace;'><strong>PHP</strong> ".htmlspecialchars($message)."<br><small>".htmlspecialchars($file).":".$line."</small></div>"; return true;});
}
function debug_log_local($msg){ if(!is_dir(__DIR__.'/logs')) @mkdir(__DIR__.'/logs'); @file_put_contents(__DIR__.'/logs/debug.log', date('c')." \t".$msg."\n", FILE_APPEND); }

require __DIR__.'/inc/db.php';
// Helpers blocs de contenu dynamiques
require_once __DIR__.'/inc/content.php';
// Détection utilisateur connecté : priorité à user_id sinon fallback sur session 'user'
$currentUser = null;
if (!empty($_SESSION['user_id']) && isset($pdo) && $pdo instanceof PDO) {
	try {
		$stmtU = $pdo->prepare('SELECT id, username, pseudo, email, role_id FROM users WHERE id = ? LIMIT 1');
		$stmtU->execute([$_SESSION['user_id']]);
		$currentUser = $stmtU->fetch();
	} catch (Throwable $e) { error_log('[user fetch] '.$e->getMessage()); }
} elseif (!empty($_SESSION['user'])) {
	// Construire un objet minimal afin que l’interface détecte la connexion
	$currentUser = [
		'id' => null,
		'username' => $_SESSION['user'],
		'pseudo' => $_SESSION['user'],
		'email' => null,
		'role_id' => isset($_SESSION['role_id']) ? (int)$_SESSION['role_id'] : null,
	];
}
// Récupération dynamique des prestations (table 3) – sélectionne la grille par défaut si elle existe
$prestations = [];
try {
	if (isset($pdo) && $pdo instanceof PDO) {
		$defaultGridId = null;
		$stmt = $pdo->query("SELECT id FROM grids WHERE is_default=1 ORDER BY id LIMIT 1");
		$defaultGridId = $stmt->fetchColumn();
		if (!$defaultGridId) { // fallback première grille
			$stmt = $pdo->query("SELECT id FROM grids ORDER BY is_default DESC, id LIMIT 1");
			$defaultGridId = $stmt->fetchColumn();
		}
		if ($defaultGridId) {
			$stmt = $pdo->prepare("SELECT id, nom, duree, description, prix_ttc, poids, emoji FROM prestations WHERE grid_id=? ORDER BY poids ASC, nom ASC");
			$stmt->execute([$defaultGridId]);
		} else { // fallback toutes prestations (si aucune grille)
			$stmt = $pdo->query("SELECT id, nom, duree, description, prix_ttc, poids, emoji FROM prestations ORDER BY grid_id DESC, poids ASC, nom ASC");
		}
		$prestations = $stmt->fetchAll();
	}
} catch (Throwable $e) {
	error_log('[prestations] '.$e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="fr">
  <head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
	<title>Néosphère - Esthétique & bien-être</title>
	<meta name="description" content="<?php echo strip_tags(content_get('meta_description','Néosphère by Lindsay Serkeyn esthétique & bien-être moderne à Rebecq. Prenez rendez-vous dès maintenant.')); ?>" />
	<meta name="author" content="<?php echo strip_tags(content_get('meta_author','Néosphère')); ?>" />
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
	<link href="https://fonts.googleapis.com/css2?family=Dancing+Script:wght@500;600&display=swap" rel="stylesheet">

	<meta property="og:title" content="<?php echo strip_tags(content_get('og_title','Néosphère - esthétique & bien-être à Rebecq')); ?>" />
	<meta property="og:description" content="<?php echo strip_tags(content_get('og_description','Néosphère, cabinet moderne offrant une gamme complète de services esthétique avec une approche humaine et personnalisée.')); ?>" />
    <meta property="og:type" content="website" />
    <meta property="og:image" content="https://pub-bb2e103a32db4e198524a2e9ed8f35b4.r2.dev/f28adda3-eb69-42f4-b70e-dac30f78847c/id-preview-0d2135ee--8e6c6b2e-8478-430b-a1d4-379251552694.lovable.app-1756631809744.png" />

    <meta name="twitter:card" content="summary_large_image" />
    <meta name="twitter:site" content="@lovable_dev" />
    <meta name="twitter:image" content="https://pub-bb2e103a32db4e198524a2e9ed8f35b4.r2.dev/f28adda3-eb69-42f4-b70e-dac30f78847c/id-preview-0d2135ee--8e6c6b2e-8478-430b-a1d4-379251552694.lovable.app-1756631809744.png" />
    <script type="module" crossorigin src="/assets/index-B2DV3Bjp.js"></script>
    <link rel="stylesheet" crossorigin href="/assets/index-B-8CwsTr.css">
  
<style>
	body,html{margin:0;padding:0;}
	body{
		/* Image de fond globale */
		/* Empêcher répétition et assurer que le contenu reste lisible */
		min-height:100%;
		font-family:Inter, sans-serif;
	}

	<?php $isAdminInline = isset($_SESSION['role']) && $_SESSION['role']==='admin'; ?>
	/* Overrides layout corrections */
	#services{background:rgba(255,255,255,0.88); backdrop-filter:blur(3px); border-top:1px solid #f2eadf;}
	#equipe{background:rgba(255,255,255,0.88); backdrop-filter:blur(3px); border-top:1px solid #f2eadf;}
	#equipe .section-wrapper{padding-top:42px;}
	@media(max-width:900px){#equipe .section-wrapper{padding-top:36px;}}
	@media(max-width:600px){#equipe .section-wrapper{padding-top:30px;}}
	.services-intro-block{text-align:center; max-width:840px; margin:0 auto 10px;}
	.cta-row.center{justify-content:center;}
	.team-title{font-size:30px; margin-top:12px;}
	/* Pagination services: afficher 8 par défaut */
	#toggle-services{position:relative;}
	.svc-hidden{display:none;}

	/* ===== Section Contact ===== */
	#contact{background:rgba(255,255,255,0.9); backdrop-filter:blur(3px); border-top:1px solid #f2eadf;}
	/* Contact full width */
	#contact.section-wrapper{max-width:100%; padding-left:0; padding-right:0; padding-top:20px; padding-bottom:24px;}
	#contact .contact-inner{max-width:1250px; margin:0 auto; padding:40px 60px 36px;}
	@media(max-width:900px){#contact .contact-inner{padding:46px 30px 32px;}}
	@media(max-width:600px){#contact .contact-inner{padding:38px 24px 28px;}}
	.contact-grid{display:grid; grid-template-columns:360px 1fr; gap:46px; margin-top:48px; align-items:start;}
	@media(max-width:1050px){.contact-grid{grid-template-columns:1fr;}}
	.contact-box{background:#fff; border:1px solid #eee; border-radius:14px; padding:34px 38px; box-shadow:0 4px 14px -6px #00000012;}
	.contact-box h3{margin:0 0 18px; font-size:20px; color:#222;}
	.info-list{list-style:none; padding:0; margin:0; display:flex; flex-direction:column; gap:18px;}
	.info-item{display:flex; gap:14px;}
	.info-ico{width:42px; height:42px; border-radius:12px; background:#FFF3D6; display:flex; align-items:center; justify-content:center; font-size:20px; color:#E6A23C; flex-shrink:0;}
	.info-desc span{display:block;}
	.info-desc .lbl{font-size:12px; letter-spacing:.5px; text-transform:uppercase; color:#999; font-weight:600; margin-bottom:4px;}
	.info-desc .val{font-size:14px; color:#333; font-weight:500; line-height:1.35;}
	.quick-actions{margin-top:34px; background:#fff; border:1px solid #eee; border-radius:10px; padding:22px 26px; display:flex; flex-direction:column; gap:12px; box-shadow:0 4px 12px -6px #00000010;}
	.action-btn{width:100%; background:#FFC94D; color:#fff; border:none; border-radius:6px; padding:12px 18px; font-weight:600; font-size:14px; cursor:pointer; display:flex; align-items:center; gap:8px; justify-content:center; text-decoration:none;}
	.action-btn.alt{background:#fff; color:#222; border:2px solid #FFC94D;}
	.action-btn.alt:hover{background:#FFF9ED;}
	/* Variante quand déplacée dans le formulaire (mise à jour) */
	.contact-form .quick-actions{margin-top:28px; background:transparent; border:none; padding:0; box-shadow:none; flex-direction:row; gap:16px;}
	.contact-form .quick-actions .action-btn{flex:1;}
	@media(max-width:700px){.contact-form .quick-actions{flex-direction:column;}}
	.contact-form{background:#fff; border:1px solid #eee; border-radius:14px; padding:34px 40px 38px; box-shadow:0 4px 14px -6px #00000012;}
	.contact-form h3{margin:0 0 18px; font-size:20px; color:#222;}
	.form-row{display:grid; grid-template-columns:repeat(auto-fit,minmax(210px,1fr)); gap:18px; margin-bottom:18px;}
	.form-group{display:flex; flex-direction:column; gap:6px;}
	.form-group label{font-size:12px; text-transform:uppercase; letter-spacing:.6px; font-weight:600; color:#777;}
	.form-group input,.form-group select,.form-group textarea{border:1px solid #ddd; border-radius:8px; padding:12px 14px; font-family:inherit; font-size:14px; background:#FFFEFC; transition:border-color .25s, background .25s;}
	.form-group input:focus,.form-group select:focus,.form-group textarea:focus{outline:none; border-color:#FFC94D; background:#fff; box-shadow:0 0 0 3px #FFC94D33;}
	.form-group textarea{min-height:120px; resize:vertical;}
	.form-actions{margin-top:6px;}
	.send-btn{background:#FFC94D; color:#fff; border:none; border-radius:8px; padding:14px 28px; font-weight:600; cursor:pointer; font-size:14px; display:inline-flex; align-items:center; gap:8px;}
	.send-btn:hover{filter:brightness(1.05);}
	.required{color:#E5533D; margin-left:4px;}
	.form-note{font-size:11px; color:#999; margin-top:8px;}
	@media(max-width:650px){.contact-form{padding:28px 26px;} .contact-box{padding:28px 26px;}}
	/* Map */
	.map-box{margin-top:26px; background:#fff; border:1px solid #eee; border-radius:14px; overflow:hidden; box-shadow:0 4px 14px -6px #00000012;}
	.map-box iframe{width:100%; height:270px; border:0; display:block;}
	.map-caption{padding:10px 16px 14px; font-size:12px; color:#666; display:flex; justify-content:space-between; align-items:center; gap:12px;}
	.map-caption a{color:#E6A23C; text-decoration:none; font-weight:600; font-size:12px;}
	.map-caption a:hover{text-decoration:underline;}
	/* Inline editing (admin) */
	[data-editable]{position:relative;}
	body.admin-inline [data-editable]{cursor:text;}
	body.admin-inline [data-editable]:hover{outline:1px dashed #FFB341;}
	body.admin-inline [data-editing]{outline:2px solid #FF9800; background:#FFF7E6; border-radius:4px;}

	/* ===== Sections Services & Equipe ===== */
	.section-wrapper{padding:90px 60px; max-width:1250px; margin:0 auto; font-family:Inter, sans-serif;}
	@media(max-width:900px){.section-wrapper{padding:70px 30px}}
	.section-eyebrow{display:inline-block; background:#FFF1E0; color:#E89A2F; padding:4px 14px; border-radius:30px; font-size:12px; font-weight:600; letter-spacing:.5px;}
	.section-title{font-size:34px; line-height:1.2; margin:14px 0 10px; font-weight:700; color:#222;}
	.section-title span{color:#FFA94D;}
	.section-intro{color:#555; max-width:760px; font-size:15px; line-height:1.55;}
	.services-grid{display:grid; grid-template-columns:repeat(auto-fill,minmax(250px,1fr)); gap:34px 28px; margin-top:48px;}
	.service-card{background:#fff; border:1px solid #eee; border-radius:14px; padding:26px 26px 30px; position:relative; box-shadow:0 4px 14px -6px #0000000d; transition:.35s cubic-bezier(.4,.2,.2,1);}
	.service-card:hover{box-shadow:0 10px 28px -4px #00000020; transform:translateY(-4px);}
	.service-icon{width:44px; height:44px; background:#FFF3D6; border-radius:12px; display:flex; align-items:center; justify-content:center; font-size:22px; color:#E6A23C; margin-bottom:18px;}
	.service-card h3{margin:0 0 12px; font-size:18px; color:#222; font-weight:600;}
	.service-card ul{list-style:none; padding:0; margin:0 0 14px; font-size:13.5px; line-height:1.5; color:#555;}
	.service-card ul li{position:relative; padding-left:16px; margin-bottom:4px;}
	.service-card ul li:before{content:""; position:absolute; left:0; top:8px; width:6px; height:6px; background:#FFC94D; border-radius:50%;}
	.service-meta{font-size:11px; color:#999; font-weight:500; letter-spacing:.5px; text-transform:uppercase;}
	.cta-row{margin-top:40px; display:flex; gap:14px; flex-wrap:wrap;}
	.btn-primary{background:#FFC94D; color:#fff; border:none; border-radius:8px; padding:14px 28px; font-weight:600; cursor:pointer; font-size:14px; display:inline-flex; align-items:center; gap:8px; box-shadow:0 4px 10px -2px #FFC94D66; transition:.3s;}
	.btn-primary:hover{filter:brightness(1.05);}
	.btn-outline{background:#fff; color:#222; border:2px solid #FFC94D; border-radius:8px; padding:12px 24px; font-weight:600; font-size:14px; cursor:pointer; display:inline-flex; align-items:center; gap:8px;}
	/* Uniformiser largeur/alignement des CTA services */
	#services .cta-row #toggle-services,
	#services .cta-row .btn-primary{min-width:260px; justify-content:center;}
	.btn-outline:hover{background:#FFF9ED;}
	/* Equipe */
	.team-layout{display:grid; grid-template-columns:1.2fr 1fr; gap:70px; margin-top:40px; align-items:start;}
	@media(max-width:1050px){.team-layout{grid-template-columns:1fr; gap:50px;}}
	.team-content h3{font-size:22px; margin:10px 0 16px; color:#222;}
	.team-content p{font-size:14.5px; line-height:1.55; color:#555; margin:0 0 20px;}
	.check-list{list-style:none; padding:0; margin:0 0 26px; font-size:14px; color:#444;}
	.check-list li{position:relative; padding-left:30px; margin:0 0 14px;}
	.check-list li:before{content:"✔"; position:absolute; left:0; top:1px; background:#FFE7BF; color:#CB8C1E; font-size:12px; width:20px; height:20px; border-radius:6px; display:flex; align-items:center; justify-content:center; font-weight:600;}
	.stats-grid{display:grid; grid-template-columns:repeat(auto-fill,minmax(120px,1fr)); gap:18px;}
	.stat-box{background:#fff; border:1px solid #eee; border-radius:10px; padding:20px 18px 22px; text-align:center; box-shadow:0 4px 10px -4px #00000014;}
	.stat-icon{width:38px; height:38px; background:#FFF3D6; border-radius:10px; display:flex; align-items:center; justify-content:center; font-size:20px; color:#E6A23C; margin:0 auto 12px;}
	.stat-box strong{display:block; font-size:18px; color:#222;}
	.stat-box span{font-size:11px; color:#777; letter-spacing:.5px; text-transform:uppercase; font-weight:600;}
	.mission-box{grid-column:1/-1; background:#fff; border:1px solid #eee; border-radius:14px; padding:30px 40px; margin-top:14px; text-align:center; box-shadow:0 4px 16px -6px #00000010;}
	.mission-box .mission-icon{width:54px; height:54px; background:#FFF3D6; border-radius:15px; display:flex; align-items:center; justify-content:center; font-size:26px; color:#E6A23C; margin:0 auto 14px;}
	.mission-box h4{margin:0 0 10px; font-size:20px; color:#222;}
	.mission-box p{margin:0; font-size:14px; line-height:1.5; color:#555;}
	@media(max-width:600px){
		.services-grid{grid-template-columns:repeat(auto-fill,minmax(200px,1fr));}
		.mission-box{padding:26px 28px;}
	}

/* ===== Footer ===== */
#footer{position:relative; background:rgba(255,255,255,0.58); backdrop-filter:blur(6px) saturate(1.2); -webkit-backdrop-filter:blur(6px) saturate(1.2); border-top:1px solid #e9dfd2; font-family:Inter, sans-serif; color:#444;}
#footer:before{content:""; position:absolute; inset:0; background:linear-gradient(180deg,rgba(255,255,255,0.65) 0%,rgba(255,255,255,0.40) 60%,rgba(255,255,255,0.55) 100%); pointer-events:none;}
#footer .footer-inner{position:relative; z-index:1;}
#footer .footer-inner{max-width:1250px; margin:0 auto; padding:58px 60px 34px;}
#footer .footer-top{display:grid; grid-template-columns:1.3fr 1fr 1fr 1fr; gap:50px 60px;}
@media(max-width:1100px){#footer .footer-top{grid-template-columns:repeat(auto-fit,minmax(200px,1fr));}}
#footer h5{margin:0 0 18px; font-size:14px; letter-spacing:.6px; text-transform:uppercase; color:#222; font-weight:600;}
#footer .brand-head{display:flex; align-items:center; gap:14px; margin:0 0 14px;}
#footer .logo-badge{background:#FFC94D; color:#fff; font-weight:600; font-size:16px; width:40px; height:40px; border-radius:10px; display:flex; align-items:center; justify-content:center; box-shadow:0 4px 10px -3px #FFC94D66; overflow:hidden;}
#footer .logo-badge img{width:100%;height:100%;object-fit:contain;display:block;border-radius:10px;}
#footer .brand-block h4{margin:0; font-size:15px; font-weight:600; color:#222;}
#footer .brand-block .tagline{font-size:12px; color:#888; font-weight:500;}
#footer .brand-desc{margin:0; font-size:13px; line-height:1.5; max-width:320px;}
#footer .footer-col ul{list-style:none; padding:0; margin:0; display:flex; flex-direction:column; gap:8px; font-size:13px;}
#footer .icon-line{display:flex; align-items:flex-start; gap:10px; line-height:1.4;}
#footer .icon{width:18px; display:inline-flex; justify-content:center; color:#E6A23C; font-size:14px; margin-top:2px;}
#footer .services-list li{margin:0;}
#footer a{color:#444; text-decoration:none;}
#footer a:hover{color:#E6A23C;}
#footer .footer-sep{height:1px; background:#f1e8dc; margin:46px 0 24px;}
#footer .footer-bottom{display:flex; flex-wrap:wrap; align-items:center; justify-content:space-between; gap:20px; font-size:12px; color:#666;}
#footer .legal-links{list-style:none; padding:0; margin:0; display:flex; gap:26px;}
#footer .legal-links a{font-size:12px; color:#666;}
#footer .legal-links a:hover{color:#E6A23C;}
#footer .heart{color:#E5533D;}
@media(max-width:700px){#footer .footer-inner{padding:50px 34px 30px;} #footer .footer-sep{margin:36px 0 20px}}

/* ===== Hero Carrousel ===== */
.hero-carousel{position:relative; min-height:600px; display:flex; align-items:center; font-family:Inter,sans-serif; overflow:hidden;}
.hero-carousel:after{content:""; position:absolute; inset:0; background:linear-gradient(180deg,#00000022 0%,#00000011 40%,#0000 60%);}/* léger overlay global */
.hero-slides{position:absolute; inset:0; margin:0; padding:0;}
.hero-slide{position:absolute; inset:0; background-size:cover; background-position:center; background-repeat:no-repeat; opacity:0; transition:opacity 1.1s ease; will-change:opacity;}
.hero-slide.active{opacity:1;}
.hero-overlay{position:relative; z-index:2; background:rgba(255,255,255,0.85); border-radius:24px; padding:48px 56px; margin-left:120px; max-width:600px; backdrop-filter:saturate(1.3) blur(4px); box-shadow:0 10px 40px -10px #00000025;}
@media(max-width:900px){.hero-overlay{margin:0 40px; padding:42px 44px;}}
@media(max-width:600px){.hero-overlay{margin:0 26px; padding:34px 32px; border-radius:20px;}}
.hero-nav{position:absolute; top:50%; transform:translateY(-50%); z-index:3; background:rgba(255,255,255,.7); border:none; width:46px; height:46px; border-radius:50%; display:flex; align-items:center; justify-content:center; font-size:24px; cursor:pointer; color:#444; box-shadow:0 4px 16px -4px #00000025; backdrop-filter:blur(4px); transition:.3s;}
.hero-nav:hover{background:#FFC94D; color:#fff;}
.hero-nav.prev{left:16px;}
.hero-nav.next{right:16px;}
@media(max-width:800px){.hero-nav{width:40px; height:40px; font-size:20px;}}
@media(max-width:640px){.hero-nav{display:none;}}
.hero-dots{position:absolute; z-index:3; bottom:20px; left:50%; transform:translateX(-50%); display:flex; gap:10px;}
.hero-dot{width:12px; height:12px; border-radius:50%; background:rgba(255,255,255,.55); cursor:pointer; position:relative; overflow:hidden;}
.hero-dot.active{background:#FFC94D; box-shadow:0 0 0 4px #ffffff70;}
.hero-dot button{appearance:none; -webkit-appearance:none; border:none; background:transparent; position:absolute; inset:0; padding:0; cursor:pointer;}
@media(prefers-reduced-motion:reduce){.hero-slide{transition:none;} .hero-dot:before{display:none;}}
/* Bouton détails prestation */
.details-btn{background:#fff; border:1px solid #FFC94D; color:#CB8C1E; font-size:12px; padding:6px 10px; border-radius:20px; cursor:pointer; font-weight:600; margin-top:4px;}
.details-btn:hover{background:#FFF9ED;}
.service-card .full-desc{line-height:1.5;}
</style>
</head>

  <body>
	<?php if(!empty($__debugMode)): ?>
	<div style="position:fixed;bottom:10px;right:10px;z-index:9999;background:#111;color:#eee;padding:10px 14px;border:1px solid #444;border-radius:8px;max-width:340px;font:12px/1.4 monospace; box-shadow:0 4px 14px -4px #000;">
		<div style="font-weight:600;color:#FFC94D;">DEBUG</div>
		<div>PHP <?php echo phpversion(); ?></div>
		<div>DB: <?php echo (isset($pdo)&&$pdo instanceof PDO)? '<span style="color:#4caf50">OK</span>' : '<span style="color:#f44336">FAIL</span>'; ?></div>
		<div>User: <?php echo $currentUser ? htmlspecialchars($currentUser['username']) : 'anon'; ?> (role <?php echo $currentUser? (int)$currentUser['role_id'] : '-'; ?>)</div>
		<div>Prestations: <?php echo count($prestations); ?></div>
		<div>Memory: <?php echo round(memory_get_usage()/1024/1024,2); ?> MB</div>
	</div>
	<?php endif; ?>
			<div id="root">
				<header style="background:#fff; box-shadow:0 2px 8px #0001; display:flex; align-items:center; justify-content:space-between; padding:0 48px; height:70px;">
						<a href="#accueil" aria-label="Aller à l'accueil Néosphère" style="display:flex; align-items:center; text-decoration:none; cursor:pointer;">
							<img src="logo.jpg" alt="Néosphère" style="width:48px; height:48px; object-fit:contain; margin-right:16px; display:block;" loading="lazy">
							<div style="line-height:1.05;">
								<span style="font-size:1.55em; font-weight:600; color:#222; display:block; line-height:1.02; font-family:'Dancing Script', cursive;" data-editable="site_brand_name"><?php echo content_get('site_brand_name','Néosphère'); ?></span>
								<span style="display:block; font-family:'Dancing Script', cursive; font-size:1.05em; margin-top:0; color:#000; line-height:1; white-space:nowrap;" data-editable="header_byline"><?php echo content_get('header_byline','by Lindsay Serkeyn'); ?></span>
							</div>
						</a>
					<?php $labelEspace = $currentUser ? 'Mon espace' : 'Espace membre'; $isAdmin = $currentUser && isset($currentUser['role_id']) && (int)$currentUser['role_id']===1; ?>
					<nav style="display:flex; gap:32px; font-size:1.1em; align-items:center;">
						<a href="#accueil" style="color:#222; text-decoration:none;" data-editable="nav_home"><?php echo content_get('nav_home','Accueil'); ?></a>
						<a href="#services" style="color:#222; text-decoration:none;" data-editable="nav_services"><?php echo content_get('nav_services','Services'); ?></a>
						<a href="#equipe" style="color:#222; text-decoration:none;" data-editable="nav_team"><?php echo content_get('nav_team','Équipe'); ?></a>
						<a href="#contact" style="color:#222; text-decoration:none;" data-editable="nav_contact"><?php echo content_get('nav_contact','Contact'); ?></a>
						<a href="/membre/espace.php" style="color:#222; text-decoration:none; display:flex; align-items:center; gap:4px; font-weight:500;">
							<span>🔐</span>
							<span><?php echo htmlspecialchars($labelEspace); ?></span>
						</a>
						<?php if($isAdmin): ?>
						<a href="/admin/" style="color:#CB8C1E; text-decoration:none; font-weight:600;">Admin</a>
						<?php endif; ?>
					</nav>
					<div style="display:flex; gap:12px; align-items:center;">
						<button style="background:#fff; color:#222; border:1px solid #ddd; font-size:0.95em; display:flex; align-items:center; gap:6px; cursor:pointer; padding:8px 16px; border-radius:6px;" data-editable="header_call_btn"><span style="font-size:1.1em;">📞</span> <?php echo content_get('header_call_btn','Appeler'); ?></button>
						<button style="background:#FFC94D; color:#fff; border:none; font-size:0.95em; display:flex; align-items:center; gap:6px; cursor:pointer; padding:8px 18px; border-radius:6px; font-weight:600;" data-editable="header_rdv_btn"><span style="font-size:1.1em;">📅</span> <?php echo content_get('header_rdv_btn','Rendez-vous'); ?></button>
						<?php if($currentUser): ?>
							<div style="display:flex; gap:8px; align-items:center;">
								<a href="/membre/espace.php" style="background:#fff; border:1px solid #FFC94D; color:#CB8C1E; font-size:0.9em; padding:8px 14px; border-radius:24px; text-decoration:none; font-weight:600;">Espace membre</a>
								<form method="post" action="/membre/logout.php" style="margin:0;">
									<button type="submit" style="background:#FFE7BF; border:1px solid #FFC94D; color:#9A5A00; font-size:0.85em; padding:8px 14px; border-radius:24px; cursor:pointer; font-weight:600;">Déconnexion</button>
								</form>
							</div>
						<?php else: ?>
							<a href="/membre/login.php" style="background:#fff; border:1px solid #FFC94D; color:#CB8C1E; font-size:0.9em; padding:8px 14px; border-radius:24px; text-decoration:none; font-weight:600;">Se connecter</a>
						<?php endif; ?>
					</div>
				</header>
				<div class="hero-carousel" id="hero" aria-label="Carrousel principal présentant Néosphère" data-autoplay="6000">
					<div class="hero-slides" aria-live="off" id="carousel-slides">
					<?php
					// Affichage direct des images du carrousel
					$carouselImages = [];
					try {
						$sql = "SELECT * FROM carousel_images ORDER BY created_at DESC";
						$result = $pdo ? $pdo->query($sql) : false;
						if ($result && $result->rowCount() > 0) {
							while ($row = $result->fetch()) {
								$imgPath = 'admin/carousel_images/' . htmlspecialchars($row['filename']);
								$title = htmlspecialchars($row['title'] ?? $row['filename']);
								$active = count($carouselImages) === 0 ? ' active' : '';
								echo '<div class="hero-slide'.$active.'" role="img" aria-label="'. $title .'" style="background-image:url(\''.$imgPath.'\');"></div>';
								$carouselImages[] = $imgPath;
							}
						}
					} catch (Throwable $e) {
						error_log('[carousel] '.$e->getMessage());
					}
					?>
					</div>
					<button class="hero-nav prev" aria-label="Image précédente" type="button">‹</button>
					<button class="hero-nav next" aria-label="Image suivante" type="button">›</button>
					<div class="hero-dots" role="tablist" aria-label="Sélecteur de diapositives"></div>
					<div class="hero-overlay">
						<div style="display:flex; align-items:center; gap:10px; margin-bottom:18px;">
							<span style="color:#FFC94D; font-size:1.2em;">✔️</span>
							<span style="background:#FFF7E0; color:#E6A23C; padding:6px 18px; border-radius:16px; font-weight:500;" data-editable="hero_badge"><?php echo content_get('hero_badge','Esthétique & bien-être'); ?></span>
						</div>
						<h1 style="font-size:3em; font-weight:bold; color:#222; margin-bottom:0.2em;" data-editable="hero_title"><?php echo content_get('hero_title','Votre santé, <span style="color:#FFA94D;">notre</span><br><span style="color:#F7A1C4;">priorité</span>'); ?></h1>
						<p style="font-size:1.25em; color:#555; margin-bottom:32px;" data-editable="hero_subtitle"><?php echo content_get('hero_subtitle','Un cabinet moderne au cœur de votre bien-être. Nos praticiens expérimentés vous accompagnent avec des soins personnalisés dans un environnement chaleureux.'); ?></p>
						<div style="display:flex; gap:18px; margin-bottom:32px; flex-wrap:wrap;">
							<button style="background:#FFC94D; color:#fff; border:none; font-size:1.1em; font-weight:bold; padding:14px 28px; border-radius:8px; cursor:pointer; display:flex; align-items:center; gap:8px;" data-editable="hero_cta_primary"><?php echo content_get('hero_cta_primary','📅 Prendre rendez-vous'); ?></button>
							<button style="background:#fff; color:#222; border:2px solid #FFC94D; font-size:1.1em; font-weight:bold; padding:14px 28px; border-radius:8px; cursor:pointer; display:flex; align-items:center; gap:8px;" data-editable="hero_cta_secondary"><?php echo content_get('hero_cta_secondary','Découvrir nos services'); ?></button>
						</div>
						<div style="display:flex; gap:48px; margin-top:24px; flex-wrap:wrap;">
							<div style="display:flex; align-items:center; gap:10px;">
								<span style="font-size:2em; color:#FFC94D;">🕒</span>
								<div>
									<span style="font-weight:bold; color:#222;" data-editable="hero_feat1_title"><?php echo content_get('hero_feat1_title','Horaires flexibles'); ?></span><br>
									<span style="color:#888; font-size:0.95em;" data-editable="hero_feat1_sub"><?php echo content_get('hero_feat1_sub','du Lun. au ven.'); ?></span>
								</div>
							</div>
							<div style="display:flex; align-items:center; gap:10px;">
								<span style="font-size:2em; color:#FFC94D;">📍</span>
								<div>
									<span style="font-weight:bold; color:#222;" data-editable="hero_feat2_title"><?php echo content_get('hero_feat2_title','Localisé'); ?></span><br>
									<span style="color:#888; font-size:0.95em;" data-editable="hero_feat2_sub"><?php echo content_get('hero_feat2_sub','Rebecq'); ?></span>
								</div>
							</div>
							<div style="display:flex; align-items:center; gap:10px;">
								<span style="font-size:2em; color:#FFC94D;">🏆</span>
								<div>
									<span style="font-weight:bold; color:#222;" data-editable="hero_feat3_title"><?php echo content_get('hero_feat3_title','Expérience'); ?></span><br>
									<span style="color:#888; font-size:0.95em;" data-editable="hero_feat3_sub"><?php echo content_get('hero_feat3_sub','15+ années'); ?></span>
								</div>
							</div>
						</div>
					</div>
				</div>
			</div>
			<!-- ===== Section Services Médicaux ===== -->
			<section id="services">
				<div class="section-wrapper">
						<div class="services-intro-block">
					<span class="section-eyebrow" data-editable="services_eyebrow"><?php echo content_get('services_eyebrow','Nos Services santé'); ?></span>
					<h2 class="section-title" data-editable="services_title"><?php echo content_get('services_title','Une gamme complète de <span>soins spécialisés</span>'); ?></h2>
					<p class="section-intro" data-editable="services_intro"><?php echo content_get('services_intro','Des consultations de médecine générale aux examens spécialisés, notre cabinet met à votre disposition une équipe pluridisciplinaire expérimentée et des équipements modernes pour un suivi personnalisé.'); ?></p>
					<div class="services-search" style="margin:26px auto 10px; max-width:500px;">
						<label for="service-search" style="display:block; font-size:12px; font-weight:600; letter-spacing:.5px; text-transform:uppercase; color:#777; margin-bottom:6px;" data-editable="services_search_label"><?php echo content_get('services_search_label','Rechercher une prestation'); ?></label>
						<input id="service-search" type="text" placeholder="Tapez pour filtrer (nom, description, durée)..." style="width:100%; padding:12px 16px; border:1px solid #ddd; border-radius:10px; font-size:14px; background:#FFFEFC;" />
						<small id="service-search-count" style="display:block; margin-top:6px; font-size:11px; color:#999;"></small>
					</div>
				</div>
				<div class="services-grid">
				<?php if(!empty($prestations)): ?>
					<?php foreach($prestations as $i=>$p): 
						$hidden = $i>=8 ? ' svc-hidden' : '';
						$nom = htmlspecialchars($p['nom']);
						$duree = $p['duree'] ? htmlspecialchars($p['duree']) : '';
						$fullDesc = $p['description'] ? trim($p['description']) : '';
						$needsMore = false; $shortDesc='';
						if($fullDesc){
							$needsMore = mb_strlen($fullDesc,'UTF-8')>90; 
							$shortDesc = htmlspecialchars(mb_strimwidth($fullDesc,0,90,'…','UTF-8'));
						}
						$fullEsc = $fullDesc ? htmlspecialchars($fullDesc) : '';
						$prix = isset($p['prix_ttc']) ? number_format((float)$p['prix_ttc'],2,',',' ') : '';
					?>
					<div class="service-card<?= $hidden ?>" data-prestation-id="<?= (int)$p['id'] ?>">
						<div class="service-icon"><?= htmlspecialchars($p['emoji'] ?? '🩺') ?></div>
						<h3><?= $nom ?></h3>
						<ul>
							<?php if($duree): ?><li>Durée : <?= $duree ?></li><?php endif; ?>
							<?php if($prix !== ''): ?><li>Prix : <?= $prix ?> €</li><?php endif; ?>
							<?php if($fullDesc): ?>
								<?php if($needsMore): ?>
									<li class="short-desc"><?= $shortDesc ?></li>
									<li class="full-desc" style="display:none;"><?= $fullEsc ?></li>
								<?php else: ?>
									<li class="full-desc"><?= $fullEsc ?></li>
								<?php endif; ?>
							<?php endif; ?>
						</ul>
						<?php if($needsMore): ?><button type="button" class="details-btn" aria-expanded="false">Détails</button><?php endif; ?>
						<a href="agenda/prendre_rdv.php?prestation_id=<?= (int)$p['id'] ?>" class="button is-primary is-small" style="margin-top:10px;">Prendre rendez-vous</a>
						<div class="service-meta"></div>
					</div>
					<?php endforeach; ?>
				<?php else: ?>
				<!-- Fallback statique si base indisponible -->
				<div class="service-card" style="background:#FFF8EE;">
					<div class="service-icon">⚠️</div>
					<h3>Services indisponibles</h3>
					<ul>
						<li>Données non chargées depuis la base.</li>
						<li><strong>Astuce :</strong> ajouter <code>?debug=1</code> à l’URL pour voir les erreurs.</li>
						<li>Vérifiez la connexion MySQL et les tables <code>grids</code>, <code>prestations</code>.</li>
					</ul>
					<div class="service-meta">Base indisponible</div>
				</div>
				<?php endif; ?>
				</div>
				<div class="cta-row center" style="margin-top:34px;">
					<button class="btn-primary" data-editable="services_cta_primary"><?php echo content_get('services_cta_primary','📅 Prendre rendez-vous maintenant'); ?></button>
					<button id="toggle-services" class="btn-outline" data-state="more" data-editable="services_toggle_btn"><?php echo content_get('services_toggle_btn','➕ Afficher plus de soins'); ?></button>
				</div>
		</div>
	</section>


			<!-- ===== Section Equipe & Valeurs ===== -->
			<section id="equipe">
				<div class="section-wrapper">
				<div class="team-layout">
					<div class="team-content">
						<span class="section-eyebrow" data-editable="team_eyebrow"><?php echo content_get('team_eyebrow','Notre Équipe'); ?></span>
						<h2 class="section-title team-title" data-editable="team_title"><?php echo content_get('team_title','Une équipe dévouée à <span>votre bien-être</span>'); ?></h2>
						<p data-editable="team_intro_paragraph"><?php echo content_get('team_intro_paragraph', "Nos médecins et spécialistes sont des professionnels au carnet d'expérience, rigoureusement sélectionnés pour leur expertise et leur sens humain. Nous favorisons une prise en charge globale, coordonnée et pédagogique pour vous accompagner durablement."); ?></p>
						<ul class="check-list">
							<li data-editable="team_intro_bullet_1"><?php echo content_get('team_intro_bullet_1','Prise en charge personnalisée et confidentielle'); ?></li>
							<li data-editable="team_intro_bullet_2"><?php echo content_get('team_intro_bullet_2','Protocoles basés sur les dernières recommandations'); ?></li>
							<li data-editable="team_intro_bullet_3"><?php echo content_get('team_intro_bullet_3','Suivi digitalisé des dossiers sécurisé'); ?></li>
							<li data-editable="team_intro_bullet_4"><?php echo content_get('team_intro_bullet_4','Orientation rapide vers des spécialistes référencés'); ?></li>
						</ul>
						<a class="btn-primary" href="https://wa.me/32479746112?text=<?php echo rawurlencode(content_get('whatsapp_default_message','Bonjour Néosphère, je souhaite obtenir un renseignement.')); ?>" target="_blank" rel="noopener" style="text-decoration:none; display:inline-flex; align-items:center; gap:8px;" data-editable="team_button_contact_whatsapp"><?php echo content_get('team_button_contact_whatsapp','💬 Contactez le cabinet'); ?></a>
					</div>
					<div>
						<div class="stats-grid">
							<div class="stat-box">
								<div class="stat-icon">👨‍⚕️</div>
								<strong data-editable="team_stat1_number"><?php echo content_get('team_stat1_number','5000+'); ?></strong>
								<span data-editable="team_stat1_label"><?php echo content_get('team_stat1_label','Patients suivis'); ?></span>
							</div>
							<div class="stat-box">
								<div class="stat-icon">🏆</div>
								<strong data-editable="team_stat2_number"><?php echo content_get('team_stat2_number','15+'); ?></strong>
								<span data-editable="team_stat2_label"><?php echo content_get('team_stat2_label','Ans d\'expérience'); ?></span>
							</div>
							<div class="stat-box">
								<div class="stat-icon">⏱️</div>
								<strong data-editable="team_stat3_number"><?php echo content_get('team_stat3_number','24/7'); ?></strong>
								<span data-editable="team_stat3_label"><?php echo content_get('team_stat3_label','Assistance'); ?></span>
							</div>
							<div class="stat-box">
								<div class="stat-icon">✅</div>
								<strong data-editable="team_stat4_number"><?php echo content_get('team_stat4_number','98%'); ?></strong>
								<span data-editable="team_stat4_label"><?php echo content_get('team_stat4_label','Satisfaction'); ?></span>
							</div>
							<div class="mission-box">
								<div class="mission-icon">🎯</div>
								<h4 data-editable="team_mission_title"><?php echo content_get('team_mission_title','Notre Mission'); ?></h4>
								<p data-editable="team_mission_text"><?php echo content_get('team_mission_text','Accompagner chaque patient avec rigueur scientifique et empathie, en créant un lien de confiance durable et en valorisant la prévention autant que le traitement.'); ?></p>
							</div>
						</div>
					</div>
				</div>
				</div>
			</section>
			</section>


				<!-- ===== Section Contact ===== -->
				<section id="contact" class="section-wrapper">
					<div class="contact-inner">
						<div class="contact-intro-block" style="text-align:center; max-width:760px; margin:0 auto 10px;">
							<span class="section-eyebrow" data-editable="contact_eyebrow"><?php echo content_get('contact_eyebrow','Contactez-nous'); ?></span>
							<h2 class="section-title" data-editable="contact_title"><?php echo content_get('contact_title','Notre équipe est à votre <span>écoute</span>'); ?></h2>
							<p class="section-intro" data-editable="contact_intro"><?php echo content_get('contact_intro','Disponible pour répondre à vos questions, planifier un rendez-vous ou vous orienter vers le bon accompagnement.'); ?></p>
						</div>
					<div class="contact-grid">
						<div>
							<div class="contact-box">
								<h3 data-editable="contact_box_title"><?php echo content_get('contact_box_title','Informations de contact'); ?></h3>
								<ul class="info-list">
									<li class="info-item"><div class="info-ico">📍</div><div class="info-desc"><span class="lbl">Adresse</span><span class="val" id="contact-address" data-editable="contact_address"><?php echo content_get('contact_address', '83, rue mayeur habil<br>1400 Rebecq, Belgique'); ?></span></div></li>
									<li class="info-item"><div class="info-ico">📞</div><div class="info-desc"><span class="lbl">Téléphone</span><span class="val" data-editable="contact_phone"><?php echo content_get('contact_phone', '+32 (0) 479.74.61.12'); ?></span></div></li>
									<li class="info-item"><div class="info-ico">✉️</div><div class="info-desc"><span class="lbl">Email</span><span class="val" data-editable="contact_email"><?php echo content_get('contact_email', 'contact@neosphere-ls.be'); ?></span></div></li>
									<li class="info-item"><div class="info-ico">🕒</div><div class="info-desc"><span class="lbl">Horaires</span><span class="val" data-editable="contact_hours"><?php echo content_get('contact_hours', 'Lun - Ven : 09h00 - 17h00<br>Adaptable a la demande'); ?></span></div></li>
								</ul>
							</div>
							<div class="map-box">
								<iframe title="Localisation Néosphère" id="contact-map" loading="lazy" src="about:blank"></iframe>
								<div class="map-caption"><span><?php echo content_get('map_caption_prefix','Néosphère – '); ?><?php echo strip_tags(content_get('contact_address', '83, rue mayeur habil<br>1400 Rebecq, Belgique')); ?></span><a target="_blank" rel="noopener" href="https://www.google.com/maps?q=100+Avenue+du+Sant%C3%A9,Bruxelles,Belgique" data-editable="map_open_link_text"><?php echo content_get('map_open_link_text','Ouvrir dans Maps →'); ?></a></div>
							</div>
							<!-- quick-actions déplacé sous le formulaire -->
						</div>
						<div class="contact-form">
							<h3 data-editable="contact_form_title"><?php echo content_get('contact_form_title','Envoyez-nous un message'); ?></h3>
							<form id="contact-form">
								<div class="form-row">
									<div class="form-group">
										<label data-editable="contact_form_label_name"><?php echo content_get('contact_form_label_name','Nom complet'); ?><span class="required">*</span></label>
										<input type="text" name="name" required placeholder="Votre nom complet">
									</div>
									<div class="form-group">
										<label data-editable="contact_form_label_email"><?php echo content_get('contact_form_label_email','Email'); ?><span class="required">*</span></label>
										<input type="email" name="email" required placeholder="vous@mail.com">
									</div>
									<div class="form-group">
										<label data-editable="contact_form_label_phone"><?php echo content_get('contact_form_label_phone','Téléphone'); ?></label>
										<input type="tel" name="phone" placeholder="+32 (0) 479.74.61.12">
									</div>
									<div class="form-group">
										<label data-editable="contact_form_label_type"><?php echo content_get('contact_form_label_type','Type de demande'); ?></label>
										<select name="type">
											<option value="" data-editable="contact_form_option_placeholder"><?php echo content_get('contact_form_option_placeholder','Préciser le motif'); ?></option>
											<option data-editable="contact_form_option_rdv"><?php echo content_get('contact_form_option_rdv','Rendez-vous'); ?></option>
											<option data-editable="contact_form_option_question"><?php echo content_get('contact_form_option_question','Questions'); ?></option>
											<option data-editable="contact_form_option_results"><?php echo content_get('contact_form_option_results','Résultats'); ?></option>
											<option data-editable="contact_form_option_other"><?php echo content_get('contact_form_option_other','Autre'); ?></option>
										</select>
									</div>
								</div>
								<div class="form-group">
									<label data-editable="contact_form_label_message"><?php echo content_get('contact_form_label_message','Message'); ?><span class="required">*</span></label>
									<textarea name="message" required placeholder="Décrivez votre demande ou vos symptômes..."></textarea>
								</div>
								<div class="form-actions">
									<button class="send-btn" type="submit" data-editable="contact_form_submit"><?php echo content_get('contact_form_submit','📨 Envoyer le message'); ?></button>
									<div class="form-note" data-editable="contact_form_note"><?php echo content_get('contact_form_note','* Tous les champs obligatoires sont nécessaires pour un meilleur traitement.'); ?></div>
								</div>
								<div class="quick-actions">
									<button class="action-btn" data-editable="contact_quick_rdv_btn"><?php echo content_get('contact_quick_rdv_btn','Prendre rendez-vous'); ?></button>
									<a class="action-btn" href="https://wa.me/32479746112?text=<?php echo rawurlencode(content_get('whatsapp_default_message','Bonjour Néosphère, je souhaite obtenir un renseignement.')); ?>" target="_blank" rel="noopener" data-editable="contact_quick_call_btn"><?php echo content_get('contact_quick_call_btn','💬 Message WhatsApp'); ?></a>
								</div>
							</form>
						</div>
					</div>
				</div>
				</section>
				</section>

				<!-- ===== Footer (bas de page) ===== -->
				<footer id="footer">
					<div class="footer-inner">
						<div class="footer-top">
							<div class="footer-col">
								<div class="brand-block">
										<div class="brand-head">
											<div class="logo-badge" style="background:transparent; box-shadow:none; padding:0;">
												<img src="logo.jpg" alt="Néosphère" loading="lazy">
											</div>
											<div>
												<h4 data-editable="footer_brand_name"><?php echo content_get('footer_brand_name','Néosphère'); ?></h4>
												<div class="tagline" data-editable="footer_tagline"><?php echo content_get('footer_tagline','Esthétique & bien-être'); ?></div>
											</div>
										</div>
	<p class="brand-desc" data-editable="footer_brand_desc"><?php echo content_get('footer_brand_desc', 'Votre partenaire santé et bien-être à Rebecq. Néosphère vous propose des soins modernes, personnalisés et accessibles, pour toute la famille'); ?></p>
								</div>
							</div>
							<div class="footer-col">
								<h5 data-editable="footer_col_contact_title"><?php echo content_get('footer_col_contact_title','Contact'); ?></h5>
								<ul>
									<li class="icon-line"><span class="icon">📍</span><span data-editable="contact_address"><?php echo content_get('contact_address', '83 rue mayeur habil<br>1400 Rebecq, Belgique'); ?></span></li>
									<li class="icon-line"><span class="icon">📞</span><span data-editable="contact_phone"><?php echo content_get('contact_phone', '+32 (0) 479.74.61.12'); ?></span></li>
									<li class="icon-line"><span class="icon">✉️</span><span data-editable="contact_email"><?php echo content_get('contact_email', 'contact@neosphere-ls.be'); ?></span></li>
								</ul>
							</div>
								<div class="footer-col">
									<h5 data-editable="footer_col_hours_title"><?php echo content_get('footer_col_hours_title','Horaires'); ?></h5>
									<ul>
										<li class="icon-line"><span class="icon">⏰</span><span data-editable="contact_hours"><?php echo content_get('contact_hours', 'Lun - Ven: 09h00 - 17h00<br>adaptable à la demande'); ?></span></li>
									</ul>
								</div>
							<div class="footer-col">
								<h5 data-editable="footer_col_services_title"><?php echo content_get('footer_col_services_title','Services'); ?></h5>
								<ul class="services-list">
								<?php
								// Sélection aléatoire de 6 prestations (ou moins si disponible) pour le footer
								$footerNames = [];
								if(!empty($prestations) && is_array($prestations)){
									$pool = $prestations;
									shuffle($pool);
									$max = min(6, count($pool));
									for($i=0;$i<$max;$i++){
											$name = trim(isset($pool[$i]['nom']) ? $pool[$i]['nom'] : '');
										if($name!=='') $footerNames[] = $name;
									}
								}
								if(!$footerNames){
									$footerNames = ['Soins personnalisés','Suivi','Prévention','Bien-être','Diagnostic','Accompagnement'];
								}
								foreach($footerNames as $fn){
									echo '<li>'.htmlspecialchars($fn,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8').'</li>';
								}
								?>
								</ul>
							</div>
						</div>
						<div class="footer-sep"></div>
						<div class="footer-bottom">
							<div data-editable="footer_copyright"><?php echo content_get('footer_copyright','&copy; 2024 Néosphère. Fait avec <span class="heart">❤</span> pour votre santé.'); ?></div>
							<ul class="legal-links">
								<li><a href="#" data-editable="footer_legal_mentions"><?php echo content_get('footer_legal_mentions','Mentions légales'); ?></a></li>
								<li><a href="#" data-editable="footer_legal_confidentialite"><?php echo content_get('footer_legal_confidentialite','Confidentialité'); ?></a></li>
								<li><a href="#" data-editable="footer_legal_cgu"><?php echo content_get('footer_legal_cgu','CGU'); ?></a></li>
							</ul>
						</div>
					</div>
				</footer>
  


<mask id="mask0_19703_15608" style="mask-type:alpha" maskUnits="userSpaceOnUse" x="0" y="0" width="20" height="21">
<path fill-rule="evenodd" clip-rule="evenodd" d="M5.90405 0.885124C9.16477 0.885124 11.8081 3.53543 11.8081 6.80474V9.05456H13.773C17.0337 9.05456 19.677 11.7049 19.677 14.9742C19.677 18.2435 17.0337 20.8938 13.773 20.8938H0V6.80474C0 3.53543 2.64333 0.885124 5.90405 0.885124Z" fill="url(#paint0_linear_19703_15608)"/>
</mask>
<g mask="url(#mask0_19703_15608)">
<g filter="url(#filter0_f_19703_15608)">
<circle cx="8.63157" cy="11.5658" r="13.3199" fill="#4B73FF"/>
</g>
<g filter="url(#filter1_f_19703_15608)">
<ellipse cx="10.0949" cy="4.25612" rx="17.0591" ry="13.3199" fill="#FF66F4"/>
</g>
<g filter="url(#filter2_f_19703_15608)">
<ellipse cx="12.8775" cy="1.74957" rx="13.3199" ry="11.6977" fill="#FF0105"/>
</g>
<g filter="url(#filter3_f_19703_15608)">
<circle cx="10.3319" cy="4.25254" r="8.01052" fill="#FE7B02"/>
</g>
</g>
<defs>
<filter id="filter0_f_19703_15608" x="-10.6577" y="-7.72354" width="38.5786" height="38.5786" filterUnits="userSpaceOnUse" color-interpolation-filters="sRGB">
<feFlood flood-opacity="0" result="BackgroundImageFix"/>
<feBlend mode="normal" in="SourceGraphic" in2="BackgroundImageFix" result="shape"/>
<feGaussianBlur stdDeviation="2.98472" result="effect1_foregroundBlur_19703_15608"/>
</filter>
<filter id="filter1_f_19703_15608" x="-12.9337" y="-15.0332" width="46.057" height="38.5786" filterUnits="userSpaceOnUse" color-interpolation-filters="sRGB">
<feFlood flood-opacity="0" result="BackgroundImageFix"/>
<feBlend mode="normal" in="SourceGraphic" in2="BackgroundImageFix" result="shape"/>
<feGaussianBlur stdDeviation="2.98472" result="effect1_foregroundBlur_19703_15608"/>
</filter>
<filter id="filter2_f_19703_15608" x="-6.41182" y="-15.9176" width="38.5786" height="35.3342" filterUnits="userSpaceOnUse" color-interpolation-filters="sRGB">
<feFlood flood-opacity="0" result="BackgroundImageFix"/>
<feBlend mode="normal" in="SourceGraphic" in2="BackgroundImageFix" result="shape"/>
<feGaussianBlur stdDeviation="2.98472" result="effect1_foregroundBlur_19703_15608"/>
</filter>
<filter id="filter3_f_19703_15608" x="-3.64803" y="-9.72742" width="27.9599" height="27.9599" filterUnits="userSpaceOnUse" color-interpolation-filters="sRGB">
<feFlood flood-opacity="0" result="BackgroundImageFix"/>
<feBlend mode="normal" in="SourceGraphic" in2="BackgroundImageFix" result="shape"/>
<feGaussianBlur stdDeviation="2.98472" result="effect1_foregroundBlur_19703_15608"/>
</filter>
<linearGradient id="paint0_linear_19703_15608" x1="6.62168" y1="4.40129" x2="12.6165" y2="20.8863" gradientUnits="userSpaceOnUse">
<stop offset="0.025" stop-color="#FF8E63"/>
<stop offset="0.56" stop-color="#FF7EB0"/>
<stop offset="0.95" stop-color="#4B73FF"/>
</linearGradient>
</defs>
</svg>
</body>
<script>
	(function(){
		const grid=document.querySelector('#services .services-grid');
		const btn=document.getElementById('toggle-services');
		if(!grid||!btn) return;
		const cards=[...grid.querySelectorAll('.service-card')];
		const PAGE=8; // nombre par lot
		let shown=8; // déjà visibles par design (les autres ont .svc-hidden)
		function updateButton(){
			const remaining=cards.length-shown;
			if(remaining<=0){
				btn.innerHTML='➖ Réduire la liste';
				btn.dataset.state='less';
			}else{
				btn.innerHTML='➕ Afficher plus de soins ('+Math.min(PAGE,remaining)+')';
				btn.dataset.state='more';
			}
		}
		updateButton();
		btn.addEventListener('click',()=>{
			if(btn.dataset.state==='more'){
				shown=Math.min(shown+PAGE,cards.length);
				cards.slice(0,shown).forEach(c=>c.classList.remove('svc-hidden'));
				updateButton();
			}else{
				// réduire: masquer tout au-delà des 8 de base
				shown=8;
				cards.slice(8).forEach(c=>c.classList.add('svc-hidden'));
				updateButton();
			}
		});
	})();

// Génération dynamique de la carte (après que le DOM soit prêt)
(function generateContactMap(){
	const addrEl=document.getElementById('contact-address');
	const mapIframe=document.getElementById('contact-map');
	if(!addrEl||!mapIframe) return;
	let html=addrEl.innerHTML||'';
	if(!html.trim()) return;
	html=html.replace(/<br\s*\/?>(\s)*/gi, ', ');
	const tmp=document.createElement('div');
	tmp.innerHTML=html;
	let text=(tmp.textContent||'').replace(/\s+/g,' ').trim();
	text=text.replace(/\s*,\s*/g, ', ').replace(/,{2,}/g, ',').replace(/,\s*$/,'');
	if(!text) return;
	const encoded=encodeURIComponent(text);
	mapIframe.src='https://www.google.com/maps?q='+encoded+'&output=embed';
})();

// ===== Carrousel Hero =====
(function heroCarousel(){
	const root=document.getElementById('hero');
	if(!root) return;
	const slides=[...root.querySelectorAll('.hero-slide')];
	const dotsContainer=root.querySelector('.hero-dots');
	const prevBtn=root.querySelector('.hero-nav.prev');
	const nextBtn=root.querySelector('.hero-nav.next');
	let index=0; let timer=null; const delay=parseInt(root.dataset.autoplay||'7000',10);

	function go(i, user){
		if(!slides.length) return;
		const old=index; index=(i+slides.length)%slides.length;
		if(old===index) return;
		slides[old].classList.remove('active');
		slides[index].classList.add('active');
		updateDots();
		if(user) restart();
	}
	function updateDots(){
		const dots=[...dotsContainer.children];
		dots.forEach((d,i)=>{
			if(i===index){d.classList.add('active'); d.setAttribute('aria-selected','true'); d.setAttribute('tabindex','0');}
			else {d.classList.remove('active'); d.setAttribute('aria-selected','false'); d.setAttribute('tabindex','-1');}
		});
	}
	function next(user){go(index+1,user);} function prev(user){go(index-1,user);}
	function restart(){ if(timer){clearInterval(timer);} if(!prefersReduce){ timer=setInterval(()=>next(false), delay); } }
	const prefersReduce=window.matchMedia('(prefers-reduced-motion: reduce)').matches;

	// Build dots
	slides.forEach((_,i)=>{
		const dot=document.createElement('div');
		dot.className='hero-dot'+(i===0?' active':'');
		dot.role='tab';
		dot.setAttribute('aria-label','Aller à la diapositive '+(i+1));
		const btn=document.createElement('button');
		btn.type='button';
		btn.addEventListener('click',()=>go(i,true));
		dot.appendChild(btn);
		dotsContainer.appendChild(dot);
	});
	updateDots();
	prevBtn&&prevBtn.addEventListener('click',()=>prev(true));
	nextBtn&&nextBtn.addEventListener('click',()=>next(true));
	root.addEventListener('keydown',e=>{
		if(e.key==='ArrowRight'){next(true);}
		else if(e.key==='ArrowLeft'){prev(true);}
	});
	root.addEventListener('pointerenter',()=>{if(timer) clearInterval(timer);});
	root.addEventListener('pointerleave',()=>restart());
	restart();
})();

// ===== Inline editing admin (double clic) =====
(function(){
	const isAdmin = <?php echo json_encode($isAdminInline ?? false); ?>;
	const CSRF_TOKEN = <?php echo json_encode($_SESSION['csrf_token'] ?? ''); ?>;
	if(!isAdmin) return;
	document.body.classList.add('admin-inline');
	let saving=false;
	function flash(msg,err){ const n=document.createElement('div'); n.textContent=msg; n.style.cssText='position:fixed;bottom:18px;right:18px;padding:10px 16px;font:12px/1.35 Inter,Arial,sans-serif;border-radius:6px;font-weight:600;box-shadow:0 4px 14px -6px #00000040;z-index:9999;'+(err?'background:#D9534F;color:#fff;':'background:#2e7d32;color:#fff;'); document.body.appendChild(n); setTimeout(()=>n.remove(),2500);} 
	async function save(slug, html){ if(saving) return; saving=true; try{ const fd=new FormData(); fd.append('slug',slug); fd.append('content',html); fd.append('csrf',CSRF_TOKEN); const r=await fetch('admin/save_block.php',{method:'POST',body:fd,credentials:'same-origin'}); const j=await r.json(); if(!j.success) throw new Error(j.error||'Erreur'); flash('Sauvegardé'); } catch(e){ flash(e.message,true); } finally{ saving=false; } }
	document.querySelectorAll('[data-editable]').forEach(el=>{
		el.addEventListener('dblclick',()=>{
			if(el.dataset.editing) return;
			el.dataset.editing='1';
			el.setAttribute('contenteditable','true');
			el.focus();
		});
		el.addEventListener('keydown',e=>{
			if(e.key==='Escape'){ el.removeAttribute('contenteditable'); delete el.dataset.editing; el.blur(); }
			if(e.key==='s' && (e.ctrlKey||e.metaKey)){ e.preventDefault(); save(el.dataset.editable, el.innerHTML.trim()); }
		});
		el.addEventListener('blur',()=>{ if(el.dataset.editing){ save(el.dataset.editable, el.innerHTML.trim()); el.removeAttribute('contenteditable'); delete el.dataset.editing; } });
	});
})();
// Toggle détails prestations
(function prestationDetails(){
	const cards=document.querySelectorAll('.service-card');
	cards.forEach(card=>{
		const btn=card.querySelector('.details-btn');
		if(!btn) return;
		const shortEl=card.querySelector('.short-desc');
		const fullEl=card.querySelector('.full-desc');
		btn.addEventListener('click',()=>{
			const expanded=btn.getAttribute('aria-expanded')==='true';
			if(expanded){
				btn.setAttribute('aria-expanded','false');
				btn.textContent='Détails';
				if(shortEl) shortEl.style.display='list-item';
				if(fullEl) fullEl.style.display='none';
			}else{
				btn.setAttribute('aria-expanded','true');
				btn.textContent='Réduire';
				if(shortEl) shortEl.style.display='none';
				if(fullEl) fullEl.style.display='list-item';
			}
		});
	});
})();
// Menu utilisateur (toggle)
(function userMenu(){
	const btn=document.getElementById('user-menu-toggle');
	const menu=document.getElementById('user-menu');
	if(!btn||!menu) return;
	function close(){menu.style.display='none'; btn.setAttribute('aria-expanded','false');}
	btn.addEventListener('click',e=>{
		e.preventDefault();
		const open=menu.style.display==='block';
		if(open) close(); else {menu.style.display='block'; btn.setAttribute('aria-expanded','true');}
	});
	document.addEventListener('click',e=>{ if(!menu.contains(e.target)&&e.target!==btn){ close(); }});
	window.addEventListener('keydown',e=>{ if(e.key==='Escape') close(); });
})();
// Recherche live prestations
(function liveSearchPrestations(){
	const input=document.getElementById('service-search');
	if(!input) return;
	const countEl=document.getElementById('service-search-count');
	const grid=document.querySelector('#services .services-grid');
	if(!grid) return;
	const cards=[...grid.querySelectorAll('.service-card')];
	function normalize(s){return (s||'').toString().toLowerCase();}
	function update(){
		const q=normalize(input.value.trim());
		let shown=0; if(!q){
			cards.forEach((c,i)=>{c.style.display='';});
			shown=cards.length;
		} else {
			cards.forEach(c=>{
				const text=normalize(c.innerText);
				if(text.indexOf(q)!==-1){c.style.display=''; shown++;} else {c.style.display='none';}
			});
		}
		if(countEl){
			countEl.textContent = shown+ ' prestation'+(shown>1?'s':'')+ (q? " trouvée"+(shown>1?'s':'')+" pour \""+input.value+"\"":" affichée"+(shown>1?'s':''));
		}
	}
	input.addEventListener('input',()=>{update();});
	update();
})();

// Soumission AJAX du formulaire de contact vers contact_submit.php (racine)
(function contactAjax(){
	const form=document.getElementById('contact-form');
	if(!form) return;
	let status=form.querySelector('.contact-status');
	if(!status){
		status=document.createElement('div');
		status.className='contact-status';
		status.style.marginTop='10px';
		status.style.fontSize='0.85rem';
		form.appendChild(status);
	}
	function show(msg,type){
		status.textContent=msg;
		status.style.color=(type==='error')?'#c0392b':'#2e7d32';
	}
	form.addEventListener('submit',async e=>{
		e.preventDefault();
		const btn=form.querySelector('.send-btn');
		if(btn){btn.disabled=true; btn.textContent='Envoi...';}
		show('', '');
		try{
			const formData=new FormData(form);
			const endpoint = (window.location.pathname.indexOf('/neov2/')===0)? '/neov2/contact_submit.php' : '/contact_submit.php';
			const resp=await fetch(endpoint,{method:'POST', body:formData, headers:{'X-Requested-With':'XMLHttpRequest','Accept':'application/json'}});
			const text=await resp.text();
			let data;
			try { data=JSON.parse(text); }
			catch(parseErr){
				console.warn('Réponse brute non JSON:', text);
				show('Réponse serveur invalide','error');
				if(btn){btn.disabled=false; btn.textContent='📨 Envoyer le message';}
				return;
			}
			if(data.ok){
				show('Message envoyé. Merci !','success');
				form.reset();
				if(btn) btn.textContent='Envoyé ✔';
			} else {
				show(data.error||'Échec de l\'envoi','error');
				if(btn){btn.disabled=false; btn.textContent='📨 Envoyer le message';}
			}
		}catch(err){
			show('Erreur réseau: '+err.message,'error');
			if(btn){btn.disabled=false; btn.textContent='📨 Envoyer le message';}
		}
	});
})();
</script>
</html>
