<?php
/**
 * Admin — Forum Structure (Categories & Boards)
 * --------------------------------------------------------
 * Access: admin only.
 * Full CRUD for categories and boards with sort ordering.
 */
$pageTitle = 'Forum Structure';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/forum_helpers.php';
requireRole('admin');

$flash = '';

/* ---- POST actions ---- */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateCSRF($_POST['csrf_token'] ?? '')) {
        $flash = '❌ CSRF validation failed.';
    } else {
        $action = $_POST['action'] ?? '';

        switch ($action) {

            /* ===== CATEGORIES ===== */
            case 'add_category':
                $name = trim($_POST['cat_name'] ?? '');
                $desc = trim($_POST['cat_desc'] ?? '');
                $sort = (int)($_POST['cat_sort'] ?? 0);
                if ($name === '') { $flash = '❌ Category name required.'; break; }
                $pdo->prepare('INSERT INTO `forum_categories` (`name`,`description`,`sort_order`) VALUES (?,?,?)')
                    ->execute([$name, $desc ?: null, $sort]);
                $flash = '✅ Category created.';
                break;

            case 'edit_category':
                $id   = (int)($_POST['category_id'] ?? 0);
                $name = trim($_POST['cat_name'] ?? '');
                $desc = trim($_POST['cat_desc'] ?? '');
                $sort = (int)($_POST['cat_sort'] ?? 0);
                if ($name === '' || $id < 1) { $flash = '❌ Invalid data.'; break; }
                $pdo->prepare('UPDATE `forum_categories` SET `name`=?,`description`=?,`sort_order`=? WHERE `category_id`=?')
                    ->execute([$name, $desc ?: null, $sort, $id]);
                $flash = '✅ Category updated.';
                break;

            case 'delete_category':
                $id = (int)($_POST['category_id'] ?? 0);
                /* Prevent if boards exist */
                $bc = $pdo->prepare('SELECT COUNT(*) FROM `forum_boards` WHERE `category_id`=?');
                $bc->execute([$id]);
                if ($bc->fetchColumn() > 0) {
                    $flash = '❌ Remove all boards from this category first.';
                } else {
                    $pdo->prepare('DELETE FROM `forum_categories` WHERE `category_id`=?')->execute([$id]);
                    $flash = '✅ Category deleted.';
                }
                break;

            /* ===== BOARDS ===== */
            case 'add_board':
                $catId = (int)($_POST['board_cat'] ?? 0);
                $name  = trim($_POST['board_name'] ?? '');
                $desc  = trim($_POST['board_desc'] ?? '');
                $sort  = (int)($_POST['board_sort'] ?? 0);
                if ($name === '' || $catId < 1) { $flash = '❌ Invalid data.'; break; }
                $pdo->prepare(
                    'INSERT INTO `forum_boards` (`category_id`,`name`,`description`,`sort_order`) VALUES (?,?,?,?)'
                )->execute([$catId, $name, $desc ?: null, $sort]);
                $flash = '✅ Board created.';
                break;

            case 'edit_board':
                $id    = (int)($_POST['board_id'] ?? 0);
                $catId = (int)($_POST['board_cat'] ?? 0);
                $name  = trim($_POST['board_name'] ?? '');
                $desc  = trim($_POST['board_desc'] ?? '');
                $sort  = (int)($_POST['board_sort'] ?? 0);
                if ($name === '' || $id < 1 || $catId < 1) { $flash = '❌ Invalid data.'; break; }
                $pdo->prepare(
                    'UPDATE `forum_boards` SET `category_id`=?,`name`=?,`description`=?,`sort_order`=? WHERE `board_id`=?'
                )->execute([$catId, $name, $desc ?: null, $sort, $id]);
                $flash = '✅ Board updated.';
                break;

            case 'delete_board':
                $id = (int)($_POST['board_id'] ?? 0);
                $tc = $pdo->prepare(
                    'SELECT COUNT(*) FROM `forum_threads` WHERE `board_id`=? AND `is_deleted`=0'
                );
                $tc->execute([$id]);
                if ($tc->fetchColumn() > 0) {
                    $flash = '❌ Delete or move all threads from this board first.';
                } else {
                    $pdo->prepare('DELETE FROM `forum_boards` WHERE `board_id`=?')->execute([$id]);
                    $flash = '✅ Board deleted.';
                }
                break;
        }
    }
}

