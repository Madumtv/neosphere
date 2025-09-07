<?php
@ini_set('display_errors', '1');
@ini_set('display_startup_errors', '1');
error_reporting(E_ALL);

session_start();
// Vérification admin (mêmes heuristiques que les autres pages)
$isAdmin = false;
if (isset($_SESSION['is_admin']) && $_SESSION['is_admin']) $isAdmin = true;
if (isset($_SESSION['role']) && $_SESSION['role'] === 'admin') $isAdmin = true;
if (isset($_SESSION['role_id']) && $_SESSION['role_id'] == 1) $isAdmin = true;
if (isset($_SESSION['user']) && $_SESSION['user'] === 'admin') $isAdmin = true;
if (!isset($_SESSION['user']) || !$isAdmin) { header('Location: ../membre/login.php'); exit; }

require_once __DIR__.'/inc/db.php';
if (!$pdo) { echo '<p>Base de données indisponible.</p>'; exit; }

// Table attendue
$table = 'content_blocks';
// Vérifier l'existence de la table
try {
    $exists = $pdo->query("SHOW TABLES LIKE '".addslashes($table)."'")->fetch();
    if(!$exists){ echo "<p>Table {$table} introuvable.</p>"; exit; }
} catch(Exception $e){ echo "<p>Erreur schéma: ".htmlspecialchars($e->getMessage())."</p>"; exit; }

$action = $_POST['action'] ?? '';
$error = '';
$success = '';

