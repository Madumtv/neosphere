<?php
ini_set('memory_limit', '128M');
// upload_carousel_image.php : upload d'une image dans admin/carousel_images/
// Sécurité : uniquement admin

session_start();
function showError($msg, $code = 400) {
    http_response_code($code);
    echo '<div style="background:#ffe7e7;color:#c0392b;padding:18px 24px;border-radius:8px;font-family:Arial,sans-serif;font-size:15px;margin:30px auto;max-width:500px;border:1px solid #e0b4b4;box-shadow:0 2px 8px #0001;">'
        .'<strong>Erreur :</strong> '.htmlspecialchars($msg).'</div>';
    exit;
}
if (!isset($_SESSION['user']) || !(
    (isset($_SESSION['is_admin']) && $_SESSION['is_admin']) ||
    (isset($_SESSION['role']) && $_SESSION['role'] === 'admin') ||
    (isset($_SESSION['role_id']) && $_SESSION['role_id'] == 1) ||
    (isset($_SESSION['user']) && $_SESSION['user'] === 'admin')
)) {
    showError("Accès refusé.", 403);
}

require_once __DIR__ . '/../inc/db.php';
$targetDir = __DIR__ . '/carousel_images/';
if (!is_dir($targetDir)) {
    mkdir($targetDir, 0775, true);
}
if (!isset($_FILES['image']) || $_FILES['image']['error'] !== UPLOAD_ERR_OK) {
    showError("Aucun fichier reçu ou erreur d'upload.");
}
$allowed = ['jpg','jpeg','png','webp'];
$ext = strtolower(pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION));
if (!in_array($ext, $allowed)) {
    showError("Format non autorisé. Formats acceptés : jpg, jpeg, png, webp.");
}
$filename = uniqid('carousel_', true) . '.' . $ext;
$targetFile = $targetDir . $filename;

// Adaptation automatique de l'image au format carrousel (16:9, 1920x1080px)
$targetWidth = 1920;
$targetHeight = 1080;
$targetRatio = $targetWidth / $targetHeight;
$tmpFile = $_FILES['image']['tmp_name'];
$imgInfo = getimagesize($tmpFile);
if (!$imgInfo) {
    showError("Le fichier n'est pas une image valide.");
}
switch ($imgInfo[2]) {
    case IMAGETYPE_JPEG: $src = @imagecreatefromjpeg($tmpFile); break;
    case IMAGETYPE_PNG:  $src = @imagecreatefrompng($tmpFile); break;
    case IMAGETYPE_WEBP: $src = @imagecreatefromwebp($tmpFile); break;
    default: showError("Format d'image non supporté pour adaptation.");
}
if (!$src) {
    showError("Impossible de lire l'image. Fichier corrompu ou format non supporté.");
}
$srcW = imagesx($src);
$srcH = imagesy($src);
$srcRatio = $srcW / $srcH;
// Calcul du crop
if ($srcRatio > $targetRatio) {
    // Image trop large, crop sur la largeur
    $newW = (int)($srcH * $targetRatio);
    $newH = $srcH;
    $x = (int)(($srcW - $newW) / 2);
    $y = 0;
} else {
    // Image trop haute, crop sur la hauteur
    $newW = $srcW;
    $newH = (int)($srcW / $targetRatio);
    $x = 0;
    $y = (int)(($srcH - $newH) / 2);
}
$cropped = imagecrop($src, ['x' => $x, 'y' => $y, 'width' => $newW, 'height' => $newH]);
if (!$cropped) {
    imagedestroy($src);
    showError("Erreur lors du recadrage de l'image.", 500);
}
$resized = imagecreatetruecolor($targetWidth, $targetHeight);
imagecopyresampled($resized, $cropped, 0, 0, 0, 0, $targetWidth, $targetHeight, $newW, $newH);
// Sauvegarde
if ($ext === 'jpg' || $ext === 'jpeg') {
    if (!imagejpeg($resized, $targetFile, 90)) {
        showError("Erreur lors de l'enregistrement de l'image JPEG.", 500);
    }
} elseif ($ext === 'png') {
    if (!imagepng($resized, $targetFile, 7)) {
        showError("Erreur lors de l'enregistrement de l'image PNG.", 500);
    }
} elseif ($ext === 'webp') {
    if (!imagewebp($resized, $targetFile, 90)) {
        showError("Erreur lors de l'enregistrement de l'image WebP.", 500);
    }
}
imagedestroy($src);
imagedestroy($cropped);
imagedestroy($resized);

// Vérifier la connexion PDO
if (!$pdo) {
    showError("Erreur de connexion à la base de données.", 500);
}
// Enregistrement dans la base SQL
$stmt = $pdo->prepare('INSERT INTO carousel_images (filename) VALUES (?)');
if (!$stmt->execute([$filename])) {
    showError("Erreur lors de l'enregistrement en base de données.", 500);
}
header('Content-Type: text/html; charset=utf-8');
echo '<div style="background:#e7ffe7;color:#2e7d32;padding:18px 24px;border-radius:8px;font-family:Arial,sans-serif;font-size:15px;margin:30px auto;max-width:500px;border:1px solid #b4e0b4;box-shadow:0 2px 8px #0001;">'
    .'<strong>Succès :</strong> Image adaptée et enregistrée !</div>';
