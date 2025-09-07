<?php
// Simple viewer des tables de la base neosphere
// Sécurité minimale : à améliorer (protégé idéalement par auth admin)
require __DIR__ . '/inc/db.php';

if (!isset($pdo) || !$pdo) {
    http_response_code(500);
    echo '<h1>Connexion DB indisponible</h1>';
    exit;
}

$dbName = $pdo->query('select database()')->fetchColumn();
$tableParam = isset($_GET['table']) ? $_GET['table'] : null;
$maxPreview = isset($_GET['limit']) ? max(1, (int)$_GET['limit']) : 50;

function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8'); }

// Récupère les tables
$tables = $pdo->query("SHOW FULL TABLES WHERE Table_Type='BASE TABLE'")->fetchAll(PDO::FETCH_NUM);
$tableNames = array_map(fn($r)=>$r[0], $tables);

// Validation du param table
if ($tableParam && !in_array($tableParam, $tableNames, true)) {
    http_response_code(400);
    echo '<p>Table inconnue.</p>';
    $tableParam = null;
}

?><!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="utf-8" />
<title>Dump DB - <?=h($dbName)?></title>
<style>
body{font-family: system-ui,Segoe UI,Arial,sans-serif;margin:1.2rem;}
code,pre{font-family: ui-monospace,Consolas,monospace;font-size: .85rem;}
summary{cursor:pointer;font-weight:600;margin-top:1rem;}
table{border-collapse:collapse;margin:.5rem 0;min-width:60%;box-shadow:0 0 0 1px #ccc;}
th,td{border:1px solid #ccc;padding:.35rem .5rem;vertical-align:top;}
th{background:#f5f5f5;}
small.muted{color:#666;font-weight:400;}
.badge{display:inline-block;background:#0366d6;color:#fff;border-radius:12px;padding:0 .5em;font-size:.75rem;line-height:1.4;margin-left:.35em;}
header{display:flex;flex-wrap:wrap;gap:1rem;align-items:baseline;}
nav a{margin-right:.65rem;}
.alert{background:#fffbdd;border:1px solid #f0e6a0;padding:.5rem .75rem;border-radius:4px;}
</style>
</head>
<body>
<?php include_once __DIR__ . '/../menu.php'; ?>
<header>
  <h1 style="margin:0">Base: <?=h($dbName)?></h1>
  <nav>
    <?php foreach($tableNames as $t): ?>
      <a href="?table=<?=urlencode($t)?>"<?= $t===$tableParam?' style="font-weight:700"':'';?>><?=h($t)?></a>
    <?php endforeach; ?>
  </nav>
</header>
<hr />
<?php if(!$tableParam): ?>
  <p>Sélectionnez une table pour en voir le contenu. Aperçu des tailles :</p>
  <table>
    <thead><tr><th>Table</th><th>Lignes</th><th>Premières colonnes</th></tr></thead>
    <tbody>
    <?php foreach($tableNames as $t):
        $count = $pdo->query("SELECT COUNT(*) FROM `".$t."`")->fetchColumn();
        $colsStmt = $pdo->query("SHOW COLUMNS FROM `".$t."`");
        $cols = [];
        foreach($colsStmt as $c){ $cols[] = $c['Field']; if(count($cols)>=5) break; }
    ?>
      <tr>
        <td><a href="?table=<?=urlencode($t)?>"><?=h($t)?></a></td>
        <td style="text-align:right"><?=h($count)?></td>
        <td><?=h(implode(', ', $cols))?><?= $count> $maxPreview? ' <span class="badge">apercu</span>':''?></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
  <p class="alert">Paramètre ?limit=NN pour ajuster la limite d'aperçu (actuelle: <?=h($maxPreview)?>).</p>
<?php else: ?>
  <?php
    $count = $pdo->query("SELECT COUNT(*) FROM `".$tableParam."`")->fetchColumn();
    echo '<h2>Table '.h($tableParam).' <small class="muted">('.$count.' lignes)</small></h2>';
    $rows = $pdo->query("SELECT * FROM `".$tableParam."` ORDER BY 1 DESC LIMIT " . (int)$maxPreview)->fetchAll();
    if(!$rows){ echo '<p>Aucune ligne.</p>'; }
    else {
        echo '<p>Aperçu ('.min($count,$maxPreview).' / '.$count.') — changer ?limit= pour plus.</p>';
        echo '<table><thead><tr>'; 
        foreach(array_keys($rows[0]) as $col){ echo '<th>'.h($col).'</th>'; }
        echo '</tr></thead><tbody>';
        foreach($rows as $r){
            echo '<tr>';
            foreach($r as $v){
                if($v===null){ $cell = '<em style="color:#888">NULL</em>'; }
                elseif(is_string($v) && strlen($v)>160){
                    $preview = substr($v,0,160).'…';
                    $cell = '<details><summary>'.h($preview).'</summary><pre>'.h($v).'</pre></details>';
                } else {
                    $cell = h($v);
                }
                echo '<td>'.$cell.'</td>';
            }
            echo '</tr>';
        }
        echo '</tbody></table>';
    }
  ?>
  <p><a href="./db_dump.php">&larr; retour liste tables</a></p>
<?php endif; ?>
<hr />
<footer><small>Debug viewer &mdash; à retirer ou protéger en production.</small></footer>
</body>
</html>