// Blocs par défaut recommandés (slug => [title, content])
$defaultBlocks = [
  'footer_brand_desc' => ["Description footer", 'Votre partenaire santé et bien-être à Rebecq. Néosphère vous propose des soins modernes, personnalisés et accessibles, pour toute la famille'],
  'footer_tagline'    => ["Tagline footer", 'by Lindsay Serkeyn'],
  'contact_address'   => ["Adresse contact", '83 rue mayeur habil<br>1400 Rebecq, Belgique'],
  'contact_phone'     => ["Téléphone contact", '+32 (0) 479.74.61.12'],
  'contact_email'     => ["Email contact", 'contact@neosphere-ls.be'],
  'contact_hours'     => ["Horaires contact", 'Lun - Ven : 09h00 - 17h00<br>Adaptable à la demande'],
  'contact_map_embed' => ["Carte (iframe ou pb)", ''],
  'team_intro'        => ["Intro équipe", "<p>Nos médecins et spécialistes sont des professionnels au carnet d'expérience, rigoureusement sélectionnés pour leur expertise et leur sens humain. Nous favorisons une prise en charge globale, coordonnée et pédagogique pour vous accompagner durablement.</p>\n<ul class=\"check-list\">\n<li>Prise en charge personnalisée et confidentielle</li>\n<li>Protocoles basés sur les dernières recommandations</li>\n<li>Suivi digitalisé des dossiers médicaux sécurisé</li>\n<li>Orientation rapide vers des spécialistes référencés</li>\n</ul>"],
  'team_title'        => ["Titre équipe", 'Une équipe dévouée à <span>votre bien-être</span>'],
  // ===== Hero =====
  'hero_badge'        => ["Badge hero", 'Expertise Esthétique & bien-être'],
  'hero_title'        => ["Titre hero", 'Votre santé, <span style="color:#FFA94D;">notre</span><br><span style="color:#F7A1C4;">priorité</span>'],
  'hero_subtitle'     => ["Sous-titre hero", 'Un cabinet moderne au cœur de votre bien-être. Nos praticiens expérimentés vous accompagnent avec des soins personnalisés dans un environnement chaleureux.'],
  'hero_cta_primary'  => ["CTA hero primaire", '📅 Prendre rendez-vous'],
  'hero_cta_secondary'=> ["CTA hero secondaire", 'Découvrir nos services'],
  'hero_feat1_title'  => ["Atout 1 titre", 'Horaires flexibles'],
  'hero_feat1_sub'    => ["Atout 1 sous", 'du Lun. au ven.'],
  'hero_feat2_title'  => ["Atout 2 titre", 'Localisé'],
  'hero_feat2_sub'    => ["Atout 2 sous", 'Rebecq'],
  'hero_feat3_title'  => ["Atout 3 titre", 'Expérience'],
  'hero_feat3_sub'    => ["Atout 3 sous", '15+ années'],
  // ===== Services =====
  'services_eyebrow'  => ["Eyebrow services", 'Nos Services Médicaux'],
  'services_title'    => ["Titre services", 'Une gamme complète de <span>soins spécialisés</span>'],
  'services_intro'    => ["Intro services", 'Des consultations de médecine générale aux examens spécialisés, notre cabinet met à votre disposition une équipe pluridisciplinaire expérimentée et des équipements modernes pour un suivi personnalisé.'],
  'services_cta_primary'   => ["CTA services primaire", '📅 Prendre rendez-vous maintenant'],
  'services_cta_secondary' => ["CTA services secondaire", '👨‍⚕️ Voir tous les médecins'],
  // ===== Equipe =====
  'team_eyebrow'      => ["Eyebrow équipe", 'Notre Équipe'],
  'team_button_contact' => ["Bouton équipe contact", '📞 Contacter le secrétariat'],
  'team_stat1_number' => ["Stat1 nombre", '5000+'],
  'team_stat1_label'  => ["Stat1 label", 'Patients suivis'],
  'team_stat2_number' => ["Stat2 nombre", '15+'],
  'team_stat2_label'  => ["Stat2 label", 'Ans d\'expérience'],
  'team_stat3_number' => ["Stat3 nombre", '24/7'],
  'team_stat3_label'  => ["Stat3 label", 'Assistance'],
  'team_stat4_number' => ["Stat4 nombre", '98%'],
  'team_stat4_label'  => ["Stat4 label", 'Satisfaction'],
  'team_mission_title'=> ["Mission titre", 'Notre Mission'],
  'team_mission_text' => ["Mission texte", 'Accompagner chaque patient avec rigueur scientifique et empathie, en créant un lien de confiance durable et en valorisant la prévention autant que le traitement.'],
  // ===== Contact =====
  'contact_eyebrow'   => ["Eyebrow contact", 'Contactez-nous'],
  'contact_title'     => ["Titre contact", 'Notre équipe est à votre <span>écoute</span>'],
  'contact_intro'     => ["Intro contact", 'Disponible pour répondre à vos questions, planifier un rendez-vous ou vous orienter vers le bon accompagnement.'],
  'contact_box_title' => ["Titre boîte contact", 'Informations de contact'],
  'contact_quick_rdv_btn'  => ["Bouton rapide RDV", '📅 Prendre rendez-vous'],
  'contact_quick_call_btn' => ["Bouton rapide Appel", '📞 Appeler maintenant'],
  'contact_form_title'=> ["Titre formulaire", 'Envoyez-nous un message'],
  // ===== Footer divers =====
  'footer_legal_mentions'       => ["Lien mentions", 'Mentions légales'],
  'footer_legal_confidentialite'=> ["Lien confidentialité", 'Confidentialité'],
  'footer_legal_cgu'            => ["Lien CGU", 'CGU'],
  'footer_copyright'            => ["Copyright", '&copy; 2024 Néosphère. Fait avec <span class=\"heart\">❤</span> pour votre santé.'],
  // ===== Meta / SEO / Nav =====
  'meta_title'       => ["Meta Title", 'Néosphère - Esthétique & bien-être à Rebecq'],
  'meta_description' => ["Meta Description", 'Néosphère by Lindsay Serkeyn Esthétique & bien-être moderne à Rebecq. Prenez rendez-vous dès maintenant.'],
  'meta_author'      => ["Meta Author", 'Néosphère'],
  'og_title'         => ["OG Title", 'Néosphère - Esthétique & bien-être à Rebecq'],
  'og_description'   => ["OG Description", 'Néosphère, cabinet moderne offrant une gamme complète de services médicaux avec une approche humaine et personnalisée.'],
  'site_brand_name'  => ["Nom marque", 'Néosphère'],
  'nav_home'         => ["Menu Accueil", 'Accueil'],
  'nav_services'     => ["Menu Services", 'Services'],
  'nav_team'         => ["Menu Équipe", 'Équipe'],
  'nav_contact'      => ["Menu Contact", 'Contact'],
  'header_call_btn'  => ["Bouton en-tête Appeler", 'Appeler'],
  'header_rdv_btn'   => ["Bouton en-tête RDV", 'Rendez-vous'],
  'services_toggle_btn' => ["Bouton toggle services", '➕ Afficher plus de soins'],
  'services_search_label' => ["Label recherche services", 'Rechercher une prestation'],
  'service_details_more'  => ["Bouton détails +", 'Détails'],
  'service_details_less'  => ["Bouton détails -", 'Réduire'],
  // ===== Team intro détaillée (chaque élément séparé) =====
  'team_intro_paragraph'   => ["Paragraphe équipe", "Nos médecins et spécialistes sont des professionnels au carnet d'expérience, rigoureusement sélectionnés pour leur expertise et leur sens humain. Nous favorisons une prise en charge globale, coordonnée et pédagogique pour vous accompagner durablement."],
  'team_intro_bullet_1'    => ["Équipe puce 1", 'Prise en charge personnalisée et confidentielle'],
  'team_intro_bullet_2'    => ["Équipe puce 2", 'Protocoles basés sur les dernières recommandations'],
  'team_intro_bullet_3'    => ["Équipe puce 3", 'Suivi digitalisé des dossiers médicaux sécurisé'],
  'team_intro_bullet_4'    => ["Équipe puce 4", 'Orientation rapide vers des spécialistes référencés'],
  // ===== Footer colonnes titres =====
  'footer_col_contact_title' => ["Titre colonne contact", 'Contact'],
  'footer_col_hours_title'   => ["Titre colonne horaires", 'Horaires'],
  'footer_col_services_title'=> ["Titre colonne services", 'Services'],
  // ===== Taglines / Byline =====
  'header_byline'            => ["Byline header", 'by Lindsay Serkeyn'],
  // ===== Formulaire contact labels =====
  'contact_form_label_name'     => ["Label nom", 'Nom complet'],
  'contact_form_label_email'    => ["Label email", 'Email'],
  'contact_form_label_phone'    => ["Label téléphone", 'Téléphone'],
  'contact_form_label_type'     => ["Label type", 'Type de demande'],
  'contact_form_label_message'  => ["Label message", 'Message'],
  'contact_form_submit'         => ["Bouton envoyer", '📨 Envoyer le message'],
  'contact_form_note'           => ["Note formulaire", '* Tous les champs obligatoires sont nécessaires pour un meilleur traitement.'],
  // ===== Formulaire options =====
  'contact_form_option_placeholder' => ["Option placeholder", 'Préciser le motif'],
  'contact_form_option_rdv'         => ["Option RDV", 'Rendez-vous'],
  'contact_form_option_question'    => ["Option question", 'Question esthétique'],
  'contact_form_option_results'     => ["Option résultats", 'Résultats'],
  'contact_form_option_other'       => ["Option autre", 'Autre'],
  // ===== Map caption link =====
  'map_open_link_text'             => ["Texte lien carte", 'Ouvrir dans Maps →'],
];

