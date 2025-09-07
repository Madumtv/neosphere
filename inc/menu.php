<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__.'/content.php';
require_once __DIR__.'/db.php';
// Reconstitution utilisateur (même logique que index.php)
$currentUser = null;
if (!empty($_SESSION['user_id']) && isset($pdo) && $pdo instanceof PDO) {
	try {
		$stmtU = $pdo->prepare('SELECT id, username, pseudo, email, role_id FROM users WHERE id = ? LIMIT 1');
		$stmtU->execute([$_SESSION['user_id']]);
		$currentUser = $stmtU->fetch();
	} catch (Throwable $e) { }
} elseif (!empty($_SESSION['user'])) {
	$currentUser = [
		'id' => null,
		'username' => $_SESSION['user'],
		'pseudo' => $_SESSION['user'],
		'email' => null,
		'role_id' => isset($_SESSION['role_id']) ? (int)$_SESSION['role_id'] : null,
	];
}
$labelEspace = $currentUser ? 'Mon espace' : 'Espace membre';
$isAdmin = $currentUser && isset($currentUser['role_id']) && (int)$currentUser['role_id'] === 1;
?>
<header style="background:#fff; box-shadow:0 2px 8px #0001; display:flex; align-items:center; justify-content:space-between; padding:0 32px; height:70px; position:sticky; top:0; left:0; right:0; z-index:1000; width:100%; box-sizing:border-box;">
	<a href="#accueil" aria-label="Aller à l'accueil Néosphère" style="display:flex; align-items:center; text-decoration:none; cursor:pointer;">
		<img src="/logo.jpg" alt="Néosphère" style="width:48px; height:48px; object-fit:contain; margin-right:16px; display:block;" loading="lazy">
		<div style="line-height:1.05;">
			<span style="font-size:1.55em; font-weight:600; color:#222; display:block; line-height:1.02; font-family:'Dancing Script', cursive;" data-editable="site_brand_name"><?php echo content_get('site_brand_name','Néosphère'); ?></span>
			<span style="display:block; font-family:'Dancing Script', cursive; font-size:1.05em; margin-top:0; color:#000; line-height:1; white-space:nowrap;" data-editable="header_byline"><?php echo content_get('header_byline','by Lindsay Serkeyn'); ?></span>
		</div>
	</a>
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
	<div style="width:180px; min-width:120px; display:flex; gap:12px; align-items:center; justify-content:flex-end;"></div>
	<div style="display:flex; gap:12px; align-items:center;">
		<button style="background:#fff; color:#222; border:1px solid #ddd; font-size:0.95em; display:flex; align-items:center; gap:6px; cursor:pointer; padding:8px 16px; border-radius:6px;" data-editable="header_call_btn"><span style="font-size:1.1em;">📞</span> <?php echo content_get('header_call_btn','Appeler'); ?></button>
		<a href="/agenda/prendre_rdv.php" style="background:#FFC94D; color:#fff; border:none; font-size:0.95em; display:flex; align-items:center; gap:6px; cursor:pointer; padding:8px 18px; border-radius:6px; font-weight:600; text-decoration:none;" data-editable="header_rdv_btn"><span style="font-size:1.1em;">📅</span> <?php echo content_get('header_rdv_btn','Rendez-vous'); ?></a>
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
