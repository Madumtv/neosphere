<!-- Bouton Services & RDV retiré -->
                            <!-- Bouton Gérer le carrousel d'accueil retiré -->
<?php include_once __DIR__ . '/../inc/menu.php'; ?>
<?php
// Sécurité : redirige si non admin
session_start();
if (!isset($_SESSION['user']) || !(
    (isset($_SESSION['is_admin']) && $_SESSION['is_admin']) ||
    (isset($_SESSION['role']) && $_SESSION['role'] === 'admin') ||
    (isset($_SESSION['role_id']) && $_SESSION['role_id'] == 1) ||
    (isset($_SESSION['user']) && $_SESSION['user'] === 'admin')
)) {
    header('Location: ../membre/login.php');
    exit;
}

$displayUser = htmlspecialchars($_SESSION['user'] ?? 'Utilisateur');
$displayRole = '';
if (isset($_SESSION['role']) && $_SESSION['role'] !== '') {
    $displayRole = htmlspecialchars($_SESSION['role']);
} elseif (isset($_SESSION['role_id'])) {
    try {
        require_once __DIR__ . '/inc/db.php';
        $stmt = $pdo->prepare('SELECT name FROM roles WHERE id = :id LIMIT 1');
        $stmt->execute([':id' => intval($_SESSION['role_id'])]);
        $r = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($r && isset($r['name'])) $displayRole = htmlspecialchars($r['name']);
        else $displayRole = 'inconnu';
    } catch (Exception $e) {
        $displayRole = 'inconnu';
    }
} else {
    $displayRole = 'utilisateur';
}
?>
<!DOCTYPE html>
<html>
<head>
        <title>Admin - Neosphere</title>
        <!-- Bulma & Font Awesome for nicer admin buttons -->
        <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bulma@0.9.4/css/bulma.min.css">
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
        <link rel="stylesheet" href="../style/index-style.css">
        <style>
            /* petites personnalisations pour les boutons admin */
            .admin-cta .button { min-width:160px; }
            .admin-cta .buttons { gap: 0.75rem; }
                /* Fond spécifique pour l'admin (utilise le fond.jpg à la racine) */
                body {
                    background-image: url('../fond.jpg');
                    background-repeat: no-repeat;
                    background-position: center center;
                    background-size: cover;
                    background-color: #000;
                    background-attachment: fixed;
                }
    /* Rendre le fond visible sur toute la page */
    html, body { height: 100%; margin: 0 !important; padding: 0 !important; }
    /* Logo admin centré en haut (sans marge supérieure) */
    .admin-logo { display:block; margin:0 auto; max-width:180px; height:auto; }
    .admin-logo-card { background: rgba(255,255,255,0.0); box-shadow:none; }
    /* Wrapper admin transparent: garder seulement la card visible */
    .adm-root { background: transparent; min-height:100vh; display:flex; justify-content:center; align-items:flex-start; padding-top:0; }
    /* Conteneur semi-transparent pour laisser voir le fond (ne s'applique plus ici) */
    .container.box { background: rgba(255,255,255,0.85); }
        </style>
</head>
<body>
    <?php include_once __DIR__ . '/../inc/menu.php'; ?>
<!-- Logo in a small clickable card linking to site root -->
<div class="admin-logo-wrapper" style="text-align:center;">
    <a href="../index.php" title="Retour à l'accueil">
        <div class="card admin-logo-card" style="display:inline-block;padding:8px;border-radius:10px;">
            <div class="card-content" style="padding:6px;">
                <figure class="image is-128x128" style="margin:0 auto;">
                    <img src="../logo.jpg" alt="Neosphere" class="admin-logo">
                </figure>
            </div>
        </div>
    </a>
