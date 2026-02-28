<?php
/**
 * Edit Post
 * Access: Post author OR moderator/admin.
 */
$pageTitle = 'Edit Post';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/forum_helpers.php';
requireLogin();

$postId = (int)($_GET['id'] ?? 0);
if ($postId < 1) redirect('/forum/index.php');

$pStmt = $pdo->prepare(
    'SELECT p.*, t.title AS thread_title, t.thread_id, t.board_id, t.is_locked
     FROM `forum_posts` p
     JOIN `forum_threads` t ON t.thread_id = p.thread_id
     WHERE p.post_id = ? AND p.is_deleted = 0'
);
$pStmt->execute([$postId]);
$post = $pStmt->fetch();
if (!$post) redirect('/forum/index.php');

/* Auth check: author or mod */
$isAuthor = ($post['user_id'] == $_SESSION['user_id']);
if (!$isAuthor && !hasRole('moderator')) {
    http_response_code(403);
    exit('<h1>403 Forbidden</h1>');
}

$error   = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateCSRF($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid session token.';
    } else {
        $body = trim($_POST['body'] ?? '');
        if (strlen($body) < 2) {
            $error = 'Post must be at least 2 characters.';
        } elseif (strlen($body) > 50000) {
            $error = 'Post too long.';
        } else {
            $pdo->prepare(
                'UPDATE `forum_posts`
                 SET `body` = ?, `updated_at` = NOW(), `edited_by` = ?
                 WHERE `post_id` = ?'
            )->execute([$body, $_SESSION['user_id'], $postId]);

            logAction($pdo, $_SESSION['user_id'], "edit_post:$postId");

            /* Calculate which page this post is on */
            $posCount = $pdo->prepare(
                'SELECT COUNT(*) FROM `forum_posts`
                 WHERE `thread_id` = ? AND `is_deleted` = 0 AND `created_at` <= (
                     SELECT `created_at` FROM `forum_posts` WHERE `post_id` = ?
                 )'
            );
            $posCount->execute([$post['thread_id'], $postId]);
            $position = (int)$posCount->fetchColumn();
            $pg = max(1, (int)ceil($position / 15));

            redirect("/forum/thread.php?id={$post['thread_id']}&page=$pg#post-$postId");
        }
    }
}

require_once __DIR__ . '/../includes/header.php';
forumCSS();
?>

<div class="breadcrumb">
  <a href="/forum/index.php">Forum</a><span class="sep">›</span>
  <a href="/forum/thread.php?id=<?= $post['thread_id'] ?>"><?= e(mb_strimwidth($post['thread_title'],0,30,'…')) ?></a><span class="sep">›</span>
  <span style="color:var(--text-primary);">Edit Post</span>
</div>

<div style="max-width:740px;">
  <div class="card">
    <h2>✏️ Edit Post #<?= $postId ?></h2>

    <?php if ($post['is_locked'] && !hasRole('moderator')): ?>
      <div class="alert alert-warn">This thread is locked. Only moderators can edit posts in locked threads.</div>
    <?php else: ?>
      <?php if ($error): ?><div class="alert alert-error"><?= e($error) ?></div><?php endif; ?>
      <form method="POST">
        <input type="hidden" name="csrf_token" value="<?= e(generateCSRF()) ?>">
        <div class="form-group">
          <label for="body">Post Content</label>
          <textarea id="body" name="body" rows="10" required minlength="2" maxlength="50000"><?= e($post['body']) ?></textarea>
        </div>
        <div style="display:flex;gap:.5rem;flex-wrap:wrap;">
          <button type="submit" class="btn btn-primary">Save Changes</button>
          <a href="/forum/thread.php?id=<?= $post['thread_id'] ?>" class="btn btn-secondary">Cancel</a>
        </div>
      </form>

      <!-- Preview -->
      <div class="mt-2">
        <h3 style="font-size:.9rem;color:var(--text-secondary);">Original Post:</h3>
        <div class="card" style="background:var(--bg-secondary);margin-top:.5rem;">
          <div class="post-content"><?= formatPost($post['body']) ?></div>
        </div>
      </div>
    <?php endif; ?>
  </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>