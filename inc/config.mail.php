<?php
// Configuration SMTP centralisée pour PHPMailer
// Dupliquez ce fichier en config.mail.local.php (ignoré git) pour surcharger en local.
// Ne PAS laisser de mot de passe ici (utiliser config.mail.local.php ou variables d'env)
$logsDir = __DIR__.'/../logs';
if (!is_dir($logsDir)) @mkdir($logsDir, 0775, true);

return [
    'smtp' => [
        // Host d’envoi SMTP (vérifie si ton hébergeur recommande mail.neosphere-ls.be)
        'host'     => getenv('SMTP_HOST') ?: 'neosphere-ls.be',
        'user'     => getenv('SMTP_USER') ?: 'contact@neosphere-ls.be',
        'pass'     => getenv('SMTP_PASS') ?: 'didilulu2815!', // défini via config.mail.local.php ou variable d'env
        'port'     => (int)(getenv('SMTP_PORT') ?: 465),
        // 465 -> SMTPS (TLS implicite). Pour 587 utiliser STARTTLS.
        'secure'   => getenv('SMTP_SECURE') ?: 'SMTPS',
        'from'     => 'contact@neosphere-ls.be',
        'fromName' => 'Néosphère',
        'to'       => 'contact@neosphere-ls.be',
        'toName'   => 'Néosphère',
    ],
    'limits' => [
        'min_interval_sec' => 15,
        'max_length'       => 5000,
    ],
    'debug' => [
        'enable_smtp_debug' => false,
        'log_file'          => $logsDir.'/mail.log',
        'smtp_debug_file'   => $logsDir.'/smtp_debug.log',
    ],
];