</div>
<div class="adm-root">
    <div class="card" style="max-width:600px;margin:0;background: rgba(255,255,255,0.95);border-radius:10px;padding:1.25rem;">
        <header class="card-header">
            <p class="card-header-title">Bienvenue <?php echo $displayUser; ?> !</p>
        </header>
        <div class="card-content">
            <div class="content">
                <p>Vous êtes connecté en tant que <strong><?php echo $displayUser; ?></strong><?php if ($displayRole && $displayRole !== $displayUser) { echo ' (rôle : <strong>' . $displayRole . '</strong>)'; } ?>.</p>

                <div class="admin-cta" style="margin-top:1rem;">
                    <table class="table is-fullwidth admin-actions-table" style="background:transparent;">
                        <tbody>
                            <tr>
                                <td>
                                    <a href="grille.php" class="button is-primary is-fullwidth is-medium">
                                        <span class="icon"><i class="fas fa-list-alt"></i></span>
                                        <span>Gérer la grille</span>
                                    </a>
                                </td>
                                <td>
                                    <a href="content_blocks.php" class="button is-info is-fullwidth is-medium">
                                        <span class="icon"><i class="fas fa-pen-nib"></i></span>
                                        <span>Gérer le contenu</span>
                                    </a>
                                </td>
                            </tr>
                            <tr>
                                <td>
                                    <a href="users.php" class="button is-link is-fullwidth is-medium">
                                        <span class="icon"><i class="fas fa-users"></i></span>
                                        <span>Utilisateurs</span>
                                    </a>
                                </td>
                                <td>
                                    <a href="services.php" class="button is-success is-fullwidth is-medium" title="Gérer services et générer les créneaux">
                                        <span class="icon"><i class="fas fa-calendar-plus"></i></span>
                                        <span>Services / Créneaux</span>
                                    </a>
                                </td>
                            </tr>
                            <tr>
                                <td>
                                    <a href="appointments_validate.php" class="button is-success is-light is-fullwidth is-medium" title="Valider les demandes de rendez-vous">
                                        <span class="icon"><i class="fas fa-calendar-check"></i></span>
                                        <span>Validation RDV</span>
                                    </a>
                                    <a href="calendar.php" class="button is-link is-light is-fullwidth is-medium" style="margin-top:8px;" title="Vue calendrier des créneaux">
                                        <span class="icon"><i class="fas fa-calendar-alt"></i></span>
                                        <span>Calendrier</span>
                                    </a>
                                </td>
                                <td>
                                    <a href="debug_session.php" class="button is-warning is-fullwidth is-medium">
                                        <span class="icon"><i class="fas fa-bug"></i></span>
                                        <span>Debug session</span>
                                    </a>
                                    <a href="mails_envoyes.php" class="button is-info is-fullwidth is-medium" style="margin-top:8px;">
                                        <span class="icon"><i class="fas fa-envelope"></i></span>
                                        <span>Mails envoyés</span>
                                    </a>
                                    <a href="carousel.php" class="button is-warning is-fullwidth is-medium" style="margin-top:8px;" title="Gérer le carrousel d'accueil">
                                        <span class="icon"><i class="fas fa-images"></i></span>
                                        <span>Gérer le carrousel</span>
                                    </a>
                                </td>
                            </tr>
                            <tr>
                                <td>
                                    <a href="../index.php" class="button is-light is-fullwidth is-medium" title="Aller sur le site">
                                        <span class="icon"><i class="fas fa-home"></i></span>
                                        <span>Accueil</span>
                                    </a>
                                </td>
                                <td>
                                    <a href="../membre/logout.php" class="button is-danger is-fullwidth is-medium">
                                        <span class="icon"><i class="fas fa-sign-out-alt"></i></span>
                                        <span>Déconnexion</span>
                                    </a>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>


                <?php
                // Inclusion du debug de session (affiché uniquement pour les admins)
                if (isset($isAdmin) && $isAdmin) {
                        include __DIR__ . '/debug_session.php';
                }
                ?>

            </div>
        </div>
    </div>
</div>
</body>
</html>
