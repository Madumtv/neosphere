<?php
header('Content-Type: text/plain; charset=utf-8');
ob_start();
$lines=[];
$lines[]='Diag mail '.date('c');
$lines[]='PHP VERSION: '.PHP_VERSION;
$autoload=__DIR__.'/vendor/autoload.php';
$lines[]='Autoload exists: '.(file_exists($autoload)?'YES':'NO');
if(file_exists($autoload)) require $autoload; else $lines[]='(Composer autoload manquant)';
$lines[]='PHPMailer class: '.(class_exists('PHPMailer\\PHPMailer\\PHPMailer')?'YES':'NO');
$cfgFile=__DIR__.'/inc/config.mail.php';
$lines[]='Config file: '.(file_exists($cfgFile)?'YES':'NO');
if(file_exists($cfgFile)){
    $cfg=require $cfgFile;
    if(is_array($cfg)){
        $lines[]='SMTP host: '.($cfg['smtp']['host']??'?');
        $lines[]='SMTP secure: '.($cfg['smtp']['secure']??'?');
        $lines[]='SMTP debug flag: '.(($cfg['debug']['enable_smtp_debug']??false)?'ON':'OFF');
    } else {
        $lines[]='Config non-array';
    }
}
$local=__DIR__.'/inc/config.mail.local.php';
$lines[]='Local override: '.(file_exists($local)?'YES':'NO');
$logDir=__DIR__.'/logs';
$lines[]='Logs dir writable: '.(is_dir($logDir) && is_writable($logDir)?'YES':'NO');
$raw=ob_get_clean();
if($raw!=='') $lines[]='(Extra output captured)';
echo implode("\n",$lines),"\n";
