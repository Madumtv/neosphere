<?php
// carousel_data.php - renvoie la liste des images du carrousel depuis la base SQL
require_once __DIR__ . '/inc/db.php';
$base_url = '/admin/carousel_images/';
$images = [];
if ($pdo) {
    $stmt = $pdo->query('SELECT filename FROM carousel_images ORDER BY created_at DESC');
    $images = $stmt->fetchAll(PDO::FETCH_COLUMN);
}
// Ajoute fond.jpg de la racine si présent
$fond_path = __DIR__ . '/fond.jpg';
if (is_file($fond_path)) {
    array_unshift($images, '../fond.jpg'); // chemin relatif pour l'URL
}
header('Content-Type: application/json');
echo json_encode(array_map(function($img) use ($base_url){
    // Si l'image commence par ../, c'est fond.jpg
    if (strpos($img, '../') === 0) return $img;
    return $base_url . rawurlencode($img);
}, $images));
