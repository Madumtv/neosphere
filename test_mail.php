<?php
// Page de test SMTP / PHPMailer – ne PAS laisser en prod publique sans protection
// Ajoute éventuellement une protection simple par IP ou clé query.

require __DIR__.'/vendor/autoload.php';
$config = require __DIR__.'/inc/config.mail.php';
$local = __DIR__.'/inc/config.mail.local.php';
if (file_exists($local)) {
    $localCfg = require $local;
    if (is_array($localCfg)) { $config = array_replace_recursive($config, $localCfg); }
}

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

$action = $_POST['action'] ?? '';
$result = null; $error = null; $debugLog = '';

function h($s){return htmlspecialchars($s, ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8');}

if ($action === 'probe') {
    $mail = new PHPMailer(true);
    try {
        $mail->isSMTP();
        $mail->Host       = $config['smtp']['host'];
        $mail->SMTPAuth   = true;
        $mail->Username   = $config['smtp']['user'];
        $mail->Password   = $config['smtp']['pass'];
        $secure = strtoupper($config['smtp']['secure'] ?? 'STARTTLS');
        if ($secure === 'SMTPS') {
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
            $mail->Port = $config['smtp']['port'] ?: 465;
        } else {
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port = $config['smtp']['port'] ?: 587;
        }
        $mail->CharSet    = 'UTF-8';
        $mail->Timeout    = 15;
        $mail->SMTPDebug  = 2;
        $buffer = '';
        $mail->Debugoutput = function($str) use (&$buffer){ $buffer .= date('H:i:s ').$str."\n"; };
        $mail->setFrom($config['smtp']['from'], $config['smtp']['fromName']);
        $mail->addAddress($config['smtp']['to'], $config['smtp']['toName']);
        $mail->Subject = 'Test SMTP (probe)';
        $mail->Body    = 'Test simple';
        $mail->AltBody = 'Test simple';
        $mail->send();
        $result = 'ENVOI OK';
        $debugLog = $buffer;
    } catch (Exception $e) {
        $error = 'PHPMailer Exception: '.$e->getMessage();
        if(!empty($buffer)) $debugLog = $buffer;
    } catch (Throwable $t) {
        $error = 'Throwable: '.$t->getMessage();
    }
}
?><!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="utf-8">
<title>Test SMTP</title>
<style>
body{font-family:system-ui,-apple-system,Segoe UI,Roboto,Arial,sans-serif;margin:24px;background:#f5f7fa;color:#222;}
fieldset{border:1px solid #d0d7de;padding:16px;border-radius:6px;background:#fff;max-width:860px;}
legend{font-weight:600;padding:0 8px;}
pre{background:#0d1117;color:#eee;padding:12px;border-radius:6px;overflow:auto;max-height:420px;font-size:12px;}
.code-kv{font-family:monospace;font-size:13px;}
.status-ok{color:#2e7d32;font-weight:600;}
.status-err{color:#c62828;font-weight:600;}
.btn{cursor:pointer;background:#0366d6;color:#fff;border:none;padding:10px 18px;border-radius:4px;font-size:14px;}
.btn:disabled{opacity:.6;cursor:not-allowed;}
.small{font-size:12px;color:#555;}
.warn{color:#b26a00;}
</style>
</head>
<body>
<h1>Diagnostic SMTP / PHPMailer</h1>
<p class="small">Ne déployez pas cette page en public. Supprimez-la une fois les tests terminés.</p>
<form method="post">
<fieldset>
<legend>Configuration détectée</legend>
<div class="code-kv">Host: <?=h($config['smtp']['host']??'?')?> | Port: <?=h($config['smtp']['port']??'?')?> | Secure: <?=h($config['smtp']['secure']??'?')?></div>
<div class="code-kv">User: <?=h($config['smtp']['user']??'?')?> | From: <?=h($config['smtp']['from']??'?')?> → To: <?=h($config['smtp']['to']??'?')?></div>
<div class="code-kv">Pass défini: <?= empty($config['smtp']['pass'])?'<span class="warn">NON</span>':'OUI' ?></div>
<button class="btn" name="action" value="probe">Tester l'envoi SMTP</button>
</fieldset>
</form>
<?php if($result): ?>
<p class="status-ok">Résultat: <?=$result?></p>
<?php endif; ?>
<?php if($error): ?>
<p class="status-err">Erreur: <?=h($error)?></p>
<?php endif; ?>
<?php if($debugLog): ?>
<h2>Trace SMTP</h2>
<pre><?=h($debugLog)?></pre>
<?php endif; ?>
<hr>
<p class="small">Astuce: si échec TLS sur 465, essayez port 587 + STARTTLS, ou vérifiez firewall/cred.</p>
</body>
</html>
