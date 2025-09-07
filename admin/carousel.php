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
                <div style="margin-top:1rem;">
                    <h4 style="font-size:1.15em;color:#363636;margin-bottom:10px;">Gestion du carrousel d'accueil</h4>
                    <div id="carousel-admin">
                        <form id="carousel-upload-form" enctype="multipart/form-data" style="margin-bottom:18px;">
                            <input type="file" name="image" accept="image/*" required>
                            <button type="submit" class="button is-primary is-small">Ajouter une image</button>
                        </form>
                        <div id="carousel-message" style="color:#c00;font-size:13px;"></div>
                        <div id="carousel-images" style="display:flex;flex-wrap:wrap;gap:16px;margin-top:18px;"></div>
                    </div>
                    <script>
                    // Récupère et affiche les images du carrousel depuis la base SQL
                    function loadCarouselImages() {
                        fetch('/admin/list_carousel_images.php')
                            .then(r => r.json())
                            .then(imgs => {
                                const cont = document.getElementById('carousel-images');
                                cont.innerHTML = '';
                                imgs.forEach(obj => {
                                    const url = '/admin/carousel_images/' + encodeURIComponent(obj.filename);
                                    const div = document.createElement('div');
                                    div.style.position = 'relative';
                                    div.style.display = 'inline-block';
                                    div.innerHTML = `<img src="${url}" style="max-width:120px;max-height:80px;border-radius:8px;border:1px solid #ccc;box-shadow:0 2px 8px #0002;">` +
                                        `<button style='position:absolute;top:2px;right:2px;background:#c00;color:#fff;border:none;border-radius:6px;padding:2px 7px;cursor:pointer;font-size:13px;' title='Supprimer' onclick='deleteCarouselImage(${obj.id}, \"${obj.filename}\")'>✖</button>`;
                                    cont.appendChild(div);
                                });
                            });
                    }
                    // Suppression d'une image (nécessite script côté serveur)
                    function deleteCarouselImage(id, filename) {
                        if (!confirm('Supprimer cette image du carrousel ?')) return;
                        fetch('/admin/delete_carousel_image.php', {
                            method: 'POST',
                            headers: {'Content-Type':'application/x-www-form-urlencoded'},
                            body: 'id=' + encodeURIComponent(id) + '&filename=' + encodeURIComponent(filename)
                        })
                        .then(r => r.text())
                        .then(msg => {
                            document.getElementById('carousel-message').textContent = msg;
                            loadCarouselImages();
                        });
                    }
                    // Upload d'une image (nécessite script côté serveur)
                    document.getElementById('carousel-upload-form').onsubmit = function(e) {
                        e.preventDefault();
                        const formData = new FormData(this);
                        fetch('/admin/upload_carousel_image.php', {
                            method: 'POST',
                            body: formData
                        })
                        .then(r => r.text())
                        .then(msg => {
                            document.getElementById('carousel-message').textContent = msg;
                            loadCarouselImages();
                        });
                    };
                    loadCarouselImages();
                    </script>
                </div>

                <!-- Boutons d'administration retirés -->


                <?php
                // ...existing code...
                ?>

            </div>
        </div>
    </div>
</div>
</body>
</html>
