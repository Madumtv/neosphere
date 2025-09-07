<?php
// grille.php
// Système de gestion de grille tarifaire en PHP/JSON
// Debug: afficher toutes les erreurs pour le développement
@ini_set('display_errors', '1');
@ini_set('display_startup_errors', '1');
error_reporting(E_ALL);


// Vérification d'accès admin
session_start();
$isAdmin = false;
if (isset($_SESSION['is_admin']) && $_SESSION['is_admin']) $isAdmin = true;
if (isset($_SESSION['role']) && $_SESSION['role'] === 'admin') $isAdmin = true;
if (isset($_SESSION['role_id']) && $_SESSION['role_id'] == 1) $isAdmin = true;
if (isset($_SESSION['user']) && $_SESSION['user'] === 'admin') $isAdmin = true;
if (!isset($_SESSION['user']) || !$isAdmin) {
    header('Location: ../membre/login.php');
    exit;
}

// CSRF token generation
if (!isset($_SESSION['csrf_token'])) {
    try { $_SESSION['csrf_token'] = bin2hex(random_bytes(16)); } catch (Exception $e) { $_SESSION['csrf_token'] = bin2hex(mt_rand()); }
}

function check_csrf($token) {
    return isset($_SESSION['csrf_token']) && is_string($token) && hash_equals($_SESSION['csrf_token'], $token);
}

/**
 * Convertit une chaîne de durée en minutes (entier).
 * Formats supportés : "90", "90m", "1h30", "1:30", "1.5h", "1 h 30", etc.
 */
function parseDurationToMinutes($s) {
    if ($s === null) return 0;
    $s = trim((string)$s);
    if ($s === '') return 0;
    // chiffre pur (minutes)
    if (preg_match('/^\d+$/', $s)) return intval($s);
    // 90m or 90 min
    if (preg_match('/^(\d+)\s*m(in)?$/i', $s, $m)) return intval($m[1]);
    // H:MM or HH:MM
    if (preg_match('/^(\d{1,2})\s*[:hH]\s*(\d{1,2})$/', $s, $m)) return intval($m[1]) * 60 + intval($m[2]);
    // 1h30, 1h 30, 1 h 30
    if (preg_match('/^(\d+)\s*h(?:ou?re?s?)?\s*(\d{1,2})?/i', $s, $m)) {
        $h = intval($m[1]);
        $mm = isset($m[2]) && $m[2] !== '' ? intval($m[2]) : 0;
        return $h * 60 + $mm;
    }
    // decimal hours 1.5h
    if (preg_match('/^(\d+(?:[\.,]\d+))\s*h$/i', $s, $m)) {
        $h = floatval(str_replace(',', '.', $m[1]));
        return intval(round($h * 60));
    }
    // last resort: find first number
    if (preg_match('/(\d+)/', $s, $m)) return intval($m[1]);
    return 0;
}


$dataFile = __DIR__ . '/grille_data.json';

// Utilisation de la base SQL si disponible
require_once __DIR__ . '/inc/db.php'; // expose $pdo

function tableExists($pdo, $name) {
    try {
        $stmt = $pdo->query("SHOW TABLES LIKE " . $pdo->quote($name));
        return (bool) $stmt && $stmt->fetch();
    } catch (Exception $e) { return false; }
}

$useDb = tableExists($pdo, 'prestations');

