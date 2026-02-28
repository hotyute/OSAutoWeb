<?php
$pageTitle = 'New Thread';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/forum_helpers.php';
requireLogin();

$boardId = (int)($_GET['board'] ?? $_POST['board_id'] ?? 0);

/* If no board specified, show board picker */
$allBoards = $pdo->query(
    'SELECT b.board_id, b.name, c.name AS cat_name
     FROM `forum_boards` b
     JOIN `forum_categories` c ON c.category_id = b.category_id
     ORDER BY c.sort_order, b.sort_order'
)->fetchAll();

$board = null;
if ($boardId > 0) {
    $bs = $pdo->prepare('SELECT b.*, c.name AS category_name FROM `forum_boards` b JOIN `forum_categories` c ON c.category_id=b.category_id WHERE b.board_id=?');
    $bs->execute([$boardId]);
    $board = $bs->fetch();
}

$pageTitle = 'New Thread' . ($board ? ' — ' . $board['name'] : '');
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $board) {
    if (!validateCSRF($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid session token.';
    } else {
        $title = trim($_POST['title'] ?? '');
        $body  = trim($_POST['body'] ?? '');
        if (strlen($title) < 3 || strlen($title) > 200) {
            $error = 'Title must be 3–200 characters.';
        } elseif (strlen($body) < 10) {
            $error = 'Body must be at least 10 characters.';
        } elseif (strlen($body) > 50000) {
            $error = 'Body too long.';
        } else {
            $pdo->beginTransaction();
            try {
                $pdo->prepare('INSERT INTO `forum_threads` (`board_id`,`user_id`,`title`,`last_post_at`) VALUES (?,?,?,NOW())')
                    ->execute([$boardId, $_SESSION['user_id'], $title]);
                $newThreadId = (int)$pdo->lastInsertId();
                $pdo->prepare('INSERT INTO `forum_posts` (`thread_id`,`user_id`,`body`) VALUES (?,?,?)')
                    ->execute([$newThreadId, $_SESSION['user_id'], $body]);
                $newPostId = (int)$pdo->lastInsertId();
                $pdo->prepare('UPDATE `forum_boards` SET `thread_count`=`thread_count`+1,`post_count`=`post_count`+1,`last_post_id`=? WHERE `board_id`=?')
                    ->execute([$newPostId, $boardId]);
                $pdo->prepare('UPDATE `users` SET `post_count`=`post_count`+1 WHERE `user_id`=?')
                    ->execute([$_SESSION['user_id']]);
                $pdo->commit();
                redirect("/forum/thread.php?id=$newThreadId");
            } catch (\Exception $ex) {
                $pdo->rollBack();
                error_log('New thread: ' . $ex->getMessage());
                $error = 'Something went wrong.';
            }
        }
    }
}

require_once __DIR__ . '/../includes/header.php';
forumCSS();
?>

<div class="breadcrumb">
  <a href="/forum/index.php">Forum</a><span class="sep">›</span>
  <?php if ($board): ?>
    <a href="/forum/board.php?id=<?= $boardId ?>"><?= e($board['name']) ?></a><span class="sep">›</span>
  <?php endif; ?>
  <span style="color:var(--text-primary);">New Thread</span>
</div>

<div style="max-width:740px;">
  <div class="card">
    <h2>✏️ Create New Thread</h2>
    <?php if ($error): ?><div class="alert alert-error"><?= e($error) ?></div><?php endif; ?>

    <form method="POST">
      <input type="hidden" name="csrf_token" value="<?= e(generateCSRF()) ?>">

      <!-- Board picker -->
      <div class="form-group">
        <label for="board_id">Board</label>
        <select name="board_id" id="board_id" required
                onchange="window.location='/forum/new_thread.php?board='+this.value;">
          <option value="">— Select Board —</option>
          <?php foreach ($allBoards as $ab): ?>
            <option value="<?= $ab['board_id'] ?>" <?= $ab['board_id']==$boardId?'selected':'' ?>>
              <?= e($ab['cat_name']) ?> › <?= e($ab['name']) ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>

      <?php if ($board): ?>
        <div class="form-group">
          <label for="title">Thread Title</label>
          <input type="text" id="title" name="title" required minlength="3" maxlength="200"
                 placeholder="Clear descriptive title" value="<?= e($_POST['title'] ?? '') ?>">
        </div>
        <div class="form-group">
          <label for="body">Post Body</label>
          <p style="font-size:.78rem;color:var(--text-secondary);margin-bottom:.3rem;">
            Lines starting with <code style="background:var(--bg-secondary);padding:1px 4px;border-radius:3px;">&gt; </code> will be displayed as quotes.
          </p>
          <textarea id="body" name="body" rows="10" required minlength="10" maxlength="50000"
                    placeholder="Write your opening post…"><?= e($_POST['body'] ?? '') ?></textarea>
        </div>
        <div style="display:flex;gap:.5rem;flex-wrap:wrap;">
          <button type="submit" class="btn btn-primary">Create Thread</button>
          <a href="/forum/board.php?id=<?= $boardId ?>" class="btn btn-secondary">Cancel</a>
        </div>
      <?php else: ?>
        <p style="color:var(--text-secondary);">Select a board above to start a new thread.</p>
      <?php endif; ?>
    </form>
  </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>