// Seed automatique si demandé (?seed=1)
if (isset($_GET['seed']) && $_GET['seed'] == '1') {
  try {
    $slugs = array_keys($defaultBlocks);
    $in   = rtrim(str_repeat('?,', count($slugs)), ',');
    $existing = $pdo->prepare("SELECT slug FROM {$table} WHERE slug IN ($in)");
    $existing->execute($slugs);
    $have = array_column($existing->fetchAll(PDO::FETCH_ASSOC), 'slug');
    $missing = array_diff($slugs, $have);
    if ($missing) {
      $ins = $pdo->prepare("INSERT INTO {$table} (slug,title,content,updated_by) VALUES (?,?,?,NULL)");
      foreach ($missing as $ms) { $ins->execute([$ms, $defaultBlocks[$ms][0], $defaultBlocks[$ms][1]]); }
      $success = count($missing)." bloc(s) créé(s).";
    } else {
      $success = 'Tous les blocs par défaut existent déjà.';
    }
  } catch(Exception $e) { $error = 'Seed échoué: '.$e->getMessage(); }
}

// CRUD
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($action === 'create') {
        $slug = trim($_POST['slug'] ?? '');
        $title = trim($_POST['title'] ?? '');
        $content = trim($_POST['content'] ?? '');
        if ($slug==='') { $error = 'Slug requis.'; }
        else {
            try {
                $stmt = $pdo->prepare("INSERT INTO {$table} (slug,title,content,updated_by) VALUES (?,?,?,NULL)");
                $stmt->execute([$slug,$title,$content]);
                $success = 'Bloc créé';
            } catch(Exception $e){ $error = 'Erreur création: '.$e->getMessage(); }
        }
    } elseif ($action === 'update') {
        $id = (int)($_POST['id'] ?? 0);
        $title = trim($_POST['title'] ?? '');
        $content = trim($_POST['content'] ?? '');
        if ($id<=0) { $error='ID manquant.'; }
        else {
            try {
                $stmt = $pdo->prepare("UPDATE {$table} SET title=?, content=?, updated_at=NOW() WHERE id=? LIMIT 1");
                $stmt->execute([$title,$content,$id]);
                $success='Bloc mis à jour';
            } catch(Exception $e){ $error='Erreur mise à jour: '.$e->getMessage(); }
        }
    } elseif ($action === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id<=0) { $error='ID invalide.'; } else {
            try {
                $stmt = $pdo->prepare("DELETE FROM {$table} WHERE id=? LIMIT 1");
                $stmt->execute([$id]);
                $success='Bloc supprimé';
            } catch(Exception $e){ $error='Erreur suppression: '.$e->getMessage(); }
        }
    }
}