/* ---- Determine edit mode ---- */
$editCat   = null;
$editBoard = null;
if (isset($_GET['edit_cat'])) {
    $s = $pdo->prepare('SELECT * FROM `forum_categories` WHERE `category_id`=?');
    $s->execute([(int)$_GET['edit_cat']]);
    $editCat = $s->fetch();
}
if (isset($_GET['edit_board'])) {
    $s = $pdo->prepare('SELECT * FROM `forum_boards` WHERE `board_id`=?');
    $s->execute([(int)$_GET['edit_board']]);
    $editBoard = $s->fetch();
}

/* ---- Fetch data ---- */
$categories = $pdo->query('SELECT * FROM `forum_categories` ORDER BY `sort_order` ASC, `category_id` ASC')->fetchAll();
$boards     = $pdo->query('SELECT * FROM `forum_boards` ORDER BY `sort_order` ASC, `board_id` ASC')->fetchAll();
$boardsByCat = [];
foreach ($boards as $b) $boardsByCat[$b['category_id']][] = $b;

require_once __DIR__ . '/../includes/header.php';
forumCSS();
?>

<div class="flex-between flex-between-mobile mb-1">
  <h1>🏗️ Forum Structure</h1>
  <div style="display:flex;gap:.4rem;flex-wrap:wrap;">
    <a href="/admin/index.php" class="btn btn-secondary btn-sm">&larr; Admin</a>
    <a href="/forum/index.php" class="btn btn-secondary btn-sm">View Forum</a>
  </div>
</div>

<?php if ($flash): ?>
  <div class="alert <?= str_starts_with($flash, '✅') ? 'alert-success' : 'alert-error' ?>">
    <?= e($flash) ?>
  </div>
<?php endif; ?>

<!-- ============ ADD / EDIT CATEGORY FORM ============ -->
<div class="card">
  <h3><?= $editCat ? '✏️ Edit Category' : '➕ Add Category' ?></h3>
  <form method="POST">
    <input type="hidden" name="csrf_token" value="<?= e(generateCSRF()) ?>">
    <input type="hidden" name="action" value="<?= $editCat ? 'edit_category' : 'add_category' ?>">
    <?php if ($editCat): ?>
      <input type="hidden" name="category_id" value="<?= $editCat['category_id'] ?>">
    <?php endif; ?>
    <div class="grid-3" style="gap:1rem;">
      <div class="form-group">
        <label>Name</label>
        <input type="text" name="cat_name" required
               value="<?= e($editCat['name'] ?? '') ?>">
      </div>
      <div class="form-group">
        <label>Description</label>
        <input type="text" name="cat_desc"
               value="<?= e($editCat['description'] ?? '') ?>">
      </div>
      <div class="form-group">
        <label>Sort Order</label>
        <input type="number" name="cat_sort" value="<?= $editCat['sort_order'] ?? 0 ?>">
      </div>
    </div>
    <div style="display:flex;gap:.5rem;flex-wrap:wrap;">
      <button type="submit" class="btn btn-primary">
        <?= $editCat ? 'Update Category' : 'Add Category' ?>
      </button>
      <?php if ($editCat): ?>
        <a href="/admin/forums.php" class="btn btn-secondary">Cancel</a>
      <?php endif; ?>
    </div>
  </form>
</div>

