<?php
require_once __DIR__ . '/inc/db.php';
// Création de la table sent_mails si elle n'existe pas
if (isset($pdo) && $pdo instanceof PDO) {
  try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS sent_mails (
      id INT AUTO_INCREMENT PRIMARY KEY,
      sender VARCHAR(255),
      recipient VARCHAR(255),
      subject VARCHAR(255),
      body TEXT,
      sent_at DATETIME,
      ip VARCHAR(64),
      user_agent TEXT
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
  } catch (Exception $e) { /* ignore */ }
}
// Endpoint d'envoi du formulaire de contact via PHPMailer (JSON)
// Robustesse: buffer pour capturer sorties parasites et renvoyer JSON propre
ob_start();
// Régler le fuseau horaire (UTC+1 / +2 DST)
date_default_timezone_set('Europe/Brussels');
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'Méthode non autorisée']);
    exit;
}

$isAjax = (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest');

// Limite taille
$maxBytes = 64 * 1024;
if(!empty($_SERVER['CONTENT_LENGTH']) && (int)$_SERVER['CONTENT_LENGTH'] > $maxBytes){
    http_response_code(413);
    echo json_encode(['ok'=>false,'error'=>'Payload trop volumineux']);
    exit;
}

// Champs
$name    = trim($_POST['name'] ?? '');
$email   = trim($_POST['email'] ?? '');
$phone   = trim($_POST['phone'] ?? '');
$type    = trim($_POST['type'] ?? '');
$message = trim($_POST['message'] ?? '');

$errors = [];
if ($name === '' || mb_strlen($name) < 2) { $errors[] = 'Nom invalide'; }
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) { $errors[] = 'Email invalide'; }
if ($message === '' || mb_strlen($message) < 10) { $errors[] = 'Message trop court'; }
if (mb_strlen($message) > 5000) { $errors[] = 'Message trop long'; }
if ($phone && !preg_match('/^[0-9 +().-]{6,30}$/', $phone)) { $errors[] = 'Téléphone invalide'; }

if ($errors) {
    echo json_encode(['ok' => false, 'error' => implode(', ', $errors)]);
    exit;
}

session_start();
$now = time();
// Anti-flood désactivé pour les tests (remettre le bloc si besoin de limiter)
// Ancien bloc:
// if (!isset($_SESSION['last_contact_submit'])) { $_SESSION['last_contact_submit'] = 0; }
// if (($now - $_SESSION['last_contact_submit']) < 15) {
//     echo json_encode(['ok'=>false,'error'=>'Veuillez patienter avant un nouvel envoi']);
//     exit;
// }
$_SESSION['last_contact_submit'] = $now;

$ip  = $_SERVER['REMOTE_ADDR'] ?? '?';
$ua  = $_SERVER['HTTP_USER_AGENT'] ?? '?';
// Horodatage avec fuseau
$nowDt = new DateTimeImmutable('now', new DateTimeZone('Europe/Brussels'));
$nowFormatted = $nowDt->format('Y-m-d H:i:s T');