// Liste des blocs (pagination simple)
$limit = 10000; // afficher largement tous les blocs
$rows = [];
try {
  $rows = $pdo->query("SELECT id, slug, title, content, updated_at FROM {$table} ORDER BY id ASC LIMIT {$limit}")->fetchAll(PDO::FETCH_ASSOC);
} catch(Exception $e){ $error = 'Erreur chargement: '.$e->getMessage(); }

// Détection des slugs manquants pour l'affichage du bouton seed
$existingSlugs = array_column($rows, 'slug');
$missingDefaults = array_diff(array_keys($defaultBlocks), $existingSlugs);

function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8'); }
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="utf-8" />
<title>Gestion contenu</title>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bulma@0.9.4/css/bulma.min.css">
<style>
html,body{height:100%;}
body{padding:30px; margin:0; background-image:url('../fond.jpg'); background-repeat:no-repeat; background-position:center center; background-size:cover; background-attachment:fixed; display:flex; flex-direction:column;}
main.admin-wrapper{max-width:1200px; width:100%; margin:0 auto; backdrop-filter:saturate(1.2) blur(3px);}
.panel-surface{background:rgba(255,255,255,0.94); border-radius:12px; box-shadow:0 8px 32px -8px #00000030; padding:26px 34px;}
@media (max-width:820px){.panel-surface{padding:22px 20px;}}
textarea{min-height:140px; font-family:monospace;}
.rte-wrapper{border:1px solid #d9d9d9; border-radius:8px; overflow:hidden; background:#fff;}
.rte-toolbar{display:flex; flex-wrap:wrap; gap:4px; padding:6px 8px; background:linear-gradient(#fafafa,#f0f0f0); border-bottom:1px solid #e2e2e2;}
.rte-toolbar button{border:1px solid #c8c8c8; background:#fff; padding:4px 8px; font-size:.8rem; line-height:1; cursor:pointer; border-radius:4px; display:inline-flex; align-items:center; gap:4px;}
.rte-toolbar button.active{background:#3273dc; color:#fff; border-color:#2460b8;}
.rte-toolbar button:hover{background:#f5f5f5;}
.rte-toolbar input[type=color]{width:32px; height:28px; padding:0; border:1px solid #c8c8c8; border-radius:4px; cursor:pointer; background:#fff;}
.rte-editor{min-height:160px; padding:10px 12px; outline:none; font-family:inherit; font-size:0.95rem; line-height:1.4;}
.rte-editor:focus{box-shadow:0 0 0 2px #3273dc40;}
.rte-hint{font-size:.65rem; color:#777; margin-top:4px;}
pre{white-space:pre-wrap; word-wrap:break-word;}
.trunc{max-width:360px; display:block; overflow:hidden; text-overflow:ellipsis; white-space:nowrap;}
.badge{background:#3273dc; color:#fff; border-radius:12px; padding:2px 8px; font-size:.65rem; font-weight:600;}
.cards-grid{display:grid; grid-template-columns:1fr 1fr; gap:34px;} @media(max-width:1100px){.cards-grid{grid-template-columns:1fr;}}
.glass-box{background:rgba(255,255,255,0.9); backdrop-filter:blur(4px) saturate(1.2); border:1px solid #ffffff80; border-radius:14px; padding:24px 26px; box-shadow:0 6px 22px -8px #00000035;}
.table{background:#fff;}
.page-title{display:flex; align-items:center; gap:14px;}
.page-title .ico{font-size:1.6rem; line-height:1;}
header.topbar{display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; margin-bottom:18px;}
header.topbar a{margin-right:8px;}
</style>
</head>
<body>
<?php include_once __DIR__ . '/../menu.php'; ?>
<main class="admin-wrapper">
  <div class="panel-surface">
    <header class="topbar">
      <div class="page-title">
        <span class="ico">📝</span>
        <div>
          <h1 class="title is-4" style="margin:0;">Blocs de contenu</h1>
          <p class="subtitle is-7" style="margin:2px 0 0;">Gérer les zones de texte réutilisables (footer, slogans, etc.).</p>
        </div>
      </div>
      <div style="display:flex; gap:6px; flex-wrap:wrap;">
        <a href="index.php" class="button is-small">← Dashboard</a>
        <a href="content_blocks.php" class="button is-small is-light">Rafraîchir</a>
        <?php if(!empty($missingDefaults)): ?>
          <a href="content_blocks.php?seed=1" class="button is-small is-primary" onclick="return confirm('Créer les blocs manquants ?');">Créer blocs par défaut (<?= count($missingDefaults) ?>)</a>
        <?php endif; ?>
      </div>
    </header>

    <?php if($error): ?><div class="notification is-danger"><?=h($error)?></div><?php endif; ?>
    <?php if($success): ?><div class="notification is-success"><?=h($success)?></div><?php endif; ?>

    <div class="glass-box" style="max-width:780px;">
      <h2 class="title is-5" style="margin-top:0;">Nouveau bloc</h2>
      <form method="post">
        <input type="hidden" name="action" value="create" />
        <div class="field">
          <label class="label">Slug (unique)</label>
          <div class="control"><input class="input" name="slug" required placeholder="ex: footer_tagline" /></div>
        </div>
        <div class="field">
          <label class="label">Titre</label>
          <div class="control"><input class="input" name="title" placeholder="Label interne" /></div>
        </div>
        <div class="field">
          <label class="label">Contenu</label>
          <div class="control"><textarea class="textarea rte" id="new-content" name="content" placeholder="Texte (mise en forme via barre au-dessus)"></textarea></div>
          <p class="rte-hint">Astuce: sélectionner puis cliquer G (gras), I (italique), U (souligné), Liste, Lien, Couleur.</p>
        </div>
        <div class="field is-grouped">
          <div class="control"><button class="button is-primary">Créer</button></div>
        </div>
      </form>
    </div>

    <h2 class="title is-5" style="margin-top:40px;">Blocs existants</h2>
    <div class="field" style="max-width:320px;">
      <div class="control has-icons-left">
        <input id="filter-input" class="input" type="text" placeholder="Filtrer (slug ou titre)">
        <span class="icon is-left">🔍</span>
      </div>
    </div>
    <?php if(!empty($missingDefaults)): ?>
      <p class="notification is-warning is-light" style="max-width:780px;">Blocs manquants: <strong><?= htmlspecialchars(implode(', ',$missingDefaults)) ?></strong>. Vous pouvez les créer via le bouton "Créer blocs par défaut".</p>
    <?php endif; ?>
  <div class="glass-box" style="overflow-x:auto;">
  <table class="table is-fullwidth is-striped is-hoverable" style="margin-bottom:0;">
      <thead><tr><th>ID</th><th>Slug</th><th>Titre</th><th>Extrait</th><th>Actions</th></tr></thead>
      <tbody>
        <?php if(!$rows): ?>
          <tr><td colspan="5">Aucun bloc.</td></tr>
        <?php else: foreach($rows as $r): ?>
          <tr>
            <td><?= (int)$r['id'] ?></td>
            <td><code><?= h($r['slug']) ?></code></td>
            <td><?= h($r['title']) ?></td>
            <td><span class="trunc" title="<?= h($r['content']) ?>"><?= h($r['content']) ?></span></td>
            <td>
              <button 
                class="button is-small is-info js-edit-block"
                data-id="<?= (int)$r['id'] ?>"
                data-title="<?= htmlspecialchars($r['title'] ?? '', ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8') ?>"
                data-content-b64="<?= base64_encode($r['content'] ?? '') ?>"
                type="button"
              >Éditer</button>
              <form method="post" style="display:inline;" onsubmit='return confirm("Supprimer ce bloc ?");'>
                <input type="hidden" name="action" value="delete" />
                <input type="hidden" name="id" value="<?= (int)$r['id'] ?>" />
                <button class="button is-small is-danger">✕</button>
              </form>
            </td>
          </tr>
        <?php endforeach; endif; ?>
      </tbody>
    </table>
    </div>
  </div>
</main>

<!-- Modal édition -->
<div class="modal" id="edit-modal">
  <div class="modal-background" onclick="closeModal()"></div>
  <div class="modal-card" style="width:760px; max-width:95vw;">
    <header class="modal-card-head">
      <p class="modal-card-title">Éditer bloc</p>
      <button class="delete" aria-label="close" onclick="closeModal()"></button>
    </header>
    <section class="modal-card-body">
      <form method="post" id="edit-form">
        <input type="hidden" name="action" value="update" />
        <input type="hidden" name="id" id="edit-id" />
        <div class="field">
          <label class="label">Titre</label>
          <div class="control"><input class="input" name="title" id="edit-title" /></div>
        </div>
        <div class="field">
          <label class="label">Contenu</label>
          <div class="control"><textarea class="textarea rte" name="content" id="edit-content"></textarea></div>
          <p class="rte-hint">Mise en forme identique au bloc de création.</p>
        </div>
        <div class="field is-grouped">
          <div class="control"><button class="button is-primary">Enregistrer</button></div>
          <div class="control"><button type="button" class="button" onclick="closeModal()">Annuler</button></div>
        </div>
      </form>
      <p class="is-size-7 has-text-grey">Utilisation dans le code: <code>content_get('slug')</code></p>
    </section>
  </div>
</div>

<script>
function editBlock(id, title, content){
  document.getElementById('edit-id').value = id;
  document.getElementById('edit-title').value = (title===null||title==='null')? '' : title;
  document.getElementById('edit-content').value = content || '';
  document.getElementById('edit-modal').classList.add('is-active');
}
function closeModal(){ document.getElementById('edit-modal').classList.remove('is-active'); }
// Filtre rapide
document.getElementById('filter-input').addEventListener('input', function(){
  const q=this.value.toLowerCase();
  document.querySelectorAll('table tbody tr').forEach(tr=>{
    const txt=tr.innerText.toLowerCase();
    tr.style.display = txt.indexOf(q)>-1 ? '' : 'none';
  });
});
</script>
<script>
// ---- Mini éditeur RTE ----
(function(){
  function wrapTextarea(tx){
    if(tx.dataset.enhanced) return; tx.dataset.enhanced='1';
    const wrapper=document.createElement('div'); wrapper.className='rte-wrapper';
    const toolbar=document.createElement('div'); toolbar.className='rte-toolbar';
    const editor=document.createElement('div'); editor.className='rte-editor'; editor.contentEditable=true; editor.innerHTML=tx.value;
    // Boutons config
    const buttons=[
      {cmd:'bold', label:'G'},
      {cmd:'italic', label:'I'},
      {cmd:'underline', label:'U'},
      {cmd:'insertUnorderedList', label:'Liste'},
      {cmd:'createLink', label:'Lien', action:()=>{
          const url=prompt('URL du lien (https://...)');
          if(url){ document.execCommand('createLink', false, url); }
        }
      },
      {cmd:'removeFormat', label:'Nettoyer', action:()=>{document.execCommand('removeFormat', false, null); document.execCommand('unlink',false,null);} }
    ];
    buttons.forEach(b=>{
      const btn=document.createElement('button'); btn.type='button'; btn.textContent=b.label; btn.title=b.label;
      btn.addEventListener('click',()=>{ if(b.action){ b.action(); } else { document.execCommand(b.cmd,false,null);} editor.focus(); refreshStates();});
      toolbar.appendChild(btn);
    });
    const color=document.createElement('input'); color.type='color'; color.title='Couleur du texte';
    color.addEventListener('input',()=>{ document.execCommand('foreColor', false, color.value); editor.focus(); });
    toolbar.appendChild(color);
    tx.style.display='none';
    tx.parentNode.insertBefore(wrapper, tx);
    wrapper.appendChild(toolbar); wrapper.appendChild(editor); wrapper.appendChild(tx);
    // Sync avant submit
    const form=tx.closest('form'); if(form){ form.addEventListener('submit',()=>{ tx.value=editor.innerHTML; }); }
    // Etat actif
    function refreshStates(){
      toolbar.querySelectorAll('button').forEach(b=>{
        const cmd = buttons.find(x=>x.label===b.textContent); if(!cmd||cmd.action) return;
        try{ b.classList.toggle('active', document.queryCommandState(cmd.cmd)); }catch(e){}
      });
    }
    editor.addEventListener('keyup', refreshStates); editor.addEventListener('mouseup', refreshStates);
  }
  document.querySelectorAll('textarea.rte').forEach(wrapTextarea);
  // Quand on ouvre le modal d'édition on réinitialise le contenu dans l'éditeur
  window.editBlock = (function(orig){
    return function(id,title,content){
      orig(id,title,content); // remplit textarea
      // Appliquer RTE si pas encore
      const tx=document.getElementById('edit-content');
      // Le wrapper existe, juste mettre à jour le contenu editable
      const editor = tx.parentNode.querySelector('.rte-editor'); if(editor){ editor.innerHTML = tx.value; }
    };
  })(window.editBlock);
    // Délégation clic sur nouveaux boutons .js-edit-block
    document.addEventListener('click', function(e){
      const btn = e.target.closest('.js-edit-block');
      if(!btn) return;
      const id = parseInt(btn.dataset.id||'0',10);
      const title = btn.dataset.title || '';
      let content = '';
      if(btn.dataset.contentB64){ try { content = atob(btn.dataset.contentB64); } catch(err) { content=''; } }
      if(typeof window.editBlock === 'function'){ window.editBlock(id, title, content); }
    });
})();
</script>
</body>
</html>
