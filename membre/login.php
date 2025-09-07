<?php
// Debug: afficher toutes les erreurs pour le développement
@ini_set('display_errors', '1');
@ini_set('display_startup_errors', '1');
error_reporting(E_ALL);

session_start();
$__authPath = dirname(__DIR__).'/inc/auth.php';
if (is_file($__authPath)) {
    require_once $__authPath;
} else {
    // Fallback minimal si auth.php non déployé (évite fatal en prod)
    if (empty($_SESSION['csrf_token'])) $_SESSION['csrf_token'] = bin2hex(random_bytes(16));
    if (!function_exists('auth_regenerate_on_login')) {
        function auth_regenerate_on_login(){ if (session_status()===PHP_SESSION_ACTIVE) session_regenerate_id(true); }
    }
}
error_reporting(E_ALL);
$error = '';
if (isset($_POST['login'])) {
    try {
        require_once __DIR__ . '/../admin/inc/db.php';

        // trouver la colonne de mot de passe
        $stmt = $pdo->query("DESCRIBE users");
        $cols = $stmt->fetchAll(PDO::FETCH_COLUMN, 0);
        $candidates = ['password','passwd','password_hash','pass','pwd'];
        $pwdCol = null;
        foreach ($candidates as $c) if (in_array($c, $cols)) { $pwdCol = $c; break; }
        if (!$pwdCol) throw new Exception('Colonne de mot de passe introuvable');

        // récupérer l'utilisateur par username
        $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ?");
        $stmt->execute([$_POST['username']]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($user) {
            $stored = $user[$pwdCol] ?? '';
            $ok = false;
            // si stocké comme bcrypt/argon2/etc (password_hash)
            if (password_verify($_POST['password'], $stored)) {
                $ok = true;
            } else {
                // fallback: vérifier md5
                if ($stored === md5($_POST['password'])) {
                    $ok = true;
                    // migrer le hash vers password_hash
                    $newHash = password_hash($_POST['password'], PASSWORD_DEFAULT);
                    $u = $pdo->prepare("UPDATE users SET {$pwdCol} = ? WHERE username = ?");
                    $u->execute([$newHash, $_POST['username']]);
                }
            }

            if ($ok) {
                // Standardisation des clés de session
                if (isset($user['id'])) $_SESSION['user_id'] = $user['id'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['user'] = $user['username']; // compat rétro
                if (isset($user['role'])) $_SESSION['role'] = $user['role'];
                if (isset($user['role_id'])) $_SESSION['role_id'] = $user['role_id'];
                if (isset($user['is_admin'])) $_SESSION['is_admin'] = (bool)$user['is_admin'];
                // fallback admin heuristique
                $_SESSION['is_admin'] = (!empty($_SESSION['is_admin']) && $_SESSION['is_admin']) || ($_SESSION['user'] === 'admin') || (isset($_SESSION['role']) && $_SESSION['role'] === 'admin') || (isset($_SESSION['role_id']) && $_SESSION['role_id'] == 1);
                auth_regenerate_on_login();
                header('Location: espace.php');
                exit;
            } else {
                $error = 'Identifiants incorrects';
            }
        } else {
            $error = 'Identifiants incorrects';
        }
    } catch (Exception $e) {
        $error = 'Erreur base de données : ' . $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>Connexion membre</title>
        <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bulma@0.9.4/css/bulma.min.css">
        <link rel="stylesheet" href="../style/index-style.css">
</head>
    <style>
        /* Fond plein page et centrage du formulaire */
        html,body {margin:0; padding:0;}
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
</head>
<body>
    <?php include_once __DIR__ . '/../inc/menu.php'; ?>
<div class="auth-container">
    <div class="box-wrap">
        <div class="box auth-box">
                    <h2 class="title is-4">Connexion</h2>
                    <?php if ($error) echo '<div class="notification is-danger">'.htmlspecialchars($error).'</div>'; ?>
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
                                <button type="submit" name="login" class="button is-link is-fullwidth">Se connecter</button>
                            </div>
                        </div>
                    </form>
                    <div class="has-text-centered" style="margin-top:12px;">
                        <span style="color:#555;margin-right:8px;">Pas encore inscrit ?</span>
                        <a href="register.php" class="button is-link is-light is-small">Créer un compte</a>
                    </div>
    </div>
</div>
</body>
</html>
