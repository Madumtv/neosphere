<?php
// carousel_test.php
// Page de test pour afficher le contenu du carrousel

require_once __DIR__ . '/inc/db.php';

// $pdo est défini dans inc/db.php
// Récupérer les images du carrousel
$sql = "SELECT * FROM carousel_images ORDER BY created_at DESC";
$result = $pdo ? $pdo->query($sql) : false;

?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Test Carrousel</title>
    <style>
        .carousel {
            display: flex;
            gap: 20px;
            overflow-x: auto;
            padding: 20px;
        }
        .carousel-item {
            border: 1px solid #ccc;
            border-radius: 8px;
            padding: 10px;
            min-width: 200px;
            text-align: center;
            background: #f9f9f9;
        }
        .carousel-item img {
            max-width: 180px;
            max-height: 120px;
            border-radius: 4px;
        }
        .carousel-item .title {
            margin-top: 8px;
            font-weight: bold;
        }
        .carousel-empty {
            color: #888;
            font-style: italic;
        }
    </style>
</head>
<body>
    <h1>Test d'affichage du carrousel</h1>
    <div class="carousel">
        <?php
        if ($result && $result->rowCount() > 0) {
            while ($row = $result->fetch()) {
                // Chemin de l'image (adapter si besoin)
                $imgPath = 'admin/carousel_images/' . htmlspecialchars($row['filename']);
                echo '<div class="carousel-item">';
                echo '<img src="' . $imgPath . '" alt="' . htmlspecialchars($row['title'] ?? $row['filename']) . '" />';
                echo '<div class="title">' . htmlspecialchars($row['title'] ?? '') . '</div>';
                echo '<div class="date">' . htmlspecialchars($row['created_at']) . '</div>';
                echo '</div>';
            }
        } else {
            echo '<div class="carousel-empty">Aucune image trouvée dans le carrousel.</div>';
        }
        ?>
    </div>
</body>
</html>
