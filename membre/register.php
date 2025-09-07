<?php
// Debug: afficher toutes les erreurs pour le développement
@ini_set('display_errors', '1');
@ini_set('display_startup_errors', '1');
error_reporting(E_ALL);

$error = '';
$success = '';
if (isset($_POST['register'])) {
    try {
        // utiliser la connexion centralisée
        require_once __DIR__ . '/../admin/inc/db.php';

        // helper pour trouver la colonne mot de passe
        $getPasswordColumn = function(PDO $pdo) {
            $candidates = ['password', 'passwd', 'password_hash', 'pass', 'pwd'];
            $cols = [];
            $stmt = $pdo->query("DESCRIBE users");
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
            foreach ($rows as $r) {
                if (isset($r['Field'])) $cols[] = $r['Field'];
            }
            foreach ($candidates as $c) {
                if (in_array($c, $cols)) return $c;
            }
            return null;
        };

        $pwdCol = $getPasswordColumn($pdo);
        if (!$pwdCol) {
            throw new Exception('Colonne de mot de passe introuvable dans la table users');
        }

        $stmt = $pdo->prepare('SELECT * FROM users WHERE username = ?');
        $stmt->execute([$_POST['username']]);
        if ($stmt->fetch()) {
            $error = "Nom d'utilisateur déjà pris";
        } else {
            // sécuriser le mot de passe avec password_hash
            $hash = password_hash($_POST['password'], PASSWORD_DEFAULT);
            $sql = "INSERT INTO users (username, {$pwdCol}) VALUES (?, ?)";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$_POST['username'], $hash]);
            $success = 'Compte créé ! <a href="login.php">Connectez-vous</a>';
        }
    } catch (Exception $e) {
        // Affiche une erreur lisible sans interrompre l'exécution
        $error = 'Erreur base de données : ' . $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>Inscription membre</title>
        <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bulma@0.9.4/css/bulma.min.css">
        <link rel="stylesheet" href="../style/index-style.css">
    <style>
        /* Fond plein page et centrage du formulaire */
        body {
            background-image: url('../fond.jpg');
            background-repeat: no-repeat;
            background-position: center center;
            background-size: cover;
            background-attachment: fixed;
        }
        .auth-container { min-height: 100vh; display:flex; align-items:center; justify-content:center; padding:2rem; }
        .auth-box { width:100%; max-width:480px; box-shadow:0 6px 18px rgba(0,0,0,0.12); background: rgba(255,255,255,0.95); }
        .mb-2 { margin-bottom: 0.75rem; }
    </style>
</head>
<body>
    <?php include_once __DIR__ . '/../menu.php'; ?>
<div class="auth-container">
    <div class="box-wrap">
        <div class="logo-wrapper">
            <img src="../logo.jpg" alt="Logo" class="member-logo" />
        </div>
        <div class="box auth-box">
                    <h2 class="title is-4">Inscription</h2>
                    <?php if ($error) echo '<div class="notification is-danger">'.htmlspecialchars($error).'</div>'; ?>
                    <?php if ($success) echo '<div class="notification is-success">'. $success .'</div>'; ?>
                    <form method="post">
                        <div class="field">
                            <label class="label">Nom d'utilisateur</label>
                            <div class="control">
                                <input class="input" type="text" name="username" placeholder="Nom d'utilisateur" required>
                            </div>
                        </div>

                        <div class="field">
                            <label class="label">Mot de passe</label>
                            <div class="control">
                                <input class="input" type="password" name="password" placeholder="Mot de passe" required>
                            </div>
                        </div>

                        <div class="field">
                            <div class="control">
                                <button type="submit" name="register" class="button is-primary is-fullwidth">S'inscrire</button>
                            </div>
                        </div>
                    </form>
                    <div class="has-text-centered" style="margin-top:12px;">
                        <span style="color:#555;margin-right:8px;">Déjà inscrit ?</span>
                        <a href="login.php" class="button is-link is-light is-small">Se connecter</a>
                    </div>
    </div>
</div>
</body>
</html>
