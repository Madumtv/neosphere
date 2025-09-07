<?php
// Page de gestion des utilisateurs (CRUD)
@ini_set('display_errors', '1');
@ini_set('display_startup_errors', '1');
error_reporting(E_ALL);

session_start();
// Vérification admin (copié depuis index.php)
$isAdmin = false;
if (isset($_SESSION['is_admin']) && $_SESSION['is_admin']) $isAdmin = true;
if (isset($_SESSION['role']) && $_SESSION['role'] === 'admin') $isAdmin = true;
if (isset($_SESSION['role_id']) && $_SESSION['role_id'] == 1) $isAdmin = true;
if (isset($_SESSION['user']) && $_SESSION['user'] === 'admin') $isAdmin = true;
if (!isset($_SESSION['user']) || !$isAdmin) {
    header('Location: ../membre/login.php');
    exit;
}

require_once __DIR__ . '/inc/db.php'; // fournit $pdo

$tableCandidates = ['users','user','members','membre'];
$usersTable = null;
foreach ($tableCandidates as $t) {
    try {
        $stmt = $pdo->query("SHOW TABLES LIKE '" . addslashes($t) . "'");
        if ($stmt && $stmt->fetch()) { $usersTable = $t; break; }
    } catch (Exception $e) { }
}
if (!$usersTable) {
    echo "<p>Table d'utilisateurs introuvable. Vérifiez la base de données.</p>";
    exit;
}

// Détection des colonnes utiles
$cols = [];
try {
    $desc = $pdo->query("DESCRIBE {$usersTable}")->fetchAll(PDO::FETCH_ASSOC);
    foreach ($desc as $c) $cols[] = $c['Field'];
} catch (Exception $e) {
    echo "Erreur interrogation schéma: " . htmlspecialchars($e->getMessage());
    exit;
}

// Détection éventuelle d'une table des rôles et chargement des options
$rolesTable = null;
$roleOptions = []; // id => name
$roleTableCandidates = ['roles','role','user_roles','roles_list','roles_table'];
foreach ($roleTableCandidates as $rt) {
  try {
    $s = $pdo->query("SHOW TABLES LIKE '" . addslashes($rt) . "'");
    if ($s && $s->fetch()) { $rolesTable = $rt; break; }
  } catch (Exception $e) { }
}
if ($rolesTable) {
  // essayer d'identifier les colonnes id et label
  try {
    $rdesc = $pdo->query("DESCRIBE {$rolesTable}")->fetchAll(PDO::FETCH_ASSOC);
    $rcols = array_column($rdesc, 'Field');
    $rIdCol = findCol($rcols, ['id','role_id','rid'], 'id');
    $rNameCol = findCol($rcols, ['name','role','role_name','label'], 'name');
    // charger les options
    $sql = "SELECT {$rIdCol} as id, {$rNameCol} as name FROM {$rolesTable} ORDER BY {$rIdCol} ASC";
    $rows = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);
    foreach ($rows as $row) {
      $roleOptions[$row['id']] = $row['name'];
    }
  } catch (Exception $e) {
    // ignore silently, fallback below
  }
}

function findCol(array $cols, array $names, $default = null) {
    foreach ($names as $n) if (in_array($n, $cols)) return $n;
    // fallback: partial match
    foreach ($cols as $c) {
        foreach ($names as $n) {
            if (stripos($c, $n) !== false) return $c;
        }
    }
    return $default;
}

$idCol = findCol($cols, ['id','user_id','uid'], 'id');
$usernameCol = findCol($cols, ['username','user','name'], 'username');
$emailCol = findCol($cols, ['email','mail'], 'email');
$pseudoCol = findCol($cols, ['pseudo','display_name','displayname','nickname'], null);
$roleCol = findCol($cols, ['role','role_name'], 'role');
$roleIdCol = findCol($cols, ['role_id'], 'role_id');
$passwordCol = findCol($cols, ['password','passwd','pass'], 'password');

