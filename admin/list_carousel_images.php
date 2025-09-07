<?php
// list_carousel_images.php : renvoie la liste des images du carrousel depuis la base SQL
require_once __DIR__ . '/../inc/db.php';
header('Content-Type: application/json');
if (!$pdo) {
    echo json_encode([]);
    exit;
}
$stmt = $pdo->query('SELECT id, filename FROM carousel_images ORDER BY created_at DESC');
echo json_encode($stmt->fetchAll());