<!-- ============ ADD / EDIT BOARD FORM ============ -->
<div class="card">
  <h3><?= $editBoard ? '✏️ Edit Board' : '➕ Add Board' ?></h3>
  <form method="POST">
    <input type="hidden" name="csrf_token" value="<?= e(generateCSRF()) ?>">
    <input type="hidden" name="action" value="<?= $editBoard ? 'edit_board' : 'add_board' ?>">
    <?php if ($editBoard): ?>
      <input type="hidden" name="board_id" value="<?= $editBoard['board_id'] ?>">
    <?php endif; ?>
    <div class="grid-3" style="gap:1rem;">
      <div class="form-group">
        <label>Category</label>
        <select name="board_cat" required>
          <option value="">— Select —</option>
          <?php foreach ($categories as $c): ?>
            <option value="<?= $c['category_id'] ?>"
              <?= (($editBoard['category_id'] ?? 0) == $c['category_id']) ? 'selected' : '' ?>>
              <?= e($c['name']) ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="form-group">
        <label>Board Name</label>
        <input type="text" name="board_name" required
               value="<?= e($editBoard['name'] ?? '') ?>">
      </div>
      <div class="form-group">
        <label>Sort Order</label>
        <input type="number" name="board_sort" value="<?= $editBoard['sort_order'] ?? 0 ?>">
      </div>
    </div>
    <div class="form-group">
      <label>Description</label>
      <input type="text" name="board_desc"
             value="<?= e($editBoard['description'] ?? '') ?>">
    </div>
    <div style="display:flex;gap:.5rem;flex-wrap:wrap;">
      <button type="submit" class="btn btn-primary">
        <?= $editBoard ? 'Update Board' : 'Add Board' ?>
      </button>
      <?php if ($editBoard): ?>
        <a href="/admin/forums.php" class="btn btn-secondary">Cancel</a>
      <?php endif; ?>
    </div>
  </form>
</div>

<!-- ============ CURRENT STRUCTURE ============ -->
<div class="card">
  <h3>📂 Current Structure</h3>

  <?php if (empty($categories)): ?>
    <p style="color:var(--text-secondary);">No categories yet. Create one above.</p>
  <?php endif; ?>

  <?php foreach ($categories as $cat): ?>
    <div class="cat-section">
      <div class="cat-section-header">
        <div>
          <strong style="color:var(--accent-green);font-size:1rem;">
            <?= e($cat['name']) ?>
          </strong>
          <?php if ($cat['description']): ?>
            <span style="font-size:.8rem;color:var(--text-secondary);margin-left:.5rem;">
              — <?= e($cat['description']) ?>
            </span>
          <?php endif; ?>
          <span class="badge badge-blue" style="margin-left:.4rem;">
            Order: <?= $cat['sort_order'] ?>
          </span>
        </div>
        <div style="display:flex;gap:.3rem;flex-wrap:wrap;">
          <a href="/admin/forums.php?edit_cat=<?= $cat['category_id'] ?>"
             class="btn btn-secondary btn-sm">Edit</a>
          <form method="POST" style="display:inline;">
            <input type="hidden" name="csrf_token" value="<?= e(generateCSRF()) ?>">
            <input type="hidden" name="action" value="delete_category">
            <input type="hidden" name="category_id" value="<?= $cat['category_id'] ?>">
            <button type="submit" class="btn btn-danger btn-sm"
                    onclick="return confirm('Delete this category?');">Delete</button>
          </form>
        </div>
      </div>

      <?php
        $catBoards = $boardsByCat[$cat['category_id']] ?? [];
        if (empty($catBoards)):
      ?>
        <div class="board-row" style="color:var(--text-secondary);font-size:.85rem;border-radius:0 0 var(--radius) var(--radius);">
          No boards in this category.
        </div>
      <?php else: foreach ($catBoards as $b): ?>
        <div class="board-row">
          <div style="min-width:0;">
            <strong><?= e($b['name']) ?></strong>
            <?php if ($b['description']): ?>
              <span style="font-size:.78rem;color:var(--text-secondary);margin-left:.4rem;">
                — <?= e($b['description']) ?>
              </span>
            <?php endif; ?>
            <div style="font-size:.72rem;color:var(--text-secondary);margin-top:.15rem;">
              <?= $b['thread_count'] ?> threads &middot;
              <?= $b['post_count'] ?> posts &middot;
              Order: <?= $b['sort_order'] ?>
            </div>
          </div>
          <div style="display:flex;gap:.3rem;flex-wrap:wrap;flex-shrink:0;">
            <a href="/admin/forums.php?edit_board=<?= $b['board_id'] ?>"
               class="btn btn-secondary btn-sm">Edit</a>
            <form method="POST" style="display:inline;">
              <input type="hidden" name="csrf_token" value="<?= e(generateCSRF()) ?>">
              <input type="hidden" name="action" value="delete_board">
              <input type="hidden" name="board_id" value="<?= $b['board_id'] ?>">
              <button type="submit" class="btn btn-danger btn-sm"
                      onclick="return confirm('Delete board?');">Delete</button>
            </form>
          </div>
        </div>
      <?php endforeach; endif; ?>
    </div>
  <?php endforeach; ?>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>