// Messages
$error = '';
$success = '';

// Actions POST: create, update, delete
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if ($action === 'create') {
  $username = trim($_POST['username'] ?? '');
  $email = trim($_POST['email'] ?? '');
  // role can be either textual role (roleCol) or a numeric role_id (roleIdCol)
  $role = trim($_POST['role'] ?? 'member');
  $role_id = isset($_POST['role_id']) ? intval($_POST['role_id']) : null;
        $password = $_POST['password'] ?? '';
        if ($username === '') { $error = 'Nom d\'utilisateur requis.'; }
        else {
            try {
                $fields = [$usernameCol => $username];
                $params = [':u' => $username];
                if (in_array($emailCol, $cols) && $email !== '') { $fields[$emailCol] = $email; $params[':e']=$email; }
                if ($pseudoCol && in_array($pseudoCol, $cols) && ($pseudo = trim($_POST['pseudo'] ?? '')) !== '') { $fields[$pseudoCol] = $pseudo; $params[':pseudo']=$pseudo; }
                if (in_array($roleCol, $cols)) { $fields[$roleCol] = $role; $params[':r']=$role; }
                // If only role_id column exists, allow setting it via select but do not show 'Role ID' label
                if (!in_array($roleCol, $cols) && in_array($roleIdCol, $cols) && $role_id !== null) { $fields[$roleIdCol] = $role_id; $params[':rid']=$role_id; }
                if (in_array($passwordCol, $cols) && $password !== '') { $hash = password_hash($password, PASSWORD_DEFAULT); $fields[$passwordCol]=$hash; $params[':p']=$hash; }

                $colNames = implode(', ', array_keys($fields));
                $placeholders = implode(', ', array_map(function($k){ return ':' . $k; }, array_keys($fields)));
                // Build prepared statements mapping
                $placeholders = implode(', ', array_keys($params));
                $sql = "INSERT INTO {$usersTable} ({$colNames}) VALUES ({$placeholders})";
                $stmt = $pdo->prepare($sql);
                $stmt->execute($params);
                $success = 'Utilisateur créé.';
            } catch (Exception $e) { $error = 'Erreur création: ' . $e->getMessage(); }
        }
    } elseif ($action === 'update') {
        $id = $_POST['id'] ?? '';
        if ($id === '') { $error = 'ID manquant.'; }
        else {
            $username = trim($_POST['username'] ?? '');
            $email = trim($_POST['email'] ?? '');
      $role = trim($_POST['role'] ?? 'member');
      $role_id = isset($_POST['role_id']) ? intval($_POST['role_id']) : null;
            // when roles table exists we expect role_id from the form; otherwise textual role is used
            $password = $_POST['password'] ?? '';
            try {
                $sets = [];
                $params = [':id' => $id];
                if ($username !== '') { $sets[] = "{$usernameCol} = :u"; $params[':u']=$username; }
                if (in_array($emailCol, $cols)) { $sets[] = "{$emailCol} = :e"; $params[':e']=$email; }
                if ($pseudoCol && in_array($pseudoCol, $cols)) { $sets[] = "{$pseudoCol} = :pseudo"; $params[':pseudo'] = trim($_POST['pseudo'] ?? ''); }
                if (in_array($roleCol, $cols)) { $sets[] = "{$roleCol} = :r"; $params[':r']=$role; }
                if (!in_array($roleCol, $cols) && in_array($roleIdCol, $cols) && $role_id !== null) { $sets[] = "{$roleIdCol} = :rid"; $params[':rid']=$role_id; }
                if (in_array($passwordCol, $cols) && $password !== '') { $hash = password_hash($password, PASSWORD_DEFAULT); $sets[] = "{$passwordCol} = :p"; $params[':p']=$hash; }
                if (empty($sets)) { $error = 'Aucune donnée à mettre à jour.'; }
                else {
                    $sql = "UPDATE {$usersTable} SET " . implode(', ', $sets) . " WHERE {$idCol} = :id";
                    $stmt = $pdo->prepare($sql);
                    $stmt->execute($params);
                    $success = 'Utilisateur mis à jour.';
                }
            } catch (Exception $e) { $error = 'Erreur mise à jour: ' . $e->getMessage(); }
        }
  } elseif ($action === 'delete') {
        $id = $_POST['id'] ?? '';
        if ($id === '') { $error = 'ID manquant.'; }
        else {
            try {
                $sql = "DELETE FROM {$usersTable} WHERE {$idCol} = :id";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([':id' => $id]);
                $success = 'Utilisateur supprimé.';
            } catch (Exception $e) { $error = 'Erreur suppression: ' . $e->getMessage(); }
        }
    }
  // gestion simple des rôles via admin/users.php (si table roles détectée)
  elseif ($action === 'create_role' || $action === 'delete_role' || $action === 'update_role') {
    if (!$rolesTable) { $error = 'Table des rôles introuvable.'; }
    else {
      try {
        if ($action === 'update_role') {
          $rid = intval($_POST['role_id'] ?? 0);
          $rname_new = trim($_POST['role_name'] ?? '');
          if ($rid <= 0 || $rname_new === '') { $error = 'ID ou nom de rôle invalide.'; }
          else {
            $ustmt = $pdo->prepare("UPDATE {$rolesTable} SET {$rNameCol} = :n WHERE {$rIdCol} = :rid");
            $ustmt->execute([':n' => $rname_new, ':rid' => $rid]);
            $success = 'Rôle mis à jour.';
            $rows = $pdo->query("SELECT {$rIdCol} as id, {$rNameCol} as name FROM {$rolesTable} ORDER BY {$rIdCol} ASC")->fetchAll(PDO::FETCH_ASSOC);
            $roleOptions = [];
            foreach ($rows as $row) $roleOptions[$row['id']] = $row['name'];
          }
        }
        if ($action === 'create_role') {
          $rname = trim($_POST['role_name'] ?? '');
          if ($rname === '') { $error = 'Nom de rôle requis.'; }
          else {
            $sql = "INSERT INTO {$rolesTable} ({$rNameCol}) VALUES (:n)";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([':n' => $rname]);
            $success = 'Rôle créé.';
            // reload options
            $rows = $pdo->query("SELECT {$rIdCol} as id, {$rNameCol} as name FROM {$rolesTable} ORDER BY {$rIdCol} ASC")->fetchAll(PDO::FETCH_ASSOC);
            $roleOptions = [];
            foreach ($rows as $row) $roleOptions[$row['id']] = $row['name'];
          }
                } elseif ($action === 'delete_role') {
          $rid = intval($_POST['role_id'] ?? 0);
          if ($rid <= 0) { $error = 'ID de rôle invalide.'; }
          else {
            // Prevent deleting role if users exist with this role_id
            $cnt = 0;
            try {
              $cstmt = $pdo->prepare("SELECT COUNT(*) as c FROM {$usersTable} WHERE {$roleIdCol} = :rid");
              $cstmt->execute([':rid' => $rid]);
              $cres = $cstmt->fetch(PDO::FETCH_ASSOC);
              $cnt = intval($cres['c'] ?? 0);
            } catch (Exception $e) { $cnt = 0; }
            if ($cnt > 0) { $error = 'Impossible de supprimer: des utilisateurs utilisent ce rôle.'; }
            else {
              $dstmt = $pdo->prepare("DELETE FROM {$rolesTable} WHERE {$rIdCol} = :rid");
              $dstmt->execute([':rid' => $rid]);
              $success = 'Rôle supprimé.';
              $rows = $pdo->query("SELECT {$rIdCol} as id, {$rNameCol} as name FROM {$rolesTable} ORDER BY {$rIdCol} ASC")->fetchAll(PDO::FETCH_ASSOC);
              $roleOptions = [];
              foreach ($rows as $row) $roleOptions[$row['id']] = $row['name'];
            }
          }
        }
      } catch (Exception $e) { $error = 'Erreur roles: ' . $e->getMessage(); }
    }
  }
}

