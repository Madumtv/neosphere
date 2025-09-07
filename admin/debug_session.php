<?php
// debug_session.php — affiche les variables de session pour debug en dev
// Protégé : inclure uniquement depuis un script admin qui a vérifié isAdmin
if (!isset($_SESSION)) session_start();

ob_start();
?>
<div style="margin-top:1rem;padding:12px;background:#fff3cd;border:1px solid #ffeeba;border-radius:6px;">
    <strong>Debug session</strong>
    <pre style="white-space:pre-wrap;word-break:break-word;"><?php echo htmlspecialchars(print_r($_SESSION, true)); ?></pre>
</div>
<?php
$frag = ob_get_clean();
// Si le fichier est inclus, on affiche ; si appelé directement, on empêche l'affichage
if (debug_backtrace()) echo $frag;
?>
