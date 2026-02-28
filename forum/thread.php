<?php
/**
 * Thread View — Posts, Pagination, Reply
 * --------------------------------------------------------
 * Access: Authenticated users.
 *
 * Pagination: Total post count derived from the cached
 *   reply_count + 1 (for the opening post). 15 posts/page.
 *
 * Reply: Inserts a new post, increments thread.reply_count,
 *   updates thread.last_post_at, board.post_count, and
 *   board.last_post_id — all in prepared statements.
 *   Replies are blocked when thread.is_locked = 1.
 *
 * Moderation: Mods/admins see a "Delete" button on each post,
 *   routed through forum/moderate.php.
 */
$pageTitle = 'Thread';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
requireLogin();

/* ---- Resolve thread ---- */
$threadId = (int)($_GET['id'] ?? 0);
if ($threadId < 1) redirect('/forum/index.php');

$tStmt = $pdo->prepare(
    'SELECT t.*, u.username AS op_username,
            b.name AS board_name, b.board_id
     FROM `forum_threads` t
     JOIN `users` u ON u.user_id = t.user_id
     JOIN `forum_boards` b ON b.board_id = t.board_id
     WHERE t.thread_id = ? AND t.is_deleted = 0'
);
$tStmt->execute([$threadId]);
$thread = $tStmt->fetch();
if (!$thread) redirect('/forum/index.php');

$pageTitle = e($thread['title']);

/* ---- Increment view counter (cheap UPDATE, no read) ---- */
$pdo->prepare('UPDATE `forum_threads` SET `views` = `views` + 1 WHERE `thread_id` = ?')
    ->execute([$threadId]);

/* ---- Pagination via cached reply_count ---- */
$perPage    = 15;
$totalPosts = (int)$thread['reply_count'] + 1; /* replies + OP */
$totalPages = max(1, (int)ceil($totalPosts / $perPage));
$page       = max(1, min((int)($_GET['page'] ?? 1), $totalPages));
$offset     = ($page - 1) * $perPage;

/* ---- Fetch visible posts for this page ---- */
$pStmt = $pdo->prepare(
    'SELECT p.*, u.username, u.role AS user_role,
            u.post_count AS user_posts, u.signature,
            u.created_at AS user_joined
     FROM `forum_posts` p
     JOIN `users` u ON u.user_id = p.user_id
     WHERE p.thread_id = ? AND p.is_deleted = 0
     ORDER BY p.created_at ASC
     LIMIT ? OFFSET ?'
);
$pStmt->execute([$threadId, $perPage, $offset]);
$posts = $pStmt->fetchAll();

/* ---- Handle reply submission ---- */
$replyErr = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if ($thread['is_locked']) {
        $replyErr = 'This thread is locked.';
    } elseif (!validateCSRF($_POST['csrf_token'] ?? '')) {
        $replyErr = 'Invalid session token. Please reload.';
    } else {
        $body = trim($_POST['body'] ?? '');

        if (strlen($body) < 2) {
            $replyErr = 'Reply must be at least 2 characters.';
        } elseif (strlen($body) > 50000) {
            $replyErr = 'Reply is too long (max 50 000 characters).';
        } else {
            /* Insert the post */
            $ins = $pdo->prepare(
                'INSERT INTO `forum_posts` (`thread_id`, `user_id`, `body`) VALUES (?, ?, ?)'
            );
            $ins->execute([$threadId, $_SESSION['user_id'], $body]);
            $newPostId = (int)$pdo->lastInsertId();

            /* Update thread cached stats */
            $pdo->prepare(
                'UPDATE `forum_threads`
                 SET `reply_count` = `reply_count` + 1,
                     `last_post_at` = NOW()
                 WHERE `thread_id` = ?'
            )->execute([$threadId]);

            /* Update board cached stats */
            $pdo->prepare(
                'UPDATE `forum_boards`
                 SET `post_count`   = `post_count` + 1,
                     `last_post_id` = ?
                 WHERE `board_id` = ?'
            )->execute([$newPostId, $thread['board_id']]);

            /* Increment author's cached post_count */
            $pdo->prepare(
                'UPDATE `users` SET `post_count` = `post_count` + 1 WHERE `user_id` = ?'
            )->execute([$_SESSION['user_id']]);

            /* Redirect to the last page so user sees their new post */
            $newTotal  = $totalPosts + 1;
            $lastPage  = max(1, (int)ceil($newTotal / $perPage));
            redirect("/forum/thread.php?id=$threadId&page=$lastPage#post-$newPostId");
        }
    }
}

