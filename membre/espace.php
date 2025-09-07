<?php
// Debug: afficher toutes les erreurs pour le développement
@ini_set('display_errors', '1');
@ini_set('display_startup_errors', '1');
error_reporting(E_ALL);

session_start();
if (!isset($_SESSION['user'])) {
    header('Location: login.php');
    exit;
}
// Ne pas rediriger automatiquement les admins vers /admin : afficher l'espace membre avec un lien Administration
?>
<!DOCTYPE html>
<html lang="fr">
<head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>Espace membre</title>
        <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bulma@0.9.4/css/bulma.min.css">
        <link rel="stylesheet" href="../style/index-style.css">
    <style>
        /* Fond plein page et centrage de la box membre */
        body {
            background-image: url('../fond.jpg');
            background-repeat: no-repeat;
            background-position: center center;
            background-size: cover;
            background-attachment: fixed;
        }
        .auth-container { min-height: 100vh; display:flex; align-items:center; justify-content:center; padding:2rem; }
        .auth-box { width:100%; max-width:640px; box-shadow:0 6px 18px rgba(0,0,0,0.12); background: rgba(255,255,255,0.95); }
    .buttons.column{display:flex; flex-direction:column; gap:12px; align-items:stretch;}
    .buttons.column a.button, .buttons.column a, .buttons.column form button{width:100%; justify-content:center;}
    </style>
</head>
<body>
    <?php include_once __DIR__ . '/../menu.php'; ?>
    <div class="auth-container">
        <!-- Wrapper permettant de positionner le logo par-dessus la card -->
        <div class="box-wrap">
            <div class="logo-wrapper">
                <img src="../logo.jpg" alt="Logo" class="member-logo" />
            </div>
            <div class="box auth-box">
                <h2 class="title is-4">Bienvenue, <?php echo htmlspecialchars($_SESSION['user']); ?> !</h2>
            <p class="content">Vous êtes connecté à l'espace membre.</p>
            <div class="buttons column">
                <?php if ((isset($_SESSION['is_admin']) && $_SESSION['is_admin']) || (isset($_SESSION['role']) && $_SESSION['role']==='admin') || (isset($_SESSION['user']) && $_SESSION['user']==='admin')): ?>
                    <a href="../admin/index.php" class="button is-link">Administration</a>
                    <a href="../admin/services.php" class="button is-warning">Services / Créneaux</a>
                <?php endif; ?>
                <a href="../agenda/prendre_rdv.php" class="button is-primary">Prendre un rendez-vous</a>
                <a href="../agenda/mes_rendezvous.php" class="button is-info">Mes rendez-vous</a>
                <a href="../index.php" class="button">Accueil</a>
                <a href="logout.php" class="button is-danger">Déconnexion</a>
            </div>
        </div>
    </div>
</body>
</html>
