<?php
@ini_set('display_errors', '1');
@ini_set('display_startup_errors', '1');
error_reporting(E_ALL);

session_start();
// simple admin check
$isAdmin = false;
if (isset($_SESSION['is_admin']) && $_SESSION['is_admin']) $isAdmin = true;
if (isset($_SESSION['role']) && $_SESSION['role'] === 'admin') $isAdmin = true;
if (isset($_SESSION['role_id']) && $_SESSION['role_id'] == 1) $isAdmin = true;
if (isset($_SESSION['user']) && $_SESSION['user'] === 'admin') $isAdmin = true;
if (!isset($_SESSION['user']) || !$isAdmin) {
    header('Location: ../membre/login.php');
    exit;
}

require_once __DIR__ . '/inc/db.php';

$error = '';
$success = '';

// actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if ($action === 'create') {
        $name = trim($_POST['name'] ?? '');
        if ($name === '') { $error = 'Nom requis.'; }
        else {
            try {
                $stmt = $pdo->prepare('INSERT INTO roles (name) VALUES (:n)');
                $stmt->execute([':n' => $name]);
                $success = 'Rôle créé.';
            } catch (Exception $e) { $error = 'Erreur: ' . $e->getMessage(); }
        }
    } elseif ($action === 'update') {
        $id = $_POST['id'] ?? '';
        $name = trim($_POST['name'] ?? '');
        if ($id === '' || $name === '') { $error = 'ID et nom requis.'; }
        else {
            try {
                $stmt = $pdo->prepare('UPDATE roles SET name = :n WHERE id = :id');
                $stmt->execute([':n' => $name, ':id' => $id]);
                $success = 'Rôle mis à jour.';
            } catch (Exception $e) { $error = 'Erreur: ' . $e->getMessage(); }
        }
    } elseif ($action === 'delete') {
        $id = $_POST['id'] ?? '';
        if ($id === '') { $error = 'ID requis.'; }
        else {
            try {
                $stmt = $pdo->prepare('DELETE FROM roles WHERE id = :id');
                $stmt->execute([':id' => $id]);
                $success = 'Rôle supprimé.';
            } catch (Exception $e) { $error = 'Erreur: ' . $e->getMessage(); }
        }
    }
}

// read roles
try {
    $roles = $pdo->query('SELECT * FROM roles ORDER BY id ASC')->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) { $roles = []; $error = 'Erreur lecture roles: ' . $e->getMessage(); }

?>
<!DOCTYPE html>
<html>
<head>
  <meta charset="utf-8" />
  <title>Gestion des rôles - Admin</title>
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bulma@0.9.4/css/bulma.min.css">
  <style> body{ background-image:url('../fond.jpg'); background-size:cover; min-height:100vh; margin:0; } .container{ padding:28px; }</style>
</head>
<body>
<?php include_once __DIR__ . '/../menu.php'; ?>
  <div class="container">
    <div class="card" style="max-width:900px;margin:0 auto;">
      <header class="card-header"><p class="card-header-title">Gestion des rôles</p><a class="button is-light" href="users.php" style="margin:10px;">← Utilisateurs</a></header>
      <div class="card-content">
        <?php if ($error): ?><div class="notification is-danger"><?php echo htmlspecialchars($error); ?></div><?php endif; ?>
        <?php if ($success): ?><div class="notification is-primary"><?php echo htmlspecialchars($success); ?></div><?php endif; ?>

        <form method="post" style="margin-bottom:1rem;">
          <input type="hidden" name="action" value="create">
          <div class="field has-addons">
            <div class="control is-expanded"><input class="input" name="name" placeholder="Nouveau rôle (ex: admin)"></div>
            <div class="control"><button class="button is-success" type="submit">Créer</button></div>
          </div>
        </form>

        <table class="table is-fullwidth is-striped">
          <thead><tr><th>ID</th><th>Nom</th><th>Actions</th></tr></thead>
          <tbody>
            <?php foreach ($roles as $r): ?>
              <tr>
                <td><?php echo htmlspecialchars($r['id']); ?></td>
                <td><?php echo htmlspecialchars($r['name']); ?></td>
                <td>
                  <a class="button is-small is-info" href="?edit=<?php echo urlencode($r['id']); ?>">Modifier</a>
                  <form method="post" style="display:inline-block;margin-left:6px;" onsubmit="return confirm('Supprimer ce rôle ?');">
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="id" value="<?php echo htmlspecialchars($r['id']); ?>">
                    <button class="button is-small is-danger" type="submit">Supprimer</button>
                  </form>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>

        <?php if (isset($_GET['edit'])):
            $eid = $_GET['edit'];
            $found = null;
            foreach ($roles as $rr) if ($rr['id']==$eid) { $found = $rr; break; }
            if ($found): ?>
              <hr>
              <h4 class="title is-5">Modifier le rôle #<?php echo htmlspecialchars($found['id']); ?></h4>
              <form method="post">
                <input type="hidden" name="action" value="update">
                <input type="hidden" name="id" value="<?php echo htmlspecialchars($found['id']); ?>">
                <div class="field"><div class="control"><input class="input" name="name" value="<?php echo htmlspecialchars($found['name']); ?>"></div></div>
                <div class="field"><div class="control"><button class="button is-primary" type="submit">Enregistrer</button></div></div>
              </form>
        <?php else: ?><div class="notification is-warning">Rôle introuvable.</div><?php endif; endif; ?>

      </div>
    </div>
  </div>
</body>
</html>
