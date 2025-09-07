<?php
// admin/mails_envoyes.php : affichage des mails envoyés
require_once __DIR__ . '/inc/db.php';
if (!isset($_SESSION)) session_start();
if (!isset($_SESSION['is_admin']) || !$_SESSION['is_admin']) {
    header('Location: ../membre/login.php'); exit;
}
$rows = [];
try {
    $stmt = $pdo->query("SELECT * FROM sent_mails ORDER BY sent_at DESC LIMIT 100");
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) { $rows = []; }
?><!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Mails envoyés</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bulma@0.9.4/css/bulma.min.css">
</head>
<body>
<div class="container" style="margin-top:40px;">
    <h1 class="title is-3">Mails envoyés</h1>
    <table class="table is-striped is-fullwidth">
        <thead>
            <tr>
                <th>Date</th>
                <th>Expéditeur</th>
                <th>Destinataire</th>
                <th>Sujet</th>
                <th>IP</th>
                <th>User-Agent</th>
                <th>Contenu</th>
            </tr>
        </thead>
        <tbody>
        <?php foreach($rows as $mail): ?>
            <tr>
                <td><?= htmlspecialchars($mail['sent_at']) ?></td>
                <td><?= htmlspecialchars($mail['sender']) ?></td>
                <td><?= htmlspecialchars($mail['recipient']) ?></td>
                <td><?= htmlspecialchars($mail['subject']) ?></td>
                <td><?= htmlspecialchars($mail['ip']) ?></td>
                <td><span title="<?= htmlspecialchars($mail['user_agent']) ?>">UA</span></td>
                <td><button class="button is-small is-info" onclick="showMailBody(this)">Voir</button><div class="mail-body" style="display:none;max-width:500px;"><?= $mail['body'] ?></div></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    <a href="index.php" class="button is-light">Retour admin</a>
</div>
<script>
function showMailBody(btn){
    var div = btn.nextElementSibling;
    if(div.style.display==="none"||!div.style.display){
        div.style.display="block";
        btn.textContent="Masquer";
        div.style.background="#fffbe6";
        div.style.border="1px solid #ffe58f";
        div.style.padding="12px";
        div.style.fontSize="14px";
    }else{
        div.style.display="none";
        btn.textContent="Voir";
    }
}
</script>
</body>
</html>