// Récupération de la liste: si roles détectées, joindre pour afficher role_name
$useRoleJoin = (!empty($roleOptions) && in_array($roleIdCol, $cols));
try {
  if ($useRoleJoin) {
    $users = $pdo->query("SELECT u.*, r.name AS role_name FROM {$usersTable} u LEFT JOIN roles r ON u.{$roleIdCol}=r.id ORDER BY u.{$idCol} DESC")->fetchAll(PDO::FETCH_ASSOC);
  } else {
    $users = $pdo->query("SELECT * FROM {$usersTable} ORDER BY {$idCol} DESC")->fetchAll(PDO::FETCH_ASSOC);
  }
} catch (Exception $e) { $users = []; $error = 'Erreur lecture utilisateurs: ' . $e->getMessage(); }

?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8" />
    <title>Gestion des utilisateurs - Admin</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bulma@0.9.4/css/bulma.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
      html, body { height: 100%; margin: 0; }
      body{ background-image:url('../fond.jpg'); background-size:cover; background-attachment:fixed; background-position:center center; }
      /* Wrapper full-height pour que le fond couvre tout l'écran */
      .adm-root{ min-height:100vh; padding:40px 16px; display:flex; justify-content:center; align-items:flex-start; }
      .card{ max-width:1100px; margin:0 auto; }
    </style>
