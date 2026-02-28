<?php
/**
 * Moderator Panel — Script Management
 * --------------------------------------------------------
 * Access: role >= moderator.
 * Mods can add new scripts or edit existing ones.
 */
$pageTitle = 'Manage Scripts';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
requireRole('moderator');

$flash = '';
$editScript = null;

/* ---- Handle POST actions ---- */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateCSRF($_POST['csrf_token'] ?? '')) {
        $flash = '❌ CSRF validation failed.';
    } else {
        $action = $_POST['form_action'] ?? '';

        $title      = trim($_POST['title'] ?? '');
        $description= trim($_POST['description'] ?? '');
        $version    = trim($_POST['version'] ?? '1.0.0');
        $category   = trim($_POST['category'] ?? 'Skilling');
        $isPremium  = isset($_POST['is_premium']) ? 1 : 0;

        if ($title === '') {
            $flash = '❌ Title is required.';
        } elseif ($action === 'add') {
            $stmt = $pdo->prepare(
                'INSERT INTO `scripts` (`title`,`description`,`version`,`category`,`is_premium`,`author_id`)
                 VALUES (?,?,?,?,?,?)'
            );
            $stmt->execute([$title, $description, $version, $category, $isPremium, $_SESSION['user_id']]);
            $flash = '✅ Script added successfully.';
        } elseif ($action === 'edit') {
            $scriptId = (int)($_POST['script_id'] ?? 0);
            $stmt = $pdo->prepare(
                'UPDATE `scripts`
                 SET `title`=?, `description`=?, `version`=?, `category`=?, `is_premium`=?
                 WHERE `script_id`=?'
            );
            $stmt->execute([$title, $description, $version, $category, $isPremium, $scriptId]);
            $flash = '✅ Script updated.';
        } elseif ($action === 'delete') {
            $scriptId = (int)($_POST['script_id'] ?? 0);
            $pdo->prepare('DELETE FROM `scripts` WHERE `script_id`=?')->execute([$scriptId]);
            $flash = '✅ Script deleted.';
        }
    }
}

/* ---- Load script for editing (GET ?edit=ID) ---- */
if (isset($_GET['edit'])) {
    $eStmt = $pdo->prepare('SELECT * FROM `scripts` WHERE `script_id` = ?');
    $eStmt->execute([(int)$_GET['edit']]);
    $editScript = $eStmt->fetch();
}

/* ---- Fetch all scripts ---- */
$scripts = $pdo->query(
    'SELECT s.*, u.username AS author_name
     FROM `scripts` s
     LEFT JOIN `users` u ON u.user_id = s.author_id
     ORDER BY s.script_id DESC'
)->fetchAll();

require_once __DIR__ . '/../includes/header.php';
?>

<h1 class="mb-1">📜 Script Management</h1>

<?php if ($flash): ?>
  <div class="alert <?= str_starts_with($flash, '✅') ? 'alert-success' : 'alert-error' ?>">
    <?= e($flash) ?>
  </div>
<?php endif; ?>

<!-- Add / Edit Form -->
<div class="card">
  <h3><?= $editScript ? '✏️ Edit Script' : '➕ Add New Script' ?></h3>
  <form method="POST">
    <input type="hidden" name="csrf_token" value="<?= e(generateCSRF()) ?>">
    <input type="hidden" name="form_action" value="<?= $editScript ? 'edit' : 'add' ?>">
    <?php if ($editScript): ?>
      <input type="hidden" name="script_id" value="<?= $editScript['script_id'] ?>">
    <?php endif; ?>

    <div class="grid-3" style="gap:1rem;">
      <div class="form-group">
        <label for="title">Title</label>
        <input type="text" id="title" name="title" required
               value="<?= e($editScript['title'] ?? '') ?>">
      </div>
      <div class="form-group">
        <label for="version">Version</label>
        <input type="text" id="version" name="version"
               value="<?= e($editScript['version'] ?? '1.0.0') ?>">
      </div>
      <div class="form-group">
        <label for="category">Category</label>
        <select id="category" name="category">
          <?php
            $cats = ['Skilling','Combat','Minigames','Moneymaking','Questing','Utility'];
            foreach ($cats as $c):
          ?>
            <option value="<?= $c ?>"
              <?= (($editScript['category'] ?? '') === $c) ? 'selected' : '' ?>>
              <?= $c ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>
    </div>

    <div class="form-group">
      <label for="description">Description</label>
      <textarea id="description" name="description"><?= e($editScript['description'] ?? '') ?></textarea>
    </div>

    <div class="form-group">
      <label>
        <input type="checkbox" name="is_premium"
               <?= ($editScript['is_premium'] ?? 0) ? 'checked' : '' ?>>
        Premium Script
      </label>
    </div>

    <button type="submit" class="btn btn-primary">
      <?= $editScript ? 'Update Script' : 'Add Script' ?>
    </button>
    <?php if ($editScript): ?>
      <a href="/mod/scripts.php" class="btn btn-secondary">Cancel</a>
    <?php endif; ?>
  </form>
</div>

<!-- Existing Scripts Table -->
<div class="card">
  <h3>All Scripts</h3>
  <table>
    <thead>
      <tr><th>ID</th><th>Title</th><th>Cat</th><th>Ver</th><th>Type</th><th>Author</th><th>Actions</th></tr>
    </thead>
    <tbody>
      <?php foreach ($scripts as $s): ?>
        <tr>
          <td><?= $s['script_id'] ?></td>
          <td><?= e($s['title']) ?></td>
          <td><?= e($s['category']) ?></td>
          <td style="font-family:var(--font-mono);"><?= e($s['version']) ?></td>
          <td>
            <?= $s['is_premium']
              ? '<span class="badge badge-purple">Premium</span>'
              : '<span class="badge badge-green">Free</span>' ?>
          </td>
          <td><?= e($s['author_name'] ?? '—') ?></td>
          <td>
            <a href="/mod/scripts.php?edit=<?= $s['script_id'] ?>" class="btn btn-secondary btn-sm">Edit</a>
            <form method="POST" style="display:inline;">
              <input type="hidden" name="csrf_token" value="<?= e(generateCSRF()) ?>">
              <input type="hidden" name="form_action" value="delete">
              <input type="hidden" name="script_id" value="<?= $s['script_id'] ?>">
              <button type="submit" class="btn btn-danger btn-sm"
                      onclick="return confirm('Delete this script?');">Del</button>
            </form>
          </td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>

<p><a href="/mod/index.php">&larr; Back to Mod Panel</a></p>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>