// S'assurer que la table 'grids' existe (pour multi-grilles)
if ($useDb) {
    try {
        $pdo->exec("CREATE TABLE IF NOT EXISTS grids (
            id INT AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(200) NOT NULL,
            owner VARCHAR(200) DEFAULT NULL,
            is_default TINYINT(1) DEFAULT 0,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    } catch (Exception $e) { /* ignore */ }
    // ajouter colonne grid_id à prestations si manque
    try {
        $col = $pdo->query("SHOW COLUMNS FROM prestations LIKE 'grid_id'")->fetch();
        if (!$col) {
            $pdo->exec("ALTER TABLE prestations ADD COLUMN grid_id INT DEFAULT 0");
        }
    } catch (Exception $e) { /* ignore */ }
}

// Si la table n'existe pas et que le fichier JSON existe, tenter une migration automatique
if (!$useDb && file_exists($dataFile)) {
    $json = file_get_contents($dataFile);
    $arr = json_decode($json, true);
    if (is_array($arr) && count($arr) > 0) {
        // créer la table prestations (inclut grid_id pour multi-grilles)
        $create = "CREATE TABLE IF NOT EXISTS prestations (
            id INT AUTO_INCREMENT PRIMARY KEY,
            nom VARCHAR(255) NOT NULL,
            duree VARCHAR(100) DEFAULT NULL,
            description TEXT,
            prix_ttc DECIMAL(10,2) DEFAULT 0.00,
            poids INT DEFAULT 0,
            grid_id INT DEFAULT 0
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
        try {
            $pdo->exec($create);
            $useDb = true;
            // importer les données
            $i = 0;
            $ins = $pdo->prepare("INSERT INTO prestations (nom,duree,description,prix_ttc,poids,grid_id) VALUES (:n,:d,:desc,:p,:po,:gid)");
            foreach ($arr as $item) {
                $ins->execute([
                    ':n' => $item['nom'] ?? '',
                    ':d' => parseDurationToMinutes($item['duree'] ?? ''),
                    ':desc' => $item['description'] ?? '',
                    ':p' => isset($item['prix_ttc']) ? floatval($item['prix_ttc']) : 0,
                    ':po' => $i++,
                    ':gid' => 0
                ]);
            }
            // s'assurer que la table grids existe aussi après migration
            try {
                $pdo->exec("CREATE TABLE IF NOT EXISTS grids (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    name VARCHAR(200) NOT NULL,
                    owner VARCHAR(200) DEFAULT NULL,
                    is_default TINYINT(1) DEFAULT 0,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
            } catch (Exception $e) { /* ignore */ }
        } catch (Exception $e) {
            // migration échouée -> on reste en JSON
            $useDb = false;
        }
    }
}

// Loader/Save utilities: si $useDb true, on utilise la table SQL, sinon JSON
function loadPrestations($file, $pdo=null, $useDb=false, $gridId = 0) {
    if ($useDb && $pdo) {
        try {
            if ($gridId > 0) {
                $stmt = $pdo->prepare("SELECT * FROM prestations WHERE grid_id = :gid ORDER BY poids ASC, id ASC");
                $stmt->execute([':gid' => $gridId]);
                $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
            } else {
                $rows = $pdo->query("SELECT * FROM prestations ORDER BY poids ASC, id ASC")->fetchAll(PDO::FETCH_ASSOC);
            }
            return $rows ?: [];
        } catch (Exception $e) { return []; }
    }
    if (!file_exists($file)) return [];
    $json = file_get_contents($file);
    $data = json_decode($json, true);
    return is_array($data) ? $data : [];
}

function savePrestations($file, $prestations, $pdo=null, $useDb=false, $gridId = 0) {
    if ($useDb && $pdo) {
        try {
            // Mettre à jour les poids en fonction de l'ordre fourni (attendu: array d'IDs ou d'objets)
            // Si on reçoit des objets complets, on supprime et réinsère uniquement les prestations de la grille concernée
            $pdo->beginTransaction();
            if ($gridId > 0) {
                // supprimer uniquement les prestations liées à la grille
                $del = $pdo->prepare("DELETE FROM prestations WHERE grid_id = :gid");
                $del->execute([':gid' => $gridId]);
            } else {
                // pas de grille spécifiée -> vider toute la table (comportement legacy)
                $pdo->exec("TRUNCATE TABLE prestations");
            }
            $ins = $pdo->prepare("INSERT INTO prestations (nom,duree,description,prix_ttc,poids,grid_id) VALUES (:n,:d,:desc,:p,:po,:gid)");
            $i = 0;
            foreach ($prestations as $item) {
                if (is_array($item)) {
                    $nom = $item['nom'] ?? ($item['name'] ?? '');
                    $duree = $item['duree'] ?? '';
                    $desc = $item['description'] ?? '';
                    $prix = isset($item['prix_ttc']) ? floatval($item['prix_ttc']) : 0;
                } else {
                    $nom=''; $duree=''; $desc=''; $prix=0;
                }
                $ins->execute([':n'=>$nom,':d'=>$duree,':desc'=>$desc,':p'=>$prix,':po'=>$i++,':gid'=> $gridId]);
            }
            $pdo->commit();
        } catch (Exception $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
        }
        return;
    }
    file_put_contents($file, json_encode($prestations, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
}

// Ajout/édition/suppression en prenant en compte SQL ou JSON
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];
    // création d'une nouvelle grille
    if ($action === 'create_grid') {
        if (!check_csrf($_POST['csrf_token'] ?? '')) { $_SESSION['flash']='Jeton CSRF invalide.'; header('Location: grille.php'); exit; }
        $gname = trim($_POST['grid_name'] ?? '');
        if ($gname !== '' && $useDb) {
            try {
                $owner = $_SESSION['user'] ?? null;
                // si aucune grille existante, marquer comme default
                $cnt = $pdo->query("SELECT COUNT(*) as c FROM grids")->fetch(PDO::FETCH_ASSOC);
                $isdef = (isset($cnt['c']) && intval($cnt['c'])===0) ? 1 : 0;
                $ins = $pdo->prepare("INSERT INTO grids (name,owner,is_default) VALUES (:n,:o,:d)");
                $ins->execute([':n'=>$gname,':o'=>$owner,':d'=>$isdef]);
                $newId = $pdo->lastInsertId();
                // set the new grid as current in session
                $_SESSION['current_grid'] = intval($newId);
                // message de confirmation dans la session
                $_SESSION['flash'] = 'La grille « ' . htmlspecialchars($gname, ENT_QUOTES) . ' » a été créée.';
                // If AJAX request, return JSON so client can show inline confirmation
                if (!empty($_SERVER['HTTP_X_REQUESTED_WITH'])) {
                    header('Content-Type: application/json');
                    echo json_encode(['ok'=>true,'new_id'=>intval($newId),'name'=>$gname]);
                    exit;
                }
                header('Location: grille.php?grid=' . intval($newId)); exit;
            } catch (Exception $e) { }
        }
    }
    // définir grille par défaut (affichée sur l'accueil)
    if ($action === 'set_default_grid' && $useDb) {
        if (!check_csrf($_POST['csrf_token'] ?? '')) { $_SESSION['flash']='Jeton CSRF invalide.'; header('Location: grille.php'); exit; }
        $gid = intval($_POST['grid_id'] ?? 0);
        if ($gid>0) {
            try {
                $pdo->beginTransaction();
                $pdo->exec("UPDATE grids SET is_default = 0");
                $stmt = $pdo->prepare("UPDATE grids SET is_default = 1 WHERE id = :id");
                $stmt->execute([':id'=>$gid]);
                $pdo->commit();
                // confirmation
                $_SESSION['flash'] = 'La grille sélectionnée a été définie comme grille par défaut.';
            } catch (Exception $e) { if ($pdo->inTransaction()) $pdo->rollBack(); }
        }
        // If AJAX request, return JSON instead of redirecting (client expects JSON)
        if (!empty($_SERVER['HTTP_X_REQUESTED_WITH'])) {
            header('Content-Type: application/json');
            echo json_encode(['ok' => true, 'success' => true, 'grid_id' => intval($gid)]);
            exit;
        }
        header('Location: grille.php?grid=' . $gid); exit;
    }
    // suppression d'une grille
    if ($action === 'delete_grid' && $useDb) {
        // support JSON/AJAX response
        if (!check_csrf($_POST['csrf_token'] ?? '')) {
            if (!empty($_SERVER['HTTP_X_REQUESTED_WITH'])) {
                header('Content-Type: application/json'); echo json_encode(['ok'=>false,'error'=>'CSRF']); exit;
            }
            $_SESSION['flash']='Jeton CSRF invalide.'; header('Location: grille.php'); exit;
        }
        $gid = intval($_POST['grid_id'] ?? 0);
        if ($gid > 0) {
            try {
                // Vérifier si grille par défaut
                $g = $pdo->prepare("SELECT is_default FROM grids WHERE id = :id LIMIT 1");
                $g->execute([':id'=>$gid]);
                $grr = $g->fetch(PDO::FETCH_ASSOC);
                if ($grr && !empty($grr['is_default'])) {
                    $_SESSION['flash'] = 'Impossible de supprimer la grille par défaut. Changez d\'abord la grille par défaut.';
                } else {
                    // Supprimer prestations liées puis la grille
                    $pdo->beginTransaction();
                    $delP = $pdo->prepare("DELETE FROM prestations WHERE grid_id = :gid");
                    $delP->execute([':gid'=>$gid]);
                    $delG = $pdo->prepare("DELETE FROM grids WHERE id = :id");
                    $delG->execute([':id'=>$gid]);
                    $pdo->commit();
                    $_SESSION['flash'] = 'La grille a été supprimée.';
                }
            } catch (Exception $e) { if ($pdo->inTransaction()) $pdo->rollBack(); }
        }
        if (!empty($_SERVER['HTTP_X_REQUESTED_WITH'])) {
            header('Content-Type: application/json'); echo json_encode(['ok'=>true]); exit;
        }
        header('Location: grille.php'); exit;
    }
    // renommer une grille
    if ($action === 'rename_grid' && $useDb) {
        if (!check_csrf($_POST['csrf_token'] ?? '')) {
            if (!empty($_SERVER['HTTP_X_REQUESTED_WITH'])) { header('Content-Type: application/json'); echo json_encode(['ok'=>false,'error'=>'CSRF']); exit; }
            $_SESSION['flash'] = 'Jeton CSRF invalide.'; header('Location: grille.php'); exit;
        }
        $gid = intval($_POST['grid_id'] ?? 0);
        $new = trim($_POST['new_name'] ?? '');
        if ($gid > 0 && $new !== '') {
            try {
                $u = $pdo->prepare("UPDATE grids SET name = :name WHERE id = :id");
                $u->execute([':name' => $new, ':id' => $gid]);
                $_SESSION['flash'] = 'Grille renommée.';
                if (!empty($_SERVER['HTTP_X_REQUESTED_WITH'])) { header('Content-Type: application/json'); echo json_encode(['ok'=>true]); exit; }
            } catch (Exception $e) { if (!empty($_SERVER['HTTP_X_REQUESTED_WITH'])) { header('Content-Type: application/json'); echo json_encode(['ok'=>false,'error'=>'sql']); exit; } }
        }
        header('Location: grille.php'); exit;
    }
    // dupliquer une grille
    if ($action === 'duplicate_grid' && $useDb) {
        if (!check_csrf($_POST['csrf_token'] ?? '')) {
            if (!empty($_SERVER['HTTP_X_REQUESTED_WITH'])) { header('Content-Type: application/json'); echo json_encode(['ok'=>false,'error'=>'CSRF']); exit; }
            $_SESSION['flash'] = 'Jeton CSRF invalide.'; header('Location: grille.php'); exit;
        }
        $gid = intval($_POST['grid_id'] ?? 0);
        $new = trim($_POST['new_name'] ?? '');
        if ($gid > 0 && $new !== '') {
            try {
                $owner = $_SESSION['user'] ?? null;
                $pdo->beginTransaction();
                $ins = $pdo->prepare("INSERT INTO grids (name,owner,is_default) VALUES (:n,:o,0)");
                $ins->execute([':n'=>$new,':o'=>$owner]);
                $newId = $pdo->lastInsertId();
                // copier les prestations
                $rows = $pdo->prepare("SELECT nom,duree,description,prix_ttc,poids FROM prestations WHERE grid_id = :gid ORDER BY poids ASC, id ASC");
                $rows->execute([':gid'=>$gid]);
                $prs = $rows->fetchAll(PDO::FETCH_ASSOC);
                if ($prs) {
                    $insP = $pdo->prepare("INSERT INTO prestations (nom,duree,description,prix_ttc,poids,grid_id) VALUES (:n,:d,:desc,:p,:po,:gid)");
                    foreach ($prs as $pitem) {
                        $insP->execute([':n'=>$pitem['nom'],':d'=>parseDurationToMinutes($pitem['duree']),':desc'=>$pitem['description'],':p'=>$pitem['prix_ttc'],':po'=>$pitem['poids'],':gid'=>$newId]);
                    }
                }
                $pdo->commit();
                // set new grid as current
                $_SESSION['current_grid'] = intval($newId);
                $_SESSION['flash'] = 'Grille dupliquée.';
                if (!empty($_SERVER['HTTP_X_REQUESTED_WITH'])) { header('Content-Type: application/json'); echo json_encode(['ok'=>true,'new_id'=>intval($newId)]); exit; }
            } catch (Exception $e) { if ($pdo->inTransaction()) $pdo->rollBack(); if (!empty($_SERVER['HTTP_X_REQUESTED_WITH'])) { header('Content-Type: application/json'); echo json_encode(['ok'=>false,'error'=>'sql']); exit; } }
        }
        header('Location: grille.php'); exit;
    }
    if ($action === 'add') {
        if (!check_csrf($_POST['csrf_token'] ?? '')) { $_SESSION['flash']='Jeton CSRF invalide.'; header('Location: grille.php'); exit; }
    $nom = $_POST['nom'] ?? '';
    $duree = parseDurationToMinutes($_POST['duree'] ?? '');
        $description = $_POST['description'] ?? '';
        $prix = isset($_POST['prix_ttc']) ? floatval($_POST['prix_ttc']) : 0;
        if ($useDb) {
            try {
                $emoji = isset($_POST['emoji']) && trim($_POST['emoji']) !== '' ? trim($_POST['emoji']) : '🩺';
                $p = $pdo->prepare("INSERT INTO prestations (nom,duree,description,prix_ttc,poids,grid_id,emoji) VALUES (:n,:d,:desc,:p,:po,:gid,:emoji)");
                $max = $pdo->query("SELECT COALESCE(MAX(poids),-1) as m FROM prestations")->fetch(PDO::FETCH_ASSOC);
                $next = (isset($max['m']) ? intval($max['m']) + 1 : 0);
                // utiliser la grille fournie via le formulaire si présente, sinon la grille courante
                $g = isset($_POST['grid_id']) ? intval($_POST['grid_id']) : (isset($_SESSION['current_grid']) ? intval($_SESSION['current_grid']) : 0);
                $debugVals = ['nom'=>$nom,'duree'=>$duree,'description'=>$description,'prix_ttc'=>$prix,'poids'=>$next,'grid_id'=>$g,'emoji'=>$emoji];
                echo '<pre style="background:#fffbe6;border:1px solid #ffe58f;padding:12px;font-size:14px;">DEBUG SQL:<br>INSERT INTO prestations (nom,duree,description,prix_ttc,poids,grid_id,emoji)<br>VALUES<br>'.htmlspecialchars(print_r($debugVals,true)).'</pre>';
                $p->execute([':n'=>$nom,':d'=>$duree,':desc'=>$description,':p'=>$prix,':po'=>$next, ':gid'=>$g, ':emoji'=>$emoji]);
                $_SESSION['flash'] = 'Prestation ajoutée avec succès.';
            } catch (Exception $e) { }
        } else {
            $emoji = isset($_POST['emoji']) && trim($_POST['emoji']) !== '' ? trim($_POST['emoji']) : '🩺';
            $prestations = loadPrestations($dataFile, $pdo, $useDb, $g ?? 0);
            $prestations[] = ['nom'=>$nom,'duree'=>$duree,'description'=>$description,'prix_ttc'=>$prix,'emoji'=>$emoji];
            savePrestations($dataFile, $prestations, $pdo, $useDb, $g ?? 0);
            $_SESSION['flash'] = 'Prestation ajoutée (JSON).';
        }
        // rediriger vers la grille courante pour voir l'ajout
        $redir = 'grille.php';
        if (!empty($g)) $redir .= '?grid=' . intval($g);
        header('Location: ' . $redir); exit;
    }
    if ($action === 'edit_in_form' && isset($_POST['edit_index'])) {
        if (!check_csrf($_POST['csrf_token'] ?? '')) { $_SESSION['flash']='Jeton CSRF invalide.'; header('Location: grille.php'); exit; }
        $idx = intval($_POST['edit_index']);
        $nom = $_POST['nom'] ?? '';
        $duree = parseDurationToMinutes($_POST['duree'] ?? '');
        $description = $_POST['description'] ?? '';
        $prix = isset($_POST['prix_ttc']) ? floatval($_POST['prix_ttc']) : 0;
        if ($useDb) {
            try {
                $emoji = isset($_POST['emoji']) && trim($_POST['emoji']) !== '' ? trim($_POST['emoji']) : '🩺';
                $debugVals = ['nom'=>$nom,'duree'=>$duree,'description'=>$description,'prix_ttc'=>$prix,'emoji'=>$emoji,'id'=>$idx];
                echo '<pre style="background:#fffbe6;border:1px solid #ffe58f;padding:12px;font-size:14px;">DEBUG SQL:<br>UPDATE prestations SET nom=:n,duree=:d,description=:desc,prix_ttc=:p,emoji=:emoji WHERE id = :id<br>VALUES<br>'.htmlspecialchars(print_r($debugVals,true)).'</pre>';
                $u = $pdo->prepare("UPDATE prestations SET nom=:n,duree=:d,description=:desc,prix_ttc=:p,emoji=:emoji WHERE id = :id");
                $u->execute([':n'=>$nom,':d'=>$duree,':desc'=>$description,':p'=>$prix,':emoji'=>$emoji,':id'=>$idx]);
            } catch (Exception $e) { }
        } else {
            $prestations = loadPrestations($dataFile, $pdo, $useDb, 0);
            if (isset($prestations[$idx])) {
                $prestations[$idx]['nom'] = $nom;
                $prestations[$idx]['duree'] = $duree;
                $prestations[$idx]['description'] = $description;
                $prestations[$idx]['prix_ttc'] = $prix;
                savePrestations($dataFile, $prestations, $pdo, $useDb, 0);
            }
        }
        header('Location: grille.php'); exit;
    }
    if ($action === 'save_order') {
        if (!check_csrf($_POST['csrf_token'] ?? '')) { $_SESSION['flash']='Jeton CSRF invalide.'; header('Location: grille.php'); exit; }
        if (isset($_POST['order'])) {
            $order = json_decode($_POST['order'], true);
            if ($useDb && is_array($order)) {
                try {
                    $pdo->beginTransaction();
                    $i = 0;
                    // limiter la mise à jour aux prestations de la grille courante si présente
                    $currentG = isset($_SESSION['current_grid']) ? intval($_SESSION['current_grid']) : 0;
                    foreach ($order as $item) {
                        // item may be id or object with id
                        $id = is_array($item) && isset($item['id']) ? intval($item['id']) : (is_numeric($item) ? intval($item) : null);
                        if ($id !== null) {
                            if ($currentG>0) {
                                $upd = $pdo->prepare("UPDATE prestations SET poids = :po WHERE id = :id AND grid_id = :gid");
                                $upd->execute([':po'=>$i,':id'=>$id,':gid'=>$currentG]);
                            } else {
                                $pdo->prepare("UPDATE prestations SET poids = :po WHERE id = :id")->execute([':po'=>$i,':id'=>$id]);
                            }
                        }
                        $i++;
                    }
                    $pdo->commit();
                } catch (Exception $e) { if ($pdo->inTransaction()) $pdo->rollBack(); }
            } elseif (!$useDb && is_array($order)) {
                // legacy: save full order for JSON storage
                savePrestations($dataFile, $order, $pdo, $useDb, 0);
            }
        }
        header('Location: grille.php'); exit;
    }
}

// Suppression d'une prestation
if (isset($_GET['delete'])) {
    $del = intval($_GET['delete']);
    if ($useDb) {
        try { $pdo->prepare("DELETE FROM prestations WHERE id = :id")->execute([':id'=>$del]); } catch (Exception $e) { }
    } else {
        $prestations = loadPrestations($dataFile, $pdo, $useDb, 0);
        if (isset($prestations[$del])) { array_splice($prestations, $del, 1); savePrestations($dataFile, $prestations, $pdo, $useDb, 0); }
    }
    header('Location: grille.php'); exit;
}

// Charger listes de grids si SQL
$grids = [];
$currentGrid = 0;
if ($useDb) {
    try {
        $grows = $pdo->query("SELECT * FROM grids ORDER BY id ASC")->fetchAll(PDO::FETCH_ASSOC);
        foreach ($grows as $gr) $grids[$gr['id']] = $gr;
        // choisir grille current: param GET ?grid=, sinon session, sinon default
        if (isset($_GET['grid'])) { $currentGrid = intval($_GET['grid']); $_SESSION['current_grid']=$currentGrid; }
        elseif (isset($_SESSION['current_grid'])) $currentGrid = intval($_SESSION['current_grid']);
        else {
            foreach ($grids as $gid => $g) { if (!empty($g['is_default'])) { $currentGrid = $gid; break; } }
        }
    } catch (Exception $e) { /* ignore */ }
}

// Chargement des prestations filtrées par grid si SQL
if ($useDb) {
    try {
        if ($currentGrid>0) {
            $stmt = $pdo->prepare("SELECT * FROM prestations WHERE grid_id = :gid ORDER BY poids ASC, id ASC");
            $stmt->execute([':gid'=>$currentGrid]);
            $prestations = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } else {
            $prestations = $pdo->query("SELECT * FROM prestations ORDER BY poids ASC, id ASC")->fetchAll(PDO::FETCH_ASSOC);
        }
    } catch (Exception $e) { $prestations = []; }
} else {
    $prestations = loadPrestations($dataFile, $pdo, $useDb);
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Grille tarifaire</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bulma@0.9.4/css/bulma.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .drag-handle { cursor: grab; }
        .drag-handle:active { cursor: grabbing; }
        @media print {
            .no-print { display: none !important; }
            body { margin: 0; }
            .table { page-break-inside: avoid; font-size: 12pt; }
            .table th, .table td { border: 1px solid #000; padding: 8px; }
        }
        .modal-card {
            max-height: 90vh;
            overflow-y: auto;
        }
    /* Compact debug box */
    .debug-wrapper { margin-bottom: 1rem; }
    .debug-toggle { background: transparent; border: none; cursor: pointer; font-weight: 600; display:flex; align-items:center; gap:8px; }
    .debug-toggle .arrow { display:inline-block; transition: transform .18s ease; }
    .debug-content { border:1px solid #eee; background:#fafafa; padding:8px; border-radius:6px; max-height:300px; overflow:auto; transition: max-height .22s ease; }
    .debug-content.collapsed { max-height:40px; overflow:hidden; }
        /* Fond admin (même image que le site racine) */
        body {
            background-image: url('../fond.jpg');
            background-repeat: no-repeat;
            background-position: center center;
            background-size: cover;
            background-color: #000;
            background-attachment: fixed;
        }
    </style>
</head>
<body>
<?php include_once __DIR__ . '/../menu.php'; ?>
    <?php include_once __DIR__ . '/../menu.php'; ?>
    <div class="scroll-content">
    <section class="section">
        <div class="container box" style="background: rgba(255,255,255,0.95); padding: 24px; border-radius:10px;">
            <div style="text-align:center;margin-top:0;">
                    <img src="../logo.jpg" alt="Logo Néosphère" style="height:120px;margin-bottom:16px;margin-top:0;" />
            </div>

            <!-- Barre d'administration similaire à admin/index.php -->
            <div class="has-text-centered" style="margin-bottom:1rem;">
                <div class="buttons is-centered" style="display:inline-block">
                    <a href="index.php" class="button">Accueil admin</a>
                    <a href="../index.php" class="button is-info">Accueil site</a>
                    <a href="debug_session.php" class="button is-warning">Debug session</a>
                    <a href="../membre/logout.php" class="button is-danger">Déconnexion</a>
                </div>
            </div>
            <!-- Modal Rename Grid -->
            <div class="modal" id="renameGridModal">
                <div class="modal-background" data-close></div>
                <div class="modal-card">
                    <header class="modal-card-head">
                        <p class="modal-card-title">Renommer une grille</p>
                        <button class="delete" aria-label="close" data-close></button>
                    </header>
                    <section class="modal-card-body">
                        <form id="renameGridForm">
                            <input type="hidden" name="action" value="rename_grid">
                            <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                            <div class="field">
                                <label class="label">Grille à renommer</label>
                                <div class="control">
                                    <div class="select is-fullwidth">
                                        <select name="grid_id" id="rename_grid_select">
                                            <?php foreach ($grids as $gid => $g): ?>
                                                <option value="<?= intval($gid) ?>"><?= htmlspecialchars($g['name']) ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                </div>
                            </div>
                            <div class="field"><label class="label">Nouveau nom</label><div class="control"><input class="input" name="new_name" id="rename_grid_name" required></div></div>
                        </form>
                    </section>
                    <footer class="modal-card-foot">
                        <button class="button is-warning" id="renameGridConfirm"><span class="icon"><i class="fas fa-i-cursor"></i></span><span>Renommer</span></button>
                        <button class="button" data-close>Annuler</button>
                    </footer>
                </div>
            </div>

            <!-- Modal Duplicate Grid -->
            <div class="modal" id="duplicateGridModal">
                <div class="modal-background" data-close></div>
                <div class="modal-card">
                    <header class="modal-card-head">
                        <p class="modal-card-title">Dupliquer une grille</p>
                        <button class="delete" aria-label="close" data-close></button>
                    </header>
                    <section class="modal-card-body">
                        <form id="duplicateGridForm">
                            <input type="hidden" name="action" value="duplicate_grid">
                            <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                            <div class="field">
                                <label class="label">Grille à dupliquer</label>
                                <div class="control">
                                    <div class="select is-fullwidth">
                                        <select name="grid_id" id="duplicate_grid_select">
                                            <?php foreach ($grids as $gid => $g): ?>
                                                <option value="<?= intval($gid) ?>"><?= htmlspecialchars($g['name']) ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                </div>
                            </div>
                            <div class="field"><label class="label">Nom de la copie</label><div class="control"><input class="input" name="new_name" id="duplicate_grid_name" required></div></div>
                        </form>
                    </section>
                    <footer class="modal-card-foot">
                        <button class="button is-success" id="duplicateGridConfirm"><span class="icon"><i class="fas fa-clone"></i></span><span>Dupliquer</span></button>
                        <button class="button" data-close>Annuler</button>
                    </footer>
                </div>
            </div>

            <!-- Modal Set Default Grid -->
            <div class="modal" id="defaultGridModal">
                <div class="modal-background" data-close></div>
                <div class="modal-card">
                    <header class="modal-card-head">
                        <p class="modal-card-title">Choisir la grille par défaut</p>
                        <button class="delete" aria-label="close" data-close></button>
                    </header>
                    <section class="modal-card-body">
                        <div class="content">
                            <p>Sélectionnez la grille qui sera affichée par défaut sur l'accueil :</p>
                            <div class="field">
                                <div class="control">
                                    <div class="select is-fullwidth">
                                        <select id="default_grid_select" name="default_grid_select">
                                            <?php foreach ($grids as $gid => $g): ?>
                                                <option value="<?= intval($gid) ?>" <?= !empty($g['is_default'])? 'selected':''; ?>><?= htmlspecialchars($g['name']) ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </section>
                    <footer class="modal-card-foot">
                        <button class="button is-link" id="defaultGridConfirm">Définir par défaut</button>
                        <button class="button" data-close>Annuler</button>
                    </footer>
                </div>
            </div>

            <?php
            // inclusion du debug de session si admin (compactable)
            if (isset($isAdmin) && $isAdmin) {
                ?>
                <div class="debug-wrapper">
                    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:6px;">
                        <div style="font-weight:700;color:#333;">Zone debug (admin)</div>
                        <div>
                            <button class="debug-toggle" id="debugToggle" title="Réduire/étendre le debug">
                                <span class="arrow" id="debugArrow">▾</span>
                                <span style="font-size:0.9rem;color:#666;">Afficher</span>
                            </button>
                        </div>
                    </div>
                    <div class="debug-content collapsed" id="debugContent">
                        <?php include __DIR__ . '/debug_session.php'; ?>
                    </div>
                </div>
                <?php
            }
            // Afficher un message flash simple si défini
            if (isset($_SESSION['flash']) && $_SESSION['flash']) {
                echo '<div class="notification is-primary">' . $_SESSION['flash'] . '</div>';
                unset($_SESSION['flash']);
            }
            ?>

            <h1 class="title is-2 has-text-centered">
                <i class="fas fa-list-alt mr-3"></i>Grille tarifaire
            </h1>
            <div style="text-align:center;margin-bottom:1rem;">
                <form method="get" style="display:inline-block;">
                    <label class="label" style="margin-right:8px;">Grille:</label>
                    <div class="select" style="display:inline-block;">
                        <select name="grid" onchange="this.form.submit()">
                            <option value="0">-- Toutes --</option>
                            <?php foreach ($grids as $gid => $g): ?>
                                <option value="<?php echo intval($gid); ?>" <?php echo ($gid==$currentGrid)?'selected':''; ?>><?php echo htmlspecialchars($g['name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <button type="button" class="button is-light" id="manageGridsBtn" style="margin-left:8px;">Gérer les grilles</button>
                </form>
            </div>
            <div class="columns">
                <div class="column is-half">
                    <div class="box no-print">
                        <h2 class="title is-4">
                            <i class="fas fa-plus mr-2"></i>Ajouter une prestation
                        </h2>
                        <form method="post">
                            <input type="hidden" name="action" value="add" id="formActionInput">
                            <input type="hidden" name="csrf_token" value="<?= isset($_SESSION['csrf_token'])?$_SESSION['csrf_token']:'' ?>">
                            <?php if ($useDb): ?>
                            <div class="field">
                                <label class="label">Grille cible</label>
                                <div class="control">
                                    <div class="select">
                                        <select name="grid_id">
                                            <?php foreach ($grids as $gid => $g): ?>
                                                <option value="<?= intval($gid) ?>" <?= ($gid==$currentGrid)?'selected':''; ?>><?= htmlspecialchars($g['name']) ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                </div>
                            </div>
                            <?php endif; ?>
                            <div class="field">
                                <label class="label">Nom de la prestation</label>
                                <div class="control has-icons-left">
                                    <input class="input" type="text" name="nom" required placeholder="ex: Massage relaxant">
                                    <span class="icon is-small is-left">
                                        <i class="fas fa-tag"></i>
                                    </span>
                                </div>
                            </div>
                            <div class="field">
                                <label class="label">Durée</label>
                                <div class="control has-icons-left">
                                    <input class="input" type="number" name="duree" required min="0" placeholder="minutes (ex: 90)">
                                    <span class="icon is-small is-left">
                                        <i class="fas fa-clock"></i>
                                    </span>
                                </div>
                            </div>
                            <div class="field">
                                <label class="label">Description</label>
                                <div class="control">
                                    <textarea class="textarea" name="description" rows="3" required placeholder="Description de la prestation..."></textarea>
                                </div>
                            </div>
                            <div class="field">
                                <label class="label">Prix TTC (€)</label>
                                <div class="control has-icons-left">
                                    <input class="input" type="number" name="prix_ttc" required step="0.01" min="0" placeholder="50.00">
                                    <span class="icon is-small is-left">
                                        <i class="fas fa-euro-sign"></i>
                                    </span>
                                </div>
                            </div>
                            <div class="field">
                                <label class="label">Emoji (smiley)</label>
                                <div class="control has-icons-left">
                                    <input class="input" type="text" name="emoji" maxlength="10" placeholder="ex: 🩺, 💆, 💅">
                                    <span class="icon is-small is-left">
                                        <i class="fas fa-smile"></i>
                                    </span>
                                </div>
                                <p class="help">Laissez vide pour utiliser 🩺 par défaut.<br>
<a href="https://emojipedia.org/" target="_blank" rel="noopener" style="color:#E6A23C;text-decoration:underline;">Trouver des smileys sur Emojipedia</a></p>
                            </div>
                            <div class="field">
                                <div class="control">
                                    <button class="button is-primary is-fullwidth" type="submit" id="formSubmitBtn">
                                        <span class="icon">
                                            <i class="fas fa-plus"></i>
                                        </span>
                                        <span id="formSubmitLabel">Ajouter</span>
                                    </button>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
                <div class="column is-half">
                    <div class="box no-print">
                        <h2 class="title is-4">
                            <i class="fas fa-print mr-2"></i>Impression & gestion
                        </h2>
                        <div style="display:flex;flex-direction:column;gap:8px;">
                            <button class="button is-primary is-fullwidth" id="showPrintModal" type="button">
                                <span class="icon"><i class="fas fa-print"></i></span>
                                <span>Imprimer la grille</span>
                            </button>
                            <button class="button is-warning is-fullwidth" id="renameGridBtn" type="button">
                                <span class="icon"><i class="fas fa-i-cursor"></i></span>
                                <span>Renommer grille</span>
                            </button>
                            <button class="button is-success is-fullwidth" id="duplicateGridBtn" type="button">
                                <span class="icon"><i class="fas fa-clone"></i></span>
                                <span>Dupliquer grille</span>
                            </button>
                            <button class="button is-link is-fullwidth" id="defaultGridBtn" type="button">
                                <span class="icon"><i class="fas fa-star"></i></span>
                                <span>Définir comme grille par défaut</span>
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Modal Impression -->
            <div class="modal" id="printModal">
                <div class="modal-background"></div>
                <div class="modal-card">
                    <header class="modal-card-head">
                        <p class="modal-card-title">
                            <i class="fas fa-print mr-2"></i>Options d'impression
                        </p>
                        <button class="delete" aria-label="close" id="cancelPrintBtn"></button>
                    </header>
                    <section class="modal-card-body">
                        <form id="printOptionsForm">
                            <div class="field">
                                <label class="label">Titres personnalisés</label>
                                <div class="field">
                                    <label class="label">Titre principal</label>
                                    <div class="control has-icons-left">
                                        <input class="input" type="text" name="titre_principal" value="Grille tarifaire" placeholder="Titre principal">
                                        <span class="icon is-small is-left">
                                            <i class="fas fa-heading"></i>
                                        </span>
                                    </div>
                                </div>
                                <div class="field">
                                    <label class="label">Titre du tableau</label>
                                    <div class="control has-icons-left">
                                        <input class="input" type="text" name="titre_tableau" value="Liste des prestations" placeholder="Titre du tableau">
                                        <span class="icon is-small is-left">
                                            <i class="fas fa-list"></i>
                                        </span>
                                    </div>
                                </div>
                                <div class="field">
                                    <label class="label">Texte descriptif (optionnel)</label>
                                    <div class="control has-icons-left">
                                        <textarea class="textarea" name="texte_descriptif" rows="2" placeholder="Texte à afficher entre le titre et le tableau (ex: Tarifs en vigueur depuis janvier 2025)"></textarea>
                                        <span class="icon is-small is-left">
                                            <i class="fas fa-align-left"></i>
                                        </span>
                                    </div>
                                    <p class="help">Ce texte apparaîtra entre le titre du tableau et le tableau lui-même</p>
                                </div>
                            </div>
                            
                            <hr>
                            
                            <div class="field">
                                <label class="label">Options d'affichage</label>
                                <div class="field">
                                    <label class="checkbox">
                                        <input type="checkbox" name="show_headers" checked>
                                        <i class="fas fa-th mr-2"></i>Afficher les en-têtes de colonnes
                                    </label>
                                </div>
                                <div class="field">
                                    <label class="checkbox">
                                        <input type="checkbox" name="remove_shadow" checked>
                                        <i class="fas fa-square mr-2"></i>Retirer les ombres et effets
                                    </label>
                                </div>
                                <div class="field">
                                    <label class="checkbox">
                                        <input type="checkbox" name="show_h_lines" checked>
                                        <i class="fas fa-grip-lines-vertical mr-2"></i>Afficher lignes horizontales
                                    </label>
                                </div>
                                <div class="field">
                                    <label class="checkbox">
                                        <input type="checkbox" name="show_v_lines">
                                        <i class="fas fa-grip-lines mr-2"></i>Afficher lignes verticales
                                    </label>
                                </div>
                            </div>
                            
                            <hr>
                            
                            <div class="field">
                                <label class="label">Colonnes à imprimer</label>
                                <div class="field">
                                    <label class="checkbox">
                                        <input type="checkbox" name="col_drag" checked>
                                        <i class="fas fa-arrows-alt mr-2"></i>Drag & Drop
                                    </label>
                                </div>
                                <div class="field">
                                    <label class="checkbox">
                                        <input type="checkbox" name="col_nom" checked>
                                        <i class="fas fa-tag mr-2"></i>Nom
                                    </label>
                                </div>
                                <div class="field">
                                    <label class="checkbox">
                                        <input type="checkbox" name="col_duree" checked>
                                        <i class="fas fa-clock mr-2"></i>Durée
                                    </label>
                                </div>
                                <div class="field">
                                    <label class="checkbox">
                                        <input type="checkbox" name="col_description" checked>
                                        <i class="fas fa-align-left mr-2"></i>Description
                                    </label>
                                </div>
                                <div class="field">
                                    <label class="checkbox">
                                        <input type="checkbox" name="col_prix_ttc" checked>
                                        <i class="fas fa-euro-sign mr-2"></i>Prix TTC (€)
                                    </label>
                                </div>
                                <div class="field">
                                    <label class="checkbox">
                                        <input type="checkbox" name="col_action" checked>
                                        <i class="fas fa-cogs mr-2"></i>Action
                                    </label>
                                </div>
                            </div>
                        </form>
                    </section>
                    <footer class="modal-card-foot">
                        <button class="button is-success" id="confirmPrintBtn">
                            <span class="icon">
                                <i class="fas fa-print"></i>
                            </span>
                            <span>Imprimer</span>
                        </button>
                        <button class="button" id="cancelPrintBtn2">Annuler</button>
                    </footer>
                </div>
            </div>

            <div class="box">
                <h2 class="title is-4">
                    <i class="fas fa-list mr-2"></i>Liste des prestations
                </h2>
                <p class="subtitle is-6" id="texte-descriptif" style="display: none; margin-bottom: 1rem; font-style: italic; color: #666;"></p>
                <div class="services-search" style="margin:26px auto 10px; max-width:500px;">
                    <label for="admin-service-search" style="display:block; font-size:12px; font-weight:600; letter-spacing:.5px; text-transform:uppercase; color:#777; margin-bottom:6px;">Rechercher un soin</label>
                    <input id="admin-service-search" type="text" placeholder="Tapez pour filtrer (nom, description, durée, emoji)..." style="width:100%; padding:12px 16px; border:1px solid #ddd; border-radius:10px; font-size:14px; background:#FFFEFC;" />
                    <small id="admin-service-search-count" style="display:block; margin-top:6px; font-size:11px; color:#999;"></small>
                </div>
                <form method="post" id="ordreForm">
    <script>
    // Recherche dynamique dans la grille admin
    document.getElementById('admin-service-search').addEventListener('input', function(){
        const val = this.value.toLowerCase();
        let count = 0;
        document.querySelectorAll('#tableBody tr').forEach(function(tr){
            const txt = tr.textContent.toLowerCase();
            if(txt.indexOf(val) !== -1){
                tr.style.display = '';
                count++;
            }else{
                tr.style.display = 'none';
            }
        });
        document.getElementById('admin-service-search-count').textContent = count + ' soin(s) affiché(s)';
    });
    </script>
                    <input type="hidden" name="action" value="save_order">
                    <input type="hidden" name="csrf_token" value="<?= isset($_SESSION['csrf_token']) ? $_SESSION['csrf_token'] : '' ?>">
                    <!-- Visible column chooser (like Excel) -->
                    <div class="columns is-vcentered no-print" style="margin-bottom:10px;">
                        <div class="column is-narrow">
                            <button class="button is-light is-small" id="toggleColumnChooser" type="button">Choisir colonnes</button>
                        </div>
                        <div class="column">
                            <div id="columnChooser" class="box" style="display:none;padding:8px;">
                                <label class="checkbox" style="margin-right:10px;display:inline-block;"><input type="checkbox" data-chooser="col_drag" checked> Drag & Drop</label>
                                <label class="checkbox" style="margin-right:10px;display:inline-block;"><input type="checkbox" data-chooser="col_nom" checked> Nom</label>
                                <label class="checkbox" style="margin-right:10px;display:inline-block;"><input type="checkbox" data-chooser="col_duree" checked> Durée</label>
                                <label class="checkbox" style="margin-right:10px;display:inline-block;"><input type="checkbox" data-chooser="col_description" checked> Description</label>
                                <label class="checkbox" style="margin-right:10px;display:inline-block;"><input type="checkbox" data-chooser="col_prix_ttc" checked> Prix TTC</label>
                                <label class="checkbox" style="margin-right:10px;display:inline-block;"><input type="checkbox" data-chooser="col_action" checked> Action</label>
                                <span style="margin-left:12px;display:inline-block;vertical-align:middle;font-size:0.9rem;color:#666">Lignes:</span>
                                <label class="checkbox" style="margin-right:10px;display:inline-block;margin-left:6px;"><input type="checkbox" data-chooser="show_h_lines" checked> H</label>
                                <label class="checkbox" style="margin-right:10px;display:inline-block;"><input type="checkbox" data-chooser="show_v_lines"> V</label>
                            </div>
                        </div>
                    </div>
                    <table class="table is-fullwidth is-striped is-hoverable" id="grilleTable">
                        <thead>
                            <tr>
                                <th><i class="fas fa-arrows-alt"></i></th>
                                <th>Emoji</th>
                                <th><i class="fas fa-tag mr-2"></i>Nom</th>
                                <th><i class="fas fa-clock mr-2"></i>Durée</th>
                                <th><i class="fas fa-align-left mr-2"></i>Description</th>
                                <th><i class="fas fa-euro-sign mr-2"></i>Prix TTC (€)</th>
                                <th class="no-print"><i class="fas fa-cogs mr-2"></i>Action</th>
                            </tr>
                        </thead>
                        <tbody id="tableBody">
                        <?php foreach ($prestations as $i => $p): ?>
                        <tr data-id="<?= intval($p['id']) ?>">
                            <td class="drag-handle has-text-centered">
                                <i class="fas fa-grip-vertical" title="Déplacer"></i>
                            </td>
                            <td>
                                <span style="background:#FFF3D6; border-radius:12px; padding:6px 12px; font-size:1.5em; display:inline-block; min-width:2.5em; text-align:center;"><?= htmlspecialchars($p['emoji'] ?? '🩺') ?></span>
                            </td>
                            <td>
                              <input type="hidden" name="ordre[]" value="<?= intval($p['id']) ?>">
                              <?= htmlspecialchars($p['nom']) ?>
                            </td>
                            <td><?= intval($p['duree']) ?></td>
                            <td><?= htmlspecialchars($p['description']) ?></td>
                            <td><strong><?= htmlspecialchars($p['prix_ttc']) ?> €</strong></td>
                            <td class="no-print">
                                <div class="field is-grouped">
                                    <div class="control">
                                        <button class="button is-small is-info" type="button" onclick="openEditModal(
                                            <?= intval($p['id']) ?>,
                                            '<?= htmlspecialchars(addslashes($p['nom'])) ?>',
                                            <?= intval($p['duree']) ?>,
                                            '<?= htmlspecialchars(addslashes($p['description'])) ?>',
                                            '<?= htmlspecialchars(addslashes($p['prix_ttc'])) ?>',
                                            '<?= htmlspecialchars(addslashes($p['emoji'] ?? '🩺')) ?>'
                                        );">
                                            <span class="icon is-small">
                                                <i class="fas fa-edit"></i>
                                            </span>
                                        </button>
            <!-- Modal édition prestation -->
            <div class="modal" id="editPrestationModal">
                <div class="modal-background" onclick="closeEditModal()"></div>
                <div class="modal-card">
                    <header class="modal-card-head">
                        <p class="modal-card-title"><i class="fas fa-edit mr-2"></i>Éditer la prestation</p>
                        <button class="delete" aria-label="close" onclick="closeEditModal()"></button>
                    </header>
                    <section class="modal-card-body">
                        <form method="post" id="editPrestationForm">
                            <input type="hidden" name="action" value="edit_in_form">
                            <input type="hidden" name="csrf_token" value="<?= isset($_SESSION['csrf_token'])?$_SESSION['csrf_token']:'' ?>">
                            <input type="hidden" name="edit_index" id="edit_index_modal">
                            <div class="field">
                                <label class="label">Nom de la prestation</label>
                                <div class="control has-icons-left">
                                    <input class="input" type="text" name="nom" id="edit_nom_modal" required>
                                    <span class="icon is-small is-left"><i class="fas fa-tag"></i></span>
                                </div>
                            </div>
                            <div class="field">
                                <label class="label">Durée</label>
                                <div class="control has-icons-left">
                                    <input class="input" type="number" name="duree" id="edit_duree_modal" required min="0">
                                    <span class="icon is-small is-left"><i class="fas fa-clock"></i></span>
                                </div>
                            </div>
                            <div class="field">
                                <label class="label">Description</label>
                                <div class="control">
                                    <textarea class="textarea" name="description" id="edit_description_modal" rows="3" required></textarea>
                                </div>
                            </div>
                            <div class="field">
                                <label class="label">Prix TTC (€)</label>
                                <div class="control has-icons-left">
                                    <input class="input" type="number" name="prix_ttc" id="edit_prix_modal" required step="0.01" min="0">
                                    <span class="icon is-small is-left"><i class="fas fa-euro-sign"></i></span>
                                </div>
                            </div>
                            <div class="field">
                                <label class="label">Emoji (smiley)</label>
                                <div class="control has-icons-left">
                                    <input class="input" type="text" name="emoji" id="edit_emoji_modal" maxlength="10">
                                    <span class="icon is-small is-left"><i class="fas fa-smile"></i></span>
                                </div>
                                <p class="help">Laissez vide pour utiliser 🩺 par défaut.<br>
                                    <a href="https://emojipedia.org/" target="_blank" rel="noopener" style="color:#E6A23C;text-decoration:underline;">Trouver des smileys sur Emojipedia</a></p>
                            </div>
                            <div class="field">
                                <div class="control">
                                    <button class="button is-primary is-fullwidth" type="submit">
                                        <span class="icon"><i class="fas fa-save"></i></span>
                                        <span>Enregistrer</span>
                                    </button>
                                </div>
                            </div>
                        </form>
                    </section>
                </div>
            </div>
    <script>
    function openEditModal(id, nom, duree, description, prix, emoji) {
        document.getElementById('edit_index_modal').value = id;
        document.getElementById('edit_nom_modal').value = nom;
        document.getElementById('edit_duree_modal').value = duree;
        document.getElementById('edit_description_modal').value = description;
        document.getElementById('edit_prix_modal').value = prix;
        document.getElementById('edit_emoji_modal').value = emoji && emoji.trim() ? emoji : '🩺';
        document.getElementById('editPrestationModal').classList.add('is-active');
    }
    function closeEditModal() {
        document.getElementById('editPrestationModal').classList.remove('is-active');
    }
    document.getElementById('editPrestationForm').addEventListener('submit', function(e){
        // On laisse le submit normal (POST), le modal se fermera à la redirection
    });
    </script>
                                    </div>
                                    <div class="control">
                                        <a class="button is-small is-danger" href="?delete=<?= intval($p['id']) ?>" onclick="return confirm('Supprimer cette prestation ?')">
                                            <span class="icon is-small">
                                                <i class="fas fa-trash"></i>
                                            </span>
                                        </a>
                                    </div>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </form>

                <div class="field is-grouped no-print">
                    <div class="control">
                        <button class="button is-primary" type="button" onclick="saveOrder()">
                            <span class="icon">
                                <i class="fas fa-save"></i>
                            </span>
                            <span>Enregistrer l'ordre</span>
                        </button>
                    </div>
                    <div class="control">
                        <button class="button is-info" id="showPrintModal" type="button">
                            <span class="icon">
                                <i class="fas fa-print"></i>
                            </span>
                            <span>Imprimer la grille</span>
                        </button>
                    </div>
                    <!-- boutons 'Renommer' et 'Dupliquer' retirés d'ici : ils sont disponibles dans la colonne 'Impression & gestion' -->
                </div>
            </div>
        </div>
    </section>
            <!-- Modal gestion des grilles -->
            <div class="modal" id="gridsModal">
                <div class="modal-background" data-close></div>
                <div class="modal-card">
                    <header class="modal-card-head">
                        <p class="modal-card-title">Gérer les grilles</p>
                        <button class="delete" aria-label="close" data-close></button>
                    </header>
                    <section class="modal-card-body">
                        <div class="content">
                            <?php if (!$useDb): ?>
                                <div class="notification is-warning">La gestion multi-grilles nécessite l'utilisation de la base (prestations SQL).</div>
                            <?php else: ?>
                                <form method="post">
                                    <input type="hidden" name="action" value="create_grid">
                                    <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                                    <div class="field"><label class="label">Nom nouvelle grille</label><div class="control"><input class="input" name="grid_name" placeholder="ex: Grille été 2025"></div></div>
                                    <div class="field"><div class="control"><button class="button is-primary" type="submit">Créer</button></div></div>
                                </form>
                                <hr>
                                <h4 class="title is-6">Rendre par défaut</h4>
                                <form method="post">
                                    <input type="hidden" name="action" value="set_default_grid">
                                    <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                                    <div class="field"><div class="control"><div class="select"><select name="grid_id">
                                        <?php foreach ($grids as $gid => $g): ?>
                                            <option value="<?php echo intval($gid); ?>" <?php echo (!empty($g['is_default']))? 'selected':''; ?>><?php echo htmlspecialchars($g['name']); ?></option>
                                        <?php endforeach; ?>
                                    </select></div></div></div>
                                    <div class="field"><div class="control"><button class="button is-link" type="submit">Définir comme grille par défaut</button></div></div>
                                </form>
                                <hr>
                                <h4 class="title is-6">Gérer les grilles existantes</h4>
                                <div class="content">
                                    <p>Sélectionnez une grille dans la liste ci-dessous, puis utilisez les boutons ci-dessus pour renommer ou dupliquer :</p>
                                    <div class="box" style="max-height:320px; overflow:auto;">
                                    <?php foreach ($grids as $gid => $g): ?>
                                        <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:6px;">
                                            <div style="flex:1;">
                                                <label style="display:flex;align-items:center;gap:12px;">
                                                    <input type="radio" name="selected_grid" value="<?php echo intval($gid); ?>" <?php echo (!empty($g['is_default']))? 'checked':''; ?> />
                                                    <span><?php echo htmlspecialchars($g['name']); ?> <?php echo (!empty($g['is_default']))? '<em>(par défaut)</em>':''; ?></span>
                                                </label>
                                            </div>
                                            <div>
                                                <form method="post" class="delete-grid-form" data-grid-id="<?php echo intval($gid); ?>">
                                                    <input type="hidden" name="action" value="delete_grid">
                                                    <input type="hidden" name="grid_id" value="<?php echo intval($gid); ?>">
                                                    <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                                                    <button class="button is-danger is-small" type="submit" <?php echo (!empty($g['is_default']))? 'disabled title="Ne peut pas supprimer la grille par défaut"':''; ?>>Supprimer</button>
                                                </form>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                    </div>
                                </div>
                            <?php endif; ?>
                        </div>
                    </section>
                    <footer class="modal-card-foot">
                        <button class="button" data-close>Fermer</button>
                    </footer>
                </div>
            </div>
    <script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.0/Sortable.min.js"></script>
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        // Initialisation SortableJS sur le tableau principal
        const tbody = document.getElementById('tableBody') || document.getElementById('grilleBody');
        if (tbody) {
            Sortable.create(tbody, {
                handle: '.drag-handle',
                animation: 150,
                onEnd: function (evt) {
                    // Après un drag, on envoie la nouvelle liste d'IDs au serveur
                    const ids = Array.from(tbody.querySelectorAll('tr[data-id]')).map(tr => parseInt(tr.getAttribute('data-id'))).filter(Boolean);
                    const csrf = '<?= isset($_SESSION['csrf_token']) ? $_SESSION['csrf_token'] : '' ?>';
                    // Envoi via fetch
                    fetch('grille.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                        body: 'action=save_order&order=' + encodeURIComponent(JSON.stringify(ids)) + '&csrf_token=' + encodeURIComponent(csrf)
                    }).then(r => { if (r.ok) location.reload(); else console.error('save_order failed'); });
                }
            });
        }

        // Gestion modal Grids
        const manageGridsBtn = document.getElementById('manageGridsBtn');
        const gridsModal = document.getElementById('gridsModal');
        if (manageGridsBtn && gridsModal) {
            manageGridsBtn.addEventListener('click', function(){ gridsModal.classList.add('is-active'); });
            gridsModal.querySelectorAll('[data-close]').forEach(function(el){ el.addEventListener('click', function(){ gridsModal.classList.remove('is-active'); }); });
        }

        // Modal d'impression (ouverture/fermeture simple)
        const showPrintModalBtn = document.getElementById('showPrintModal');
        const printModal = document.getElementById('printModal');
        if (showPrintModalBtn && printModal) {
            showPrintModalBtn.addEventListener('click', function() { printModal.classList.add('is-active'); });
            printModal.querySelectorAll('[data-close], .delete').forEach(function(el){ el.addEventListener('click', function(){ printModal.classList.remove('is-active'); }); });
            // Confirmation d'impression: construire un iframe imprimable contenant uniquement le tableau et options sélectionnées
            const confirmBtn = document.getElementById('confirmPrintBtn');
            if (confirmBtn) confirmBtn.addEventListener('click', function(){
                const form = document.getElementById('printOptionsForm');
                if (!form) { printModal.classList.remove('is-active'); setTimeout(()=>window.print(), 100); return; }

                // read options
                const showHeaders = !!form.querySelector('input[name="show_headers"]') && form.querySelector('input[name="show_headers"]').checked;
                const removeShadow = !!form.querySelector('input[name="remove_shadow"]') && form.querySelector('input[name="remove_shadow"]').checked;
                const cols = {
                    drag: !!form.querySelector('input[name="col_drag"]') && form.querySelector('input[name="col_drag"]').checked,
                    nom: !!form.querySelector('input[name="col_nom"]') && form.querySelector('input[name="col_nom"]').checked,
                    duree: !!form.querySelector('input[name="col_duree"]') && form.querySelector('input[name="col_duree"]').checked,
                    desc: !!form.querySelector('input[name="col_description"]') && form.querySelector('input[name="col_description"]').checked,
                    prix: !!form.querySelector('input[name="col_prix_ttc"]') && form.querySelector('input[name="col_prix_ttc"]').checked,
                    action: !!form.querySelector('input[name="col_action"]') && form.querySelector('input[name="col_action"]').checked
                };
                const show_h_lines = !!form.querySelector('input[name="show_h_lines"]') && form.querySelector('input[name="show_h_lines"]').checked;
                const show_v_lines = !!form.querySelector('input[name="show_v_lines"]') && form.querySelector('input[name="show_v_lines"]').checked;
                const titrePrincipal = form.querySelector('input[name="titre_principal"]') ? form.querySelector('input[name="titre_principal"]').value : '';
                const titreTableau = form.querySelector('input[name="titre_tableau"]') ? form.querySelector('input[name="titre_tableau"]').value : '';
                const texteDescriptif = form.querySelector('textarea[name="texte_descriptif"]') ? form.querySelector('textarea[name="texte_descriptif"]').value : '';

                const table = document.getElementById('grilleTable');
                if (!table) { printModal.classList.remove('is-active'); setTimeout(()=>window.print(), 100); return; }

                // Build an offscreen iframe
                const iframe = document.createElement('iframe');
                iframe.style.position = 'fixed'; iframe.style.right = '0'; iframe.style.bottom = '0'; iframe.style.width = '0'; iframe.style.height = '0'; iframe.style.border = '0'; iframe.id = 'printFrame';
                document.body.appendChild(iframe);

                const doc = iframe.contentWindow.document;
                const base = location.protocol + '//' + location.host;
                const bulmaHref = 'https://cdn.jsdelivr.net/npm/bulma@0.9.4/css/bulma.min.css';
                const styleHref = base + '/style/index-style.css';

                // Prepare HTML for iframe
                let html = '<!doctype html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">';
                html += '<link rel="stylesheet" href="' + bulmaHref + '">';
                html += '<link rel="stylesheet" href="' + styleHref + '">';
                // Print-specific adjustments to ensure clean output
                html += '<style>body{background:#fff;color:#222;padding:18px;font-family:Helvetica,Arial,sans-serif} .no-shadow .box,.no-shadow .card{box-shadow:none!important;background:transparent!important} .print-table{width:100%;border-collapse:collapse} .print-table th, .print-table td{padding:8px;text-align:left;border-bottom:1px solid #eee}</style>';
                html += '</head><body>';

                // header area (titles)
                if (titrePrincipal) html += '<h1 style="margin:0 0 10px 0;font-size:18px;">' + titrePrincipal.replace(/</g,'&lt;') + '</h1>';
                if (titreTableau) html += '<h2 style="margin:0 0 8px 0;font-size:16px;">' + titreTableau.replace(/</g,'&lt;') + '</h2>';
                if (texteDescriptif) html += '<p style="margin:0 0 12px 0;color:#444;">' + texteDescriptif.replace(/</g,'&lt;') + '</p>';

                // clone the table and sanitize according to options
                const clone = table.cloneNode(true);
                // remove headers if requested
                if (!showHeaders) {
                    const thead = clone.querySelector('thead'); if (thead) thead.remove();
                }

                // determine columns to remove (map columns order: 0:drag,1:nom,2:duree,3:description,4:prix,5:action)
                const colMap = {drag:0, nom:1, duree:2, desc:3, prix:4};
                const removeIdx = [];
                // remove action column (last) for printing only if not requested
                if (!cols.action) removeIdx.push(5);
                for (const k in colMap) if (!cols[k]) removeIdx.push(colMap[k]);
                // dedupe and sort descending
                const rem = Array.from(new Set(removeIdx)).sort((a,b)=>b-a);

                // remove headers and body cells by index
                rem.forEach(function(idx){
                    // remove th
                    clone.querySelectorAll('thead tr').forEach(function(tr){ const ths = tr.querySelectorAll('th'); if (ths[idx]) ths[idx].remove(); });
                    // remove tds
                    clone.querySelectorAll('tbody tr').forEach(function(tr){ const tds = tr.querySelectorAll('td'); if (tds[idx]) tds[idx].remove(); });
                });

                // apply classes
                clone.classList.add('print-table');
                if (removeShadow) clone.classList.add('no-shadow');
                if (show_h_lines) clone.classList.add('print-h-lines');
                if (show_v_lines) clone.classList.add('print-v-lines');

                // insert the cloned table into HTML
                html += '<div class="print-container">' + clone.outerHTML + '</div>';

                html += '</body></html>';
                doc.open(); doc.write(html); doc.close();

                // close modal then print iframe
                printModal.classList.remove('is-active');
                setTimeout(function(){
                    const w = iframe.contentWindow;
                    // afterprint cleanup
                    const cleanup = function(){ try{ document.body.removeChild(iframe); }catch(e){} };
                    if ('onafterprint' in w) {
                        w.addEventListener('afterprint', function(){ cleanup(); });
                    } else {
                        // fallback: remove after small delay
                        setTimeout(cleanup, 1500);
                    }
                    // trigger print on iframe
                    w.focus();
                    w.print();
                }, 150);
            });
        }

        // Column chooser: toggle visibility on page and sync with print modal form
        const toggleColumnChooser = document.getElementById('toggleColumnChooser');
        const columnChooser = document.getElementById('columnChooser');
        if (toggleColumnChooser && columnChooser) {
            toggleColumnChooser.addEventListener('click', function(){ columnChooser.style.display = columnChooser.style.display === 'none' ? 'block' : 'none'; });
            // bind toggles
            columnChooser.querySelectorAll('input[data-chooser]').forEach(function(cb){
                cb.addEventListener('change', function(){
                    const name = cb.getAttribute('data-chooser');
                    const checked = cb.checked;
                    // find the corresponding checkbox in the print modal form and set it
                    const formCb = document.querySelector('#printOptionsForm input[name="' + name + '"]');
                    if (formCb) formCb.checked = checked;
                    // show/hide column on the table (map indices)
                    const colMap = {col_drag:0, col_nom:1, col_duree:2, col_description:3, col_prix_ttc:4, col_action:5};
                    const idx = colMap[name];
                    if (typeof idx !== 'undefined') {
                        // header
                        document.querySelectorAll('#grilleTable thead tr').forEach(function(tr){ const ths = tr.querySelectorAll('th'); if (ths[idx]) ths[idx].style.display = checked ? '' : 'none'; });
                        // body
                        document.querySelectorAll('#grilleTable tbody tr').forEach(function(tr){ const tds = tr.querySelectorAll('td'); if (tds[idx]) tds[idx].style.display = checked ? '' : 'none'; });
                    }
                });
            });
            // initialize chooser from current form state
            document.querySelectorAll('#printOptionsForm input[type="checkbox"]').forEach(function(fcb){ const name = fcb.name; const chooser = columnChooser.querySelector('input[data-chooser="'+name+'"]'); if (chooser) chooser.checked = fcb.checked; });
        }
    });

    // Fonction générale pour éditer une prestation dans le formulaire (appelée depuis les boutons Action)
    function editInForm(id, nom, duree, description, prix, emoji) {
        const nameInput = document.querySelector('input[name="nom"]');
        const dureeInput = document.querySelector('input[name="duree"]');
        const descInput = document.querySelector('textarea[name="description"]');
        const prixInput = document.querySelector('input[name="prix_ttc"]');
        const emojiInput = document.querySelector('input[name="emoji"]');
        if (nameInput) nameInput.value = nom || '';
        if (dureeInput) dureeInput.value = duree || '';
        if (descInput) descInput.value = description || '';
        if (prixInput) prixInput.value = prix || '';
        if (emojiInput) emojiInput.value = (emoji && emoji.trim()) ? emoji : '🩺';

        // Prépare le champ action pour édition
        var actionInput = document.getElementById('formActionInput');
        if (actionInput) actionInput.value = 'edit_in_form';
        var submitLabel = document.getElementById('formSubmitLabel');
        if (submitLabel) submitLabel.textContent = 'Modifier';

        // Prepare hidden index/id field
        let idx = document.querySelector('input[name="edit_index"]');
        if (!idx) {
            idx = document.createElement('input'); idx.type = 'hidden'; idx.name = 'edit_index';
            const form = document.querySelector('.box form'); if (form) form.appendChild(idx);
        }
        idx.value = id;
        if (idx) idx.scrollIntoView({ behavior: 'smooth' });
    }

    // Remettre le formulaire en mode ajout après soumission ou reset
    document.querySelector('.box form').addEventListener('reset', function() {
        var actionInput = document.getElementById('formActionInput');
        if (actionInput) actionInput.value = 'add';
        var submitLabel = document.getElementById('formSubmitLabel');
        if (submitLabel) submitLabel.textContent = 'Ajouter';
        let idx = document.querySelector('input[name="edit_index"]');
        if (idx) idx.remove();
    });
    // Envoi manuel de l'ordre (bouton Enregistrer l'ordre)
    function saveOrder() {
        const tbody = document.getElementById('tableBody');
        if (!tbody) return;
        const ids = Array.from(tbody.querySelectorAll('tr[data-id]')).map(tr => parseInt(tr.getAttribute('data-id'))).filter(Boolean);
        const csrf = '<?= isset($_SESSION['csrf_token']) ? $_SESSION['csrf_token'] : '' ?>';
        fetch('grille.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: 'action=save_order&order=' + encodeURIComponent(JSON.stringify(ids)) + '&csrf_token=' + encodeURIComponent(csrf)
        }).then(r => { if (r.ok) location.reload(); else alert('Erreur lors de la sauvegarde de l\'ordre'); }).catch(e=>{ console.error(e); alert('Erreur réseau'); });
    }
    </script>
    <script>
    (function(){
        // Auto-dismiss flash notifications
        document.querySelectorAll('.notification').forEach(function(n){ setTimeout(function(){ n.style.transition='opacity 0.4s'; n.style.opacity=0; setTimeout(()=>n.remove(),450); }, 4500); });

        // AJAX deletion for grids
        document.querySelectorAll('.delete-grid-form').forEach(function(f){
            f.addEventListener('submit', function(ev){
                ev.preventDefault();
                if (!confirm('Confirmer la suppression ? Cette opération supprimera aussi les prestations liées.')) return;
                const formData = new FormData(f);
                fetch('grille.php', {
                    method: 'POST',
                    headers: { 'X-Requested-With': 'XMLHttpRequest' },
                    body: formData
                }).then(resp => resp.json()).then(json => {
                    if (json && json.ok) {
                        // remove the grid row from modal
                        const gid = f.getAttribute('data-grid-id');
                        f.closest('div').remove();
                        // show temporary notification
                        const n = document.createElement('div'); n.className='notification is-success'; n.textContent='Grille supprimée.';
                        document.querySelector('.modal-card-body .content').prepend(n);
                        setTimeout(()=>{ n.style.transition='opacity 0.4s'; n.style.opacity=0; setTimeout(()=>n.remove(),450); }, 2500);
                    } else {
                        alert('Erreur lors de la suppression: ' + (json.error || 'unk'));
                    }
                }).catch(err => { console.error(err); alert('Erreur réseau'); });
            });
        });

        // AJAX rename for grids
        document.querySelectorAll('.rename-btn').forEach(function(btn){
            btn.addEventListener('click', function(){
                const gid = btn.getAttribute('data-grid-id');
                const input = document.querySelector('.rename-input[data-grid-id="'+gid+'"]');
                if (!input) return; const newName = input.value.trim(); if (!newName) { alert('Entrez un nouveau nom'); return; }
                const fd = new FormData(); fd.append('action','rename_grid'); fd.append('grid_id', gid); fd.append('new_name', newName); fd.append('csrf_token', '<?= isset($_SESSION['csrf_token']) ? $_SESSION['csrf_token'] : '' ?>');
                fetch('grille.php', { method:'POST', headers: {'X-Requested-With':'XMLHttpRequest'}, body: fd }).then(r=>r.json()).then(j=>{
                    if (j && j.ok) {
                        // update UI
                        const label = document.querySelector('.grid-name[data-grid-id="'+gid+'"]'); if (label) label.textContent = newName;
                        input.value = '';
                        const n = document.createElement('div'); n.className='notification is-success'; n.textContent='Grille renommée.'; document.querySelector('.modal-card-body .content').prepend(n);
                        setTimeout(()=>{ n.style.transition='opacity 0.4s'; n.style.opacity=0; setTimeout(()=>n.remove(),450); }, 2500);
                    } else alert('Erreur lors du renommage');
                }).catch(e=>{ console.error(e); alert('Erreur réseau'); });
            });
        });

        // AJAX duplicate for grids
        document.querySelectorAll('.duplicate-btn').forEach(function(btn){
            btn.addEventListener('click', function(){
                const gid = btn.getAttribute('data-grid-id');
                const input = document.querySelector('.duplicate-input[data-grid-id="'+gid+'"]');
                if (!input) return; const newName = input.value.trim(); if (!newName) { alert('Entrez un nom pour la copie'); return; }
                const fd = new FormData(); fd.append('action','duplicate_grid'); fd.append('grid_id', gid); fd.append('new_name', newName); fd.append('csrf_token', '<?= isset($_SESSION['csrf_token']) ? $_SESSION['csrf_token'] : '' ?>');
                fetch('grille.php', { method:'POST', headers: {'X-Requested-With':'XMLHttpRequest'}, body: fd }).then(r=>r.json()).then(j=>{
                    if (j && j.ok) {
                        // add new grid row to modal UI
                        const container = document.querySelector('.modal-card-body .content');
                        const div = document.createElement('div'); div.className='grid-row'; div.style.display='flex'; div.style.alignItems='center'; div.style.justifyContent='space-between'; div.style.marginBottom='6px';
                        const left = document.createElement('div'); left.style.flex='1'; left.style.marginRight='8px'; const strong = document.createElement('strong'); strong.className='grid-name'; strong.setAttribute('data-grid-id', j.new_id); strong.textContent = newName; left.appendChild(strong);
                        const right = document.createElement('div'); right.style.display='flex'; right.style.gap='8px'; right.style.alignItems='center';
                        // rename input/button for new row
                        const rn = document.createElement('input'); rn.className='input is-small rename-input'; rn.setAttribute('data-grid-id', j.new_id); rn.placeholder='Nouveau nom'; rn.style.width='220px';
                        const rb = document.createElement('button'); rb.className='button is-small is-info rename-btn'; rb.setAttribute('data-grid-id', j.new_id); rb.textContent='Renommer';
                        const dn = document.createElement('input'); dn.className='input is-small duplicate-input'; dn.setAttribute('data-grid-id', j.new_id); dn.placeholder='Nom copie'; dn.style.width='160px';
                        const db = document.createElement('button'); db.className='button is-small is-primary duplicate-btn'; db.setAttribute('data-grid-id', j.new_id); db.textContent='Dupliquer';
                        const delForm = document.createElement('form'); delForm.method='post'; delForm.className='delete-grid-form'; delForm.setAttribute('data-grid-id', j.new_id);
                        delForm.innerHTML = '<input type="hidden" name="action" value="delete_grid">'+
                            '<input type="hidden" name="grid_id" value="'+j.new_id+'">'+
                            '<input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">'+
                            '<button class="button is-danger is-small" type="submit">Supprimer</button>';
                        right.appendChild(rn); right.appendChild(rb); right.appendChild(dn); right.appendChild(db); right.appendChild(delForm);
                        div.appendChild(left); div.appendChild(right); container.appendChild(div);
                        input.value='';
                        // rebind events for new buttons/forms (simpler: reload modal bindings by reloading the modal or re-run small binder)
                        // quick binder for new elements:
                        rb.addEventListener('click', function(){ rb.disabled=true; setTimeout(()=>rb.disabled=false,500); /* will be handled by global binder if re-opened */ });
                        db.addEventListener('click', function(){ db.disabled=true; setTimeout(()=>db.disabled=false,500); });
                        const n = document.createElement('div'); n.className='notification is-success'; n.textContent='Grille dupliquée.'; document.querySelector('.modal-card-body .content').prepend(n);
                        setTimeout(()=>{ n.style.transition='opacity 0.4s'; n.style.opacity=0; setTimeout(()=>n.remove(),450); }, 2500);
                    } else alert('Erreur lors de la duplication');
                }).catch(e=>{ console.error(e); alert('Erreur réseau'); });
            });
        });
    })();
    </script>
    <script>
    (function(){
        const gridsModal = document.getElementById('gridsModal');
        const renameModal = document.getElementById('renameGridModal');
        const duplicateModal = document.getElementById('duplicateGridModal');
        const renameBtn = document.getElementById('renameGridBtn');
        const duplicateBtn = document.getElementById('duplicateGridBtn');
    const defaultBtn = document.getElementById('defaultGridBtn');
    const defaultModal = document.getElementById('defaultGridModal');

        function getSelectedGridId() {
            const sel = document.querySelector('.modal-card-body input[name="selected_grid"]:checked');
            return sel ? sel.value : null;
        }

        if (renameBtn && gridsModal && renameModal) {
            renameBtn.addEventListener('click', function(){
                const gid = getSelectedGridId(); if (!gid) { alert('Sélectionnez une grille'); return; }
                const sel = document.getElementById('rename_grid_select'); if (sel) sel.value = gid;
                document.getElementById('rename_grid_name').value = '';
                renameModal.classList.add('is-active');
            });
        }
        if (duplicateBtn && gridsModal && duplicateModal) {
            duplicateBtn.addEventListener('click', function(){
                const gid = getSelectedGridId(); if (!gid) { alert('Sélectionnez une grille'); return; }
                const sel = document.getElementById('duplicate_grid_select'); if (sel) sel.value = gid;
                document.getElementById('duplicate_grid_name').value = '';
                duplicateModal.classList.add('is-active');
            });
        }
        if (defaultBtn && gridsModal && defaultModal) {
            defaultBtn.addEventListener('click', function(){
                defaultModal.classList.add('is-active');
            });
        }

        // submit rename
        const renameConfirm = document.getElementById('renameGridConfirm');
        if (renameConfirm) renameConfirm.addEventListener('click', function(){
            const form = document.getElementById('renameGridForm'); const formData = new FormData(form);
            fetch('grille.php', { method:'POST', headers:{ 'X-Requested-With':'XMLHttpRequest' }, body: formData }).then(r=>r.json()).then(j=>{
                if (j && j.ok) {
                    showModalNotice(renameModal, 'Grille renommée.', 1200, function(){ location.reload(); });
                } else { showModalNotice(renameModal, 'Erreur lors du renommage', 1800); }
            }).catch(e=>{ console.error(e); alert('Erreur réseau'); });
        });

        // submit duplicate
        const dupConfirm = document.getElementById('duplicateGridConfirm');
        if (dupConfirm) dupConfirm.addEventListener('click', function(){
            const form = document.getElementById('duplicateGridForm'); const formData = new FormData(form);
            fetch('grille.php', { method:'POST', headers:{ 'X-Requested-With':'XMLHttpRequest' }, body: formData }).then(r=>r.json()).then(j=>{
                if (j && j.ok) {
                    showModalNotice(duplicateModal, 'Grille dupliquée.', 1200, function(){ location.reload(); });
                } else { showModalNotice(duplicateModal, 'Erreur lors de la duplication', 1800); }
            }).catch(e=>{ console.error(e); alert('Erreur réseau'); });
        });

        // set default grid
        (function(){
            const btn = document.getElementById('defaultGridConfirm');
            if (btn) {
                btn.addEventListener('click', function(e){
                    e.preventDefault();
                    const sel = document.getElementById('default_grid_select');
                    const gid = sel ? sel.value : null;
                    if (!gid) return;
                    const fd = new FormData();
                    fd.append('action','set_default_grid');
                    fd.append('grid_id', gid);
                    fd.append('csrf_token','<?= $_SESSION['csrf_token'] ?>');
                    fetch(window.location.href, {method:'POST', body:fd, headers:{'X-Requested-With':'XMLHttpRequest'}})
                        .then(r=>r.json()).then(j=>{ if(j && (j.ok || j.success)) { showModalNotice(defaultModal, 'Grille définie par défaut.', 1000, function(){ location.reload(); }); } else { showModalNotice(defaultModal, 'Erreur', 1600); } })
                        .catch(()=>showModalNotice(defaultModal, 'Erreur réseau', 1600));
                });
            }
        })();
        // helper to show a notice inside a modal and optionally call a callback after delay
        function showModalNotice(modalEl, text, delay, cb) {
            try {
                const body = modalEl.querySelector('.modal-card-body .content') || modalEl.querySelector('.modal-card-body') || modalEl;
                const n = document.createElement('div'); n.className='notification is-primary'; n.textContent = text;
                n.style.marginBottom='8px'; body.insertBefore(n, body.firstChild);
                setTimeout(function(){ n.style.transition='opacity 0.35s'; n.style.opacity=0; setTimeout(()=>n.remove(),400); if (typeof cb === 'function') cb(); }, delay || 1200);
            } catch(e){ if (typeof cb === 'function') cb(); }
        }
        document.querySelectorAll('[data-close]').forEach(function(el){ el.addEventListener('click', function(){ el.closest('.modal').classList.remove('is-active'); }); });
    })();
    </script>
    <script>
    // Toggle compact debug box
    (function(){
        const toggle = document.getElementById('debugToggle');
        const content = document.getElementById('debugContent');
        const arrow = document.getElementById('debugArrow');
        if (toggle && content && arrow) {
            toggle.addEventListener('click', function(){
                const collapsed = content.classList.toggle('collapsed');
                arrow.style.transform = collapsed ? 'rotate(0deg)' : 'rotate(180deg)';
                toggle.querySelector('span:nth-child(2)').textContent = collapsed ? 'Afficher' : 'Masquer';
            });
        }
    })();
    </script>