</head>
<body>
<?php include_once __DIR__ . '/../menu.php'; ?>
<div class="adm-root">
  <div class="card">
    <header class="card-header">
      <p class="card-header-title">Gestion des utilisateurs</p>
      <div style="margin-left:auto;display:flex;gap:8px;align-items:center;padding:8px;">
        <button class="button is-success openCreateUser" type="button" title="Créer un utilisateur"><span class="icon"><i class="fas fa-user-plus"></i></span></button>
        <button class="button is-link is-light openRoles" type="button" title="Gérer les rôles">Rôles</button>
        <a class="button is-light" href="index.php">← Retour</a>
        <a class="button is-light" href="../index.php" title="Aller sur le site">
          <span class="icon"><i class="fas fa-home"></i></span>
          <span>Accueil</span>
        </a>
      </div>
    </header>
    <div class="card-content">
      <div class="content">
        <?php if ($error): ?>
          <div class="notification is-danger"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>
        <?php if ($success): ?>
          <div class="notification is-primary"><?php echo htmlspecialchars($success); ?></div>
        <?php endif; ?>

        <!-- Création utilisateur via modal -->
        <div style="margin-bottom:1rem; display:flex; gap:8px; align-items:center;">
          <button id="openCreateUser" class="button is-success openCreateUser"><span class="icon"><i class="fas fa-user-plus"></i></span><span>Créer un utilisateur</span></button>
          <button class="button is-light openRoles" type="button">Gérer les rôles</button>
        </div>

        <!-- Modal Bulma pour création -->
        <div id="createUserModal" class="modal">
          <div class="modal-background" data-close></div>
          <div class="modal-card">
            <header class="modal-card-head">
              <p class="modal-card-title">Créer un utilisateur</p>
              <button class="delete" aria-label="close" data-close></button>
            </header>
            <form method="post">
              <input type="hidden" name="action" value="create">
              <section class="modal-card-body">
                <div class="field"><label class="label">Nom d'utilisateur</label><div class="control"><input class="input" name="username" placeholder="Nom d'utilisateur" required></div></div>
                <?php if (in_array($emailCol, $cols)): ?><div class="field"><label class="label">Email</label><div class="control"><input class="input" name="email" placeholder="Email"></div></div><?php endif; ?>
                <?php if ($pseudoCol && in_array($pseudoCol, $cols)): ?><div class="field"><label class="label">Pseudo</label><div class="control"><input class="input" name="pseudo" placeholder="Pseudo"></div></div><?php endif; ?>
                <div class="field"><label class="label">Rôle</label><div class="control">
                  <div class="select">
                    <?php if (!empty($roleOptions) && in_array($roleIdCol, $cols) && !in_array($roleCol, $cols)): ?>
                      <select name="role_id">
                          <?php foreach ($roleOptions as $rid => $rname): ?>
                          <option value="<?php echo htmlspecialchars($rid); ?>" <?php echo ($rid==2) ? 'selected' : ''; ?>><?php echo htmlspecialchars($rname); ?></option>
                          <?php endforeach; ?>
                      </select>
                    <?php else: ?>
                      <select name="role">
                        <?php if (!empty($roleOptions)): ?>
                          <?php foreach ($roleOptions as $rid => $rname): ?>
                            <option value="<?php echo htmlspecialchars($rid); ?>" <?php echo ($rid==2) ? 'selected' : ''; ?>><?php echo htmlspecialchars($rname); ?></option>
                          <?php endforeach; ?>
                        <?php else: ?>
                          <option value="member">Membre</option>
                          <option value="admin">Admin</option>
                        <?php endif; ?>
                      </select>
                    <?php endif; ?>
                  </div>
                </div></div>
                <?php if (in_array($passwordCol, $cols)): ?><div class="field"><label class="label">Mot de passe</label><div class="control"><input class="input" type="password" name="password" placeholder="Mot de passe"></div></div><?php endif; ?>
              </section>
              <footer class="modal-card-foot">
                <button class="button is-success" type="submit">Créer</button>
                <button type="button" class="button" data-close>Annuler</button>
              </footer>
            </form>
          </div>
        </div>

        <table class="table is-fullwidth is-striped">
          <thead>
            <tr>
              <th>#</th>
              <th>Utilisateur</th>
              <?php if (in_array($emailCol, $cols)): ?><th>Email</th><?php endif; ?>
              <?php if (in_array($roleCol, $cols) || in_array($roleIdCol, $cols)): ?><th>Role</th><?php endif; ?>
              <th>Actions</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($users as $u): ?>
              <tr>
                <td><?php echo htmlspecialchars($u[$idCol] ?? ''); ?></td>
                <td><?php echo htmlspecialchars($u[$usernameCol] ?? ''); ?></td>
                <?php if ($pseudoCol && in_array($pseudoCol, $cols)): ?><td><?php echo htmlspecialchars($u[$pseudoCol] ?? ''); ?></td><?php endif; ?>
                <?php if (in_array($emailCol, $cols)): ?><td><?php echo htmlspecialchars($u[$emailCol] ?? ''); ?></td><?php endif; ?>
                <?php if ($useRoleJoin || in_array($roleCol, $cols) || in_array($roleIdCol, $cols)): ?>
                  <td><?php echo htmlspecialchars($u['role_name'] ?? ($u[$roleCol] ?? ($roleOptions[$u[$roleIdCol] ?? ''] ?? ''))); ?></td>
                <?php endif; ?>
                <td>
                  <a class="button is-small is-info" href="?action=edit&id=<?php echo urlencode($u[$idCol]); ?>">Modifier</a>
                  <form method="post" style="display:inline-block;margin-left:6px;" onsubmit="return confirm('Supprimer cet utilisateur ?');">
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="id" value="<?php echo htmlspecialchars($u[$idCol]); ?>">
                    <button class="button is-small is-danger" type="submit">Supprimer</button>
                  </form>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>

        <?php if (isset($_GET['action']) && $_GET['action'] === 'edit' && isset($_GET['id'])):
            $editId = $_GET['id'];
            $found = null;
            foreach ($users as $uu) if (($uu[$idCol] ?? '') == $editId) { $found = $uu; break; }
            if ($found): ?>
              <hr>
              <h4 class="title is-5">Modifier l'utilisateur #<?php echo htmlspecialchars($found[$idCol]); ?></h4>
              <form method="post">
                <input type="hidden" name="action" value="update">
                <input type="hidden" name="id" value="<?php echo htmlspecialchars($found[$idCol]); ?>">
                <div class="field"><label class="label">Nom</label><div class="control"><input class="input" name="username" value="<?php echo htmlspecialchars($found[$usernameCol] ?? ''); ?>"></div></div>
                <?php if ($pseudoCol && in_array($pseudoCol, $cols)): ?><div class="field"><label class="label">Pseudo</label><div class="control"><input class="input" name="pseudo" value="<?php echo htmlspecialchars($found[$pseudoCol] ?? ''); ?>"></div></div><?php endif; ?>
                <?php if (in_array($emailCol, $cols)): ?><div class="field"><label class="label">Email</label><div class="control"><input class="input" name="email" value="<?php echo htmlspecialchars($found[$emailCol] ?? ''); ?>"></div></div><?php endif; ?>
                <div class="field"><label class="label">Role</label><div class="control">
                  <div class="select">
                    <?php if (!empty($roleOptions) && in_array($roleIdCol, $cols) && !in_array($roleCol, $cols)): ?>
                      <select name="role_id">
                        <?php foreach ($roleOptions as $rid => $rname): ?>
                          <option value="<?php echo htmlspecialchars($rid); ?>" <?php echo (isset($found[$roleIdCol]) && $found[$roleIdCol]==$rid)? 'selected':''; ?>><?php echo htmlspecialchars($rname); ?></option>
                        <?php endforeach; ?>
                      </select>
                    <?php else: ?>
                      <select name="role">
                        <?php if (!empty($roleOptions)): ?>
                          <?php foreach ($roleOptions as $rid => $rname): ?>
                            <option value="<?php echo htmlspecialchars($rid); ?>" <?php echo (isset($found[$roleIdCol]) && $found[$roleIdCol]==$rid)? 'selected':''; ?>><?php echo htmlspecialchars($rname); ?></option>
                          <?php endforeach; ?>
                        <?php else: ?>
                          <option value="member" <?php echo (isset($found[$roleCol]) && $found[$roleCol]==='member')? 'selected':''; ?>>Membre</option>
                          <option value="admin" <?php echo (isset($found[$roleCol]) && $found[$roleCol]==='admin')? 'selected':''; ?>>Admin</option>
                        <?php endif; ?>
                      </select>
                    <?php endif; ?>
                  </div>
                </div>
                <?php if (in_array($passwordCol, $cols)): ?><div class="field"><label class="label">Nouveau mot de passe (laisser vide pour garder)</label><div class="control"><input class="input" type="password" name="password"></div></div><?php endif; ?>
                <div class="field"><div class="control"><button class="button is-primary" type="submit">Enregistrer</button></div></div>
              </form>
            <?php else: ?>
              <div class="notification is-warning">Utilisateur introuvable.</div>
        <?php endif; endif; ?>

      </div>
    </div>
  </div>