$h = fn($v)=>nl2br(htmlspecialchars($v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'));

$htmlBody = <<<HTML
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="utf-8">
<title>Message de contact</title>
</head>
<body style="margin:0;padding:0;background:#f6f8fa;font:14px/1.5 -apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Arial,sans-serif;color:#222;">
  <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#f6f8fa;padding:24px;">
    <tr>
      <td align="center">
        <table role="presentation" width="600" cellpadding="0" cellspacing="0" style="max-width:600px;width:100%;background:#ffffff;border:1px solid #e2e6ea;border-radius:8px;overflow:hidden;">
          <tr>
            <td style="background:#222;color:#fff;padding:16px 24px;font-size:18px;font-weight:600;">
              Nouveau message de contact – Néosphère
            </td>
          </tr>
          <tr>
            <td style="padding:24px;">
              <h2 style="margin:0 0 16px;font-size:20px;color:#222;">Détails</h2>
              <table role="presentation" cellpadding="0" cellspacing="0" style="width:100%;border-collapse:collapse;font-size:14px;">
                <tr>
                  <td style="padding:6px 8px;font-weight:600;width:140px;background:#fafbfc;border:1px solid #e5e9ec;">Nom</td>
                  <td style="padding:6px 8px;border:1px solid #e5e9ec;">{$h($name)}</td>
                </tr>
                <tr>
                  <td style="padding:6px 8px;font-weight:600;background:#fafbfc;border:1px solid #e5e9ec;">Email</td>
                  <td style="padding:6px 8px;border:1px solid #e5e9ec;">{$h($email)}</td>
                </tr>
                <tr>
                  <td style="padding:6px 8px;font-weight:600;background:#fafbfc;border:1px solid #e5e9ec;">Téléphone</td>
                  <td style="padding:6px 8px;border:1px solid #e5e9ec;">{$h($phone ?: '—')}</td>
                </tr>
                <tr>
                  <td style="padding:6px 8px;font-weight:600;background:#fafbfc;border:1px solid #e5e9ec;">Type</td>
                  <td style="padding:6px 8px;border:1px solid #e5e9ec;">{$h($type ?: 'Non précisé')}</td>
                </tr>
                <tr>
                  <td style="padding:6px 8px;font-weight:600;background:#fafbfc;border:1px solid #e5e9ec;">Date</td>
                  <td style="padding:6px 8px;border:1px solid #e5e9ec;">{$h($nowFormatted)}</td>
                </tr>
                <tr>
                  <td style="padding:6px 8px;font-weight:600;background:#fafbfc;border:1px solid #e5e9ec;">IP</td>
                  <td style="padding:6px 8px;border:1px solid #e5e9ec;">{$h($ip)}</td>
                </tr>
                <tr>
                  <td style="padding:6px 8px;font-weight:600;background:#fafbfc;border:1px solid #e5e9ec;">Agent</td>
                  <td style="padding:6px 8px;border:1px solid #e5e9ec;">{$h(mb_substr($ua,0,180))}</td>
                </tr>
              </table>

              <h3 style="margin:24px 0 8px;font-size:16px;">Message</h3>
              <div style="background:#fafbfc;border:1px solid #e5e9ec;padding:12px;border-radius:4px;white-space:pre-wrap;">
                {$h($message)}
              </div>

              <p style="margin:24px 0 0;font-size:12px;color:#666;">
                Email automatique envoyé depuis le formulaire du site. Ne répondez que si pertinent.
              </p>
            </td>
          </tr>
          <tr>
            <td style="background:#f0f2f4;padding:12px 24px;font-size:12px;color:#555;text-align:center;">
              © Néosphère – Généré automatiquement
            </td>
          </tr>
        </table>
      </td>
    </tr>
  </table>
</body>
</html>
HTML;

$plainBody =
"Nouvelle demande via le site\n".
"----------------------------------------\n".
"Nom: $name\n".
"Email: $email\n".
"Téléphone: ".($phone ?: '—')."\n".
"Type: ".($type ?: 'Non précisé')."\n".
"Date: $nowFormatted\n".
"IP: $ip\n".
"User-Agent: $ua\n".
"----------------------------------------\n".
"Message:\n$message\n";

// Subject commun aux deux formats
$emailSubject = 'Contact site: '.($type ?: 'Message');

// Activation PHPMailer via Composer (avant toute utilisation de la classe)
require __DIR__ . '/vendor/autoload.php';
use PHPMailer\PHPMailer\PHPMailer; use PHPMailer\PHPMailer\Exception; // import
$usePhpMailer = true;
// Charger configuration SMTP (avec surcharge locale éventuelle)
$mailCfg = require __DIR__ . '/inc/config.mail.php';
if (file_exists(__DIR__.'/inc/config.mail.local.php')) {
  $local = require __DIR__ . '/inc/config.mail.local.php';
  $mailCfg = array_replace_recursive($mailCfg, is_array($local)?$local:[]);
}

try {
if ($usePhpMailer) {
    $smtpHost   = $mailCfg['smtp']['host'];
    $smtpUser   = $mailCfg['smtp']['user'];
    $smtpPass   = $mailCfg['smtp']['pass'];
    $smtpPort   = (int)$mailCfg['smtp']['port'];
    $smtpSecure = $mailCfg['smtp']['secure'];

  $mail = new PHPMailer(true);
  $mail->isSMTP();
    $mail->Host = $smtpHost;
    $mail->SMTPAuth = true;
    $mail->Username = $smtpUser;
    $mail->Password = $smtpPass;
    if(strtoupper((string)$smtpSecure)==='SMTPS') {
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
        if($smtpPort===587) { $smtpPort = 465; }
    } else {
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
    }
    $mail->Port = $smtpPort;
    $mail->CharSet = 'UTF-8';
    $mail->setFrom($mailCfg['smtp']['from'], $mailCfg['smtp']['fromName']);
    $mail->addAddress($mailCfg['smtp']['to'], $mailCfg['smtp']['toName']);
    $mail->addReplyTo($email, $name ?: $email);
  $mail->isHTML(true);
  $mail->Subject = $emailSubject;
  $mail->Body = $htmlBody;
  $mail->AltBody = $plainBody;
    if(!empty($mailCfg['debug']['enable_smtp_debug'])){
        $mail->SMTPDebug = 2; // sort sur STDERR (à éviter en prod)
    }
  $mail->send();
  // Enregistrer le mail dans la base
  if (isset($pdo) && $pdo instanceof PDO) {
    try {
      $stmt = $pdo->prepare("INSERT INTO sent_mails (sender, recipient, subject, body, sent_at, ip, user_agent) VALUES (:sender, :recipient, :subject, :body, :sent_at, :ip, :ua)");
      $stmt->execute([
        ':sender' => $mailCfg['smtp']['from'],
        ':recipient' => $mailCfg['smtp']['to'],
        ':subject' => $emailSubject,
        ':body' => $htmlBody,
        ':sent_at' => date('Y-m-d H:i:s'),
        ':ip' => $ip,
        ':ua' => $ua
      ]);
    } catch (Exception $e) { /* ignore */ }
  }
} else {
    $headers = [];
    $headers[] = 'From: Site Néosphère <no-reply@neosphere-ls.be>';
    $headers[] = 'Reply-To: '. $name .' <'.$email.'>';
    $headers[] = 'X-Mailer: PHP/' . phpversion();
  $sent = @mail('contact@neosphere-ls.be', $emailSubject, $plainBody, implode("\r\n", $headers));
    if (!$sent) {
        echo json_encode(['ok'=>false,'error'=>'Envoi indisponible (config SMTP en attente)']);
        ob_end_flush();
        return; 
    }
}
echo json_encode(['ok'=>true]);
} catch (Exception $e) {
    if(!empty($mailCfg['debug']['log_file'])){
        @file_put_contents($mailCfg['debug']['log_file'], date('c')." PHPMailer EXCEPTION: ".$e->getMessage()."\n", FILE_APPEND);
    }
    http_response_code(500);
    $raw = trim(ob_get_clean());
    $extra = $raw ? ' | RAW: '.substr($raw,0,200) : '';
    echo json_encode(['ok'=>false,'error'=>'Erreur SMTP: '.$e->getMessage().$extra]);
    return;
} catch (Throwable $t) {
    if(!empty($mailCfg['debug']['log_file'])){
        @file_put_contents($mailCfg['debug']['log_file'], date('c')." THROWABLE: ".$t->getMessage()."\n", FILE_APPEND);
    }
    http_response_code(500);
    $raw = trim(ob_get_clean());
    echo json_encode(['ok'=>false,'error'=>'Erreur serveur interne']);
    return;
}
ob_end_flush();
?>