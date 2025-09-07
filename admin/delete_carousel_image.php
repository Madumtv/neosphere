<?php
// delete_carousel_image.php : suppression d'une image du carrousel
session_start();
if (!isset($_SESSION['user']) || !(
    (isset($_SESSION['is_admin']) && $_SESSION['is_admin']) ||
    (isset($_SESSION['role']) && $_SESSION['role'] === 'admin') ||
    (isset($_SESSION['role_id']) && $_SESSION['role_id'] == 1) ||
    (isset($_SESSION['user']) && $_SESSION['user'] === 'admin')
)) {
    http_response_code(403);
    echo "Accès refusé.";
    exit;
}
require_once __DIR__ . '/../inc/db.php';
if (!$pdo) {
    http_response_code(500);
    echo "Erreur de connexion à la base de données.";
    exit;
}
$id = isset($_POST['id']) ? intval($_POST['id']) : 0;
$filename = isset($_POST['filename']) ? $_POST['filename'] : '';
if (!$id || !$filename) {
    http_response_code(400);
    echo "Paramètres manquants.";
    exit;
}
// Supprimer le fichier
$file = __DIR__ . '/carousel_images/' . basename($filename);
if (is_file($file)) {
    unlink($file);
}
// Supprimer l'entrée SQL
$stmt = $pdo->prepare('DELETE FROM carousel_images WHERE id = ?');
$stmt->execute([$id]);
echo "Image supprimée.";