</div>
<!-- Roles modal -->
<div id="rolesModal" class="modal">
  <div class="modal-background" data-close></div>
  <div class="modal-card">
    <header class="modal-card-head">
      <p class="modal-card-title">Gérer les rôles</p>
      <button class="delete" aria-label="close" data-close></button>
    </header>
    <section class="modal-card-body">
      <div class="content">
        <?php if (!$rolesTable): ?>
          <div class="notification is-warning">Aucune table de rôles détectée.</div>
        <?php else: ?>
          <h4 class="title is-6">Rôles existants</h4>
          <ul>
            <?php foreach ($roleOptions as $rid => $rname): ?>
              <li style="margin-bottom:6px;" data-role-id="<?php echo htmlspecialchars($rid); ?>">
                <span class="role-name"><?php echo htmlspecialchars($rname); ?></span>
                <button class="button is-small is-info role-edit-btn" type="button" style="margin-left:8px;">Modifier</button>
                <form method="post" style="display:inline-block;margin-left:8px;" onsubmit="return confirm('Supprimer ce rôle ?');">
                  <input type="hidden" name="action" value="delete_role">
                  <input type="hidden" name="role_id" value="<?php echo htmlspecialchars($rid); ?>">
                  <button class="button is-small is-danger" type="submit">Supprimer</button>
                </form>
                <!-- inline edit form (hidden) -->
                <form method="post" class="role-edit-form" style="display:none;margin-top:6px;">
                  <input type="hidden" name="action" value="update_role">
                  <input type="hidden" name="role_id" value="<?php echo htmlspecialchars($rid); ?>">
                  <div class="field has-addons">
                    <div class="control is-expanded"><input class="input" name="role_name" value="<?php echo htmlspecialchars($rname); ?>"></div>
                    <div class="control"><button class="button is-primary" type="submit">Enregistrer</button></div>
                    <div class="control"><button type="button" class="button role-edit-cancel">Annuler</button></div>
                  </div>
                </form>
              </li>
            <?php endforeach; ?>
          </ul>
          <hr>
          <h4 class="title is-6">Créer un rôle</h4>
          <form method="post">
            <input type="hidden" name="action" value="create_role">
            <div class="field"><div class="control"><input class="input" name="role_name" placeholder="Nom du rôle"></div></div>
            <div class="field"><div class="control"><button class="button is-primary" type="submit">Créer</button></div></div>
          </form>
        <?php endif; ?>
      </div>
    </section>
    <footer class="modal-card-foot">
      <button class="button" data-close>Fermer</button>
    </footer>
  </div>
