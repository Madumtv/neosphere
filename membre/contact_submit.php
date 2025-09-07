<?php
// Endpoint d'envoi du formulaire de contact via PHPMailer (JSON)
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

// Autoriser uniquement POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'Méthode non autorisée']);
    exit;
}

// Détection requête AJAX
$isAjax = (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest');
if(!$isAjax){
    // On force tout de même JSON pour éviter affichage HTML accidentel
}

// Charger l'autoloader PHPMailer - à adapter selon votre installation.
// Chemins possibles:
// require __DIR__ . '/../vendor/autoload.php';
// ou si PHPMailer extrait manuellement:
// require __DIR__ . '/../phpmailer/src/PHPMailer.php'; etc.

// Sécurité basique: limite taille globale
$maxBytes = 64 * 1024; // 64KB
if(!empty($_SERVER['CONTENT_LENGTH']) && (int)$_SERVER['CONTENT_LENGTH'] > $maxBytes){
    http_response_code(413);
    echo json_encode(['ok'=>false,'error'=>'Payload trop volumineux']);
    exit;
}

// Récupération champs
$name    = trim($_POST['name'] ?? '');
$email   = trim($_POST['email'] ?? '');
$phone   = trim($_POST['phone'] ?? '');
$type    = trim($_POST['type'] ?? '');
$message = trim($_POST['message'] ?? '');

// Validation basique
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

// Anti flood simple (IP + intervalle)
session_start();
$now = time();
if (!isset($_SESSION['last_contact_submit'])) { $_SESSION['last_contact_submit'] = 0; }
if (($now - $_SESSION['last_contact_submit']) < 15) {
    echo json_encode(['ok'=>false,'error'=>'Veuillez patienter avant un nouvel envoi']);
    exit;
}
$_SESSION['last_contact_submit'] = $now;

// Préparation email
$subject = 'Contact Néosphère: ' . ($type ?: 'Demande');
$bodyLines = [
    'Nom: ' . $name,
    'Email: ' . $email,
    'Téléphone: ' . ($phone ?: '—'),
    'Type: ' . ($type ?: '—'),
    'IP: ' . ($_SERVER['REMOTE_ADDR'] ?? 'n/a'),
    'User-Agent: ' . ($_SERVER['HTTP_USER_AGENT'] ?? 'n/a'),
    str_repeat('-', 40),
    $message
];
$body = implode("\n", $bodyLines);

// Placeholder: envoyer via mail() si PHPMailer pas encore installé
$usePhpMailer = false; // passez à true quand PHPMailer est disponible

if ($usePhpMailer) {
    // Exemple d'implémentation (décommenter et configurer):
    /*
    $mail = new PHPMailer\PHPMailer\PHPMailer(true);
    try {
        // SMTP config
        $mail->isSMTP();
        $mail->Host = 'smtp.example.com';
        $mail->SMTPAuth = true;
        $mail->Username = 'user@example.com';
        $mail->Password = 'PASSWORD';
        $mail->SMTPSecure = PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS; // ou ENCRYPTION_SMTPS
        $mail->Port = 587;
        $mail->CharSet = 'UTF-8';

        $mail->setFrom('no-reply@neosphere-ls.be', 'Site Néosphère');
        $mail->addAddress('contact@neosphere-ls.be', 'Néosphère');
        $mail->addReplyTo($email, $name);

        $mail->Subject = $subject;
        $mail->Body = $body;
        $mail->AltBody = $body;

        $mail->send();
    } catch (Exception $e) {
        echo json_encode(['ok'=>false,'error'=>'Erreur envoi: '.$mail->ErrorInfo]);
        exit;
    }
    */
} else {
    // Fallback provisoire mail() - peut échouer selon config serveur
    $headers = [];
    $headers[] = 'From: Site Néosphère <no-reply@neosphere-ls.be>';
    $headers[] = 'Reply-To: '. $name .' <'.$email.'>';
    $headers[] = 'X-Mailer: PHP/' . phpversion();
    $sent = @mail('contact@neosphere-ls.be', $subject, $body, implode("\r\n", $headers));
    if (!$sent) {
        echo json_encode(['ok'=>false,'error'=>'Envoi indisponible (config SMTP en attente)']);
        exit;
    }
}

echo json_encode(['ok'=>true]);