/* ---- Pagination helper (same compact renderer) ---- */
if (!function_exists('pgHtml')) {
    function pgHtml(int $cur, int $tot, string $base): string {
        if ($tot <= 1) return '';
        $s = str_contains($base, '?') ? '&' : '?';
        $h = '<div style="display:flex;gap:.3rem;flex-wrap:wrap;margin:1rem 0;align-items:center;">';
        if ($cur > 1)
            $h .= '<a href="'.e($base.$s.'page='.($cur-1)).'" class="btn btn-sm btn-secondary">&laquo;</a>';
        $st = max(1, $cur - 3); $en = min($tot, $cur + 3);
        if ($st > 1) {
            $h .= '<a href="'.e($base.$s.'page=1').'" class="btn btn-sm btn-secondary">1</a>';
            if ($st > 2) $h .= '<span style="padding:0 .3rem;color:var(--text-secondary);">…</span>';
        }
        for ($i = $st; $i <= $en; $i++) {
            $c = $i === $cur ? 'btn-primary' : 'btn-secondary';
            $h .= '<a href="'.e($base.$s.'page='.$i).'" class="btn btn-sm '.$c.'">'.$i.'</a>';
        }
        if ($en < $tot) {
            if ($en < $tot - 1) $h .= '<span style="padding:0 .3rem;color:var(--text-secondary);">…</span>';
            $h .= '<a href="'.e($base.$s.'page='.$tot).'" class="btn btn-sm btn-secondary">'.$tot.'</a>';
        }
        if ($cur < $tot)
            $h .= '<a href="'.e($base.$s.'page='.($cur+1)).'" class="btn btn-sm btn-secondary">&raquo;</a>';
        $h .= '</div>';
        return $h;
    }
}

require_once __DIR__ . '/../includes/header.php';
?>

<!-- Breadcrumb -->
<div style="font-size:.85rem;color:var(--text-secondary);margin-bottom:1rem;">
  <a href="/forum/index.php">Forum</a>
  <span style="margin:0 .4rem;">›</span>
  <a href="/forum/board.php?id=<?= $thread['board_id'] ?>"><?= e($thread['board_name']) ?></a>
  <span style="margin:0 .4rem;">›</span>
  <span style="color:var(--text-primary);"><?= e($thread['title']) ?></span>
</div>

<!-- Thread header -->
<div class="flex-between mb-1">
  <div>
    <h1 style="font-size:1.4rem;margin-bottom:.25rem;">
      <?php if ($thread['is_sticky']): ?>
        <span class="badge badge-amber">📌 Sticky</span>
      <?php endif; ?>
      <?php if ($thread['is_locked']): ?>
        <span class="badge badge-red">🔒 Locked</span>
      <?php endif; ?>
      <?= e($thread['title']) ?>
    </h1>
    <span style="font-size:.82rem;color:var(--text-secondary);">
      Started by <strong style="color:var(--accent-purple);"><?= e($thread['op_username']) ?></strong>
      &middot; <?= e($thread['created_at']) ?>
      &middot; <?= number_format($thread['views']) ?> views
    </span>
  </div>

  <?php /* Mod controls at thread level */ ?>
  <?php if (hasRole('moderator')): ?>
    <div style="display:flex;gap:.3rem;">
      <form method="POST" action="/forum/moderate.php" style="display:inline;">
        <input type="hidden" name="csrf_token" value="<?= e(generateCSRF()) ?>">
        <input type="hidden" name="thread_id" value="<?= $threadId ?>">
        <input type="hidden" name="return_url"
               value="<?= e("/forum/board.php?id={$thread['board_id']}") ?>">
        <input type="hidden" name="action" value="sticky">
        <button class="btn btn-sm btn-secondary">📌 Sticky</button>
      </form>
      <form method="POST" action="/forum/moderate.php" style="display:inline;">
        <input type="hidden" name="csrf_token" value="<?= e(generateCSRF()) ?>">
        <input type="hidden" name="thread_id" value="<?= $threadId ?>">
        <input type="hidden" name="return_url"
               value="<?= e("/forum/thread.php?id=$threadId&page=$page") ?>">
        <input type="hidden" name="action" value="lock">
        <button class="btn btn-sm btn-secondary">
          <?= $thread['is_locked'] ? '🔓 Unlock' : '🔒 Lock' ?>
        </button>
      </form>
      <form method="POST" action="/forum/moderate.php" style="display:inline;">
        <input type="hidden" name="csrf_token" value="<?= e(generateCSRF()) ?>">
        <input type="hidden" name="thread_id" value="<?= $threadId ?>">
        <input type="hidden" name="return_url"
               value="<?= e("/forum/board.php?id={$thread['board_id']}") ?>">
        <input type="hidden" name="action" value="delete_thread">
        <button class="btn btn-sm btn-danger"
                onclick="return confirm('Permanently hide this thread?');">🗑️ Delete</button>
      </form>
    </div>
  <?php endif; ?>
</div>

<?= pgHtml($page, $totalPages, "/forum/thread.php?id=$threadId") ?>