</div>

<script>
  (function(){
    // Create user modal
    var openCreateBtns = document.querySelectorAll('.openCreateUser');
    var createModal = document.getElementById('createUserModal');
    var createClosers = createModal ? createModal.querySelectorAll('[data-close]') : [];
    function openCreate() { if(!createModal) return; createModal.classList.add('is-active'); }
    function closeCreate() { if(!createModal) return; createModal.classList.remove('is-active'); }
    if (openCreateBtns && openCreateBtns.length) {
      openCreateBtns.forEach(function(b){ b.addEventListener('click', function(e){ e.preventDefault(); openCreate(); }); });
    }
    if (createClosers) createClosers.forEach(function(c){ c.addEventListener('click', function(e){ e.preventDefault(); closeCreate(); }); });

    // Roles modal
    var openRolesBtns = document.querySelectorAll('.openRoles');
    var rolesModal = document.getElementById('rolesModal');
    var rolesClosers = rolesModal ? rolesModal.querySelectorAll('[data-close]') : [];
    function openRoles() { if(!rolesModal) return; rolesModal.classList.add('is-active'); }
    function closeRoles() { if(!rolesModal) return; rolesModal.classList.remove('is-active'); }
    if (openRolesBtns && openRolesBtns.length) {
      openRolesBtns.forEach(function(b){ b.addEventListener('click', function(e){ e.preventDefault(); openRoles(); }); });
    }
    if (rolesClosers) rolesClosers.forEach(function(c){ c.addEventListener('click', function(e){ e.preventDefault(); closeRoles(); }); });

    // close modals on ESC
    document.addEventListener('keydown', function(e){ if (e.key === 'Escape') { closeCreate(); closeRoles(); } });
  // role edit toggle inside roles modal
  var roleEditBtns = document.querySelectorAll('.role-edit-btn');
  roleEditBtns.forEach(function(b){ b.addEventListener('click', function(){ var li = b.closest('li'); if(!li) return; var f = li.querySelector('.role-edit-form'); if(f) f.style.display = (f.style.display === 'none' || f.style.display === '') ? 'block' : 'none'; }); });
  var roleEditCancels = document.querySelectorAll('.role-edit-cancel');
  roleEditCancels.forEach(function(c){ c.addEventListener('click', function(){ var f = c.closest('.role-edit-form'); if(f) f.style.display = 'none'; }); });
  })();
</script>
</body>
</html>
