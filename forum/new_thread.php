<?php
/**
 * New Thread — Create Form & Handler
 * --------------------------------------------------------
 * Access: Authenticated users.
 *
 * Transaction: Thread + opening post are inserted inside
 *   a BEGIN/COMMIT block. Board counters are updated
 *   atomically. On failure the transaction rolls back,
 *   leaving zero orphaned rows.
 */
$pageTitle = 'New Thread';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
requireLogin();

/* ---- Resolve target board ---- */
$boardId = (int)($_GET['board'] ?? $_POST['board_id'] ?? 0);
if ($boardId < 1) redirect('/forum/index.php');

$boardStmt = $pdo->prepare(
    'SELECT b.*, c.name AS category_name
     FROM `forum_boards` b
     JOIN `forum_categories` c ON c.category_id = b.category_id
     WHERE b.board_id = ?'
);
$boardStmt->execute([$boardId]);
$board = $boardStmt->fetch();
if (!$board) redirect('/forum/index.php');

$pageTitle = 'New Thread — ' . $board['name'];
$error = '';

/* ---- Handle submission ---- */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if (!validateCSRF($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid session token. Please reload.';
    } else {
        $title = trim($_POST['title'] ?? '');
        $body  = trim($_POST['body']  ?? '');

        if (strlen($title) < 3 || strlen($title) > 200) {
            $error = 'Title must be between 3 and 200 characters.';
        } elseif (strlen($body) < 10) {
            $error = 'Post body must be at least 10 characters.';
        } elseif (strlen($body) > 50000) {
            $error = 'Post body is too long (max 50 000 characters).';
        } else {
            /*
             * Wrap in a transaction so thread + post + counter
             * updates either ALL succeed or ALL roll back.
             */
            $pdo->beginTransaction();
            try {
                /* 1. Create the thread row */
                $tIns = $pdo->prepare(
                    'INSERT INTO `forum_threads`
                        (`board_id`, `user_id`, `title`, `last_post_at`)
                     VALUES (?, ?, ?, NOW())'
                );
                $tIns->execute([$boardId, $_SESSION['user_id'], $title]);
                $newThreadId = (int)$pdo->lastInsertId();

                /* 2. Create the opening post */
                $pIns = $pdo->prepare(
                    'INSERT INTO `forum_posts`
                        (`thread_id`, `user_id`, `body`)
                     VALUES (?, ?, ?)'
                );
                $pIns->execute([$newThreadId, $_SESSION['user_id'], $body]);
                $newPostId = (int)$pdo->lastInsertId();

                /* 3. Update board cached counters */
                $pdo->prepare(
                    'UPDATE `forum_boards`
                     SET `thread_count` = `thread_count` + 1,
                         `post_count`   = `post_count` + 1,
                         `last_post_id` = ?
                     WHERE `board_id` = ?'
                )->execute([$newPostId, $boardId]);

                /* 4. Increment user's cached post_count */
                $pdo->prepare(
                    'UPDATE `users`
                     SET `post_count` = `post_count` + 1
                     WHERE `user_id` = ?'
                )->execute([$_SESSION['user_id']]);

                $pdo->commit();

                redirect("/forum/thread.php?id=$newThreadId");

            } catch (\Exception $ex) {
                $pdo->rollBack();
                error_log('New thread error: ' . $ex->getMessage());
                $error = 'Something went wrong. Please try again.';
            }
        }
    }
}

require_once __DIR__ . '/../includes/header.php';
?>

<!-- Breadcrumb -->
<div style="font-size:.85rem;color:var(--text-secondary);margin-bottom:1rem;">
  <a href="/forum/index.php">Forum</a>
  <span style="margin:0 .4rem;">›</span>
  <a href="/forum/board.php?id=<?= $boardId ?>"><?= e($board['name']) ?></a>
  <span style="margin:0 .4rem;">›</span>
  <span style="color:var(--text-primary);">New Thread</span>
</div>

<div style="max-width:740px;">
  <div class="card">
    <h2>✏️ Create New Thread</h2>
    <p style="color:var(--text-secondary);font-size:.85rem;margin-bottom:1rem;">
      Posting in <strong style="color:var(--accent-green);"><?= e($board['name']) ?></strong>
    </p>

    <?php if ($error): ?>
      <div class="alert alert-error"><?= e($error) ?></div>
    <?php endif; ?>

    <form method="POST">
      <input type="hidden" name="csrf_token" value="<?= e(generateCSRF()) ?>">
      <input type="hidden" name="board_id" value="<?= $boardId ?>">

      <div class="form-group">
        <label for="title">Thread Title</label>
        <input type="text" id="title" name="title" required
               minlength="3" maxlength="200"
               placeholder="Give your thread a clear title"
               value="<?= e($_POST['title'] ?? '') ?>">
      </div>

      <div class="form-group">
        <label for="body">Post Body</label>
        <textarea id="body" name="body" rows="10" required
                  minlength="10" maxlength="50000"
                  placeholder="Write your opening post…"><?= e($_POST['body'] ?? '') ?></textarea>
      </div>

      <div style="display:flex;gap:.5rem;">
        <button type="submit" class="btn btn-primary">Create Thread</button>
        <a href="/forum/board.php?id=<?= $boardId ?>" class="btn btn-secondary">Cancel</a>
      </div>
    </form>
  </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>