<!-- Posts -->
<?php foreach ($posts as $idx => $p): ?>
<div class="card" id="post-<?= $p['post_id'] ?>"
     style="display:flex;gap:0;padding:0;overflow:hidden;margin-bottom:1rem;">

  <!-- ===== User sidebar ===== -->
  <div style="min-width:160px;max-width:160px;background:var(--bg-secondary);
              padding:1rem;border-right:1px solid var(--border-color);
              text-align:center;flex-shrink:0;">
    <!-- Avatar placeholder -->
    <div style="width:64px;height:64px;border-radius:50%;
                background:var(--bg-primary);border:2px solid var(--border-color);
                margin:0 auto .6rem;display:flex;align-items:center;justify-content:center;
                font-size:1.5rem;color:var(--accent-green);">
      <?= strtoupper(mb_substr($p['username'], 0, 1)) ?>
    </div>
    <div style="font-weight:700;margin-bottom:.3rem;"><?= e($p['username']) ?></div>
    <span class="badge <?=
      $p['user_role'] === 'admin' ? 'badge-red' :
      ($p['user_role'] === 'moderator' ? 'badge-amber' : 'badge-blue')
    ?>" style="margin-bottom:.5rem;">
      <?= e(ucfirst($p['user_role'])) ?>
    </span>
    <div style="font-size:.72rem;color:var(--text-secondary);margin-top:.4rem;">
      Posts: <?= number_format($p['user_posts']) ?>
    </div>
    <div style="font-size:.72rem;color:var(--text-secondary);">
      Joined: <?= e(date('M Y', strtotime($p['user_joined']))) ?>
    </div>
  </div>

  <!-- ===== Post body ===== -->
  <div style="flex:1;display:flex;flex-direction:column;">
    <!-- Post meta bar -->
    <div style="padding:.6rem 1rem;border-bottom:1px solid var(--border-color);
                font-size:.78rem;color:var(--text-secondary);
                display:flex;justify-content:space-between;align-items:center;">
      <span>
        <?= e($p['created_at']) ?>
        <?php if ($p['updated_at']): ?>
          &middot; <em>edited <?= e($p['updated_at']) ?></em>
        <?php endif; ?>
      </span>
      <span>
        <a href="#post-<?= $p['post_id'] ?>" style="color:var(--text-secondary);">#<?= $p['post_id'] ?></a>

        <?php /* Per-post mod delete button */ ?>
        <?php if (hasRole('moderator')): ?>
          <form method="POST" action="/forum/moderate.php"
                style="display:inline;margin-left:.5rem;">
            <input type="hidden" name="csrf_token" value="<?= e(generateCSRF()) ?>">
            <input type="hidden" name="post_id" value="<?= $p['post_id'] ?>">
            <input type="hidden" name="action" value="delete_post">
            <input type="hidden" name="return_url"
                   value="<?= e("/forum/thread.php?id=$threadId&page=$page") ?>">
            <button type="submit" class="btn btn-sm btn-danger"
                    style="padding:2px 8px;font-size:.7rem;"
                    onclick="return confirm('Soft-delete this post?');">🗑️</button>
          </form>
        <?php endif; ?>
      </span>
    </div>

    <!-- Post content — nl2br(e()) prevents XSS while preserving line breaks -->
    <div style="padding:1rem;flex:1;line-height:1.7;">
      <?= nl2br(e($p['body'])) ?>
    </div>

    <!-- Signature -->
    <?php if (!empty($p['signature'])): ?>
      <div style="padding:.6rem 1rem;border-top:1px solid var(--border-color);
                  font-size:.78rem;color:var(--text-secondary);font-style:italic;">
        <?= nl2br(e($p['signature'])) ?>
      </div>
    <?php endif; ?>
  </div>
</div>
<?php endforeach; ?>

<?= pgHtml($page, $totalPages, "/forum/thread.php?id=$threadId") ?>

<!-- ============ REPLY FORM ============ -->
<?php if ($thread['is_locked']): ?>
  <div class="alert alert-warn">🔒 This thread is locked. Replies are disabled.</div>
<?php else: ?>
  <div class="card" id="reply">
    <h3>💬 Post a Reply</h3>

    <?php if ($replyErr): ?>
      <div class="alert alert-error"><?= e($replyErr) ?></div>
    <?php endif; ?>

    <form method="POST" action="/forum/thread.php?id=<?= $threadId ?>&page=<?= $totalPages ?>#reply">
      <input type="hidden" name="csrf_token" value="<?= e(generateCSRF()) ?>">

      <div class="form-group">
        <label for="body">Your Reply</label>
        <textarea id="body" name="body" rows="6" required
                  minlength="2" maxlength="50000"
                  placeholder="Write your reply…"><?= e($_POST['body'] ?? '') ?></textarea>
      </div>
      <button type="submit" class="btn btn-primary">Post Reply</button>
    </form>
  </div>
<?php endif; ?>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>