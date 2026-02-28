<?php
/**
 * Thread View — Responsive Posts, Pagination, Reply
 * --------------------------------------------------------
 * Post cards use .post-card / .post-sidebar / .post-body-wrap
 * CSS classes defined in header.php. On mobile the sidebar
 * collapses into a compact horizontal bar above the post body.
 */
$pageTitle = 'Thread';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
requireLogin();

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

$pdo->prepare('UPDATE `forum_threads` SET `views`=`views`+1 WHERE `thread_id`=?')
    ->execute([$threadId]);

$perPage    = 15;
$totalPosts = (int)$thread['reply_count'] + 1;
$totalPages = max(1, (int)ceil($totalPosts / $perPage));
$page       = max(1, min((int)($_GET['page'] ?? 1), $totalPages));
$offset     = ($page - 1) * $perPage;

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

$replyErr = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($thread['is_locked']) {
        $replyErr = 'This thread is locked.';
    } elseif (!validateCSRF($_POST['csrf_token'] ?? '')) {
        $replyErr = 'Invalid session token.';
    } else {
        $body = trim($_POST['body'] ?? '');
        if (strlen($body) < 2) {
            $replyErr = 'Reply must be at least 2 characters.';
        } elseif (strlen($body) > 50000) {
            $replyErr = 'Reply is too long (max 50 000 characters).';
        } else {
            $ins = $pdo->prepare(
                'INSERT INTO `forum_posts` (`thread_id`,`user_id`,`body`) VALUES (?,?,?)'
            );
            $ins->execute([$threadId, $_SESSION['user_id'], $body]);
            $newPostId = (int)$pdo->lastInsertId();

            $pdo->prepare(
                'UPDATE `forum_threads`
                 SET `reply_count`=`reply_count`+1, `last_post_at`=NOW()
                 WHERE `thread_id`=?'
            )->execute([$threadId]);

            $pdo->prepare(
                'UPDATE `forum_boards`
                 SET `post_count`=`post_count`+1, `last_post_id`=?
                 WHERE `board_id`=?'
            )->execute([$newPostId, $thread['board_id']]);

            $pdo->prepare(
                'UPDATE `users` SET `post_count`=`post_count`+1 WHERE `user_id`=?'
            )->execute([$_SESSION['user_id']]);

            $newTotal = $totalPosts + 1;
            $lastPage = max(1, (int)ceil($newTotal / $perPage));
            redirect("/forum/thread.php?id=$threadId&page=$lastPage#post-$newPostId");
        }
    }
}

if (!function_exists('pgHtml')) {
    function pgHtml(int $cur, int $tot, string $base): string {
        if ($tot <= 1) return '';
        $s = str_contains($base, '?') ? '&' : '?';
        $h = '<div class="pagination">';
        if ($cur > 1)
            $h .= '<a href="'.e($base.$s.'page='.($cur-1)).'" class="btn btn-sm btn-secondary">&laquo;</a>';
        $st = max(1, $cur-3); $en = min($tot, $cur+3);
        if ($st > 1) {
            $h .= '<a href="'.e($base.$s.'page=1').'" class="btn btn-sm btn-secondary">1</a>';
            if ($st > 2) $h .= '<span style="padding:0 .3rem;color:var(--text-secondary);">…</span>';
        }
        for ($i = $st; $i <= $en; $i++) {
            $c = $i === $cur ? 'btn-primary' : 'btn-secondary';
            $h .= '<a href="'.e($base.$s.'page='.$i).'" class="btn btn-sm '.$c.'">'.$i.'</a>';
        }
        if ($en < $tot) {
            if ($en < $tot-1) $h .= '<span style="padding:0 .3rem;color:var(--text-secondary);">…</span>';
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
<div class="breadcrumb">
  <a href="/forum/index.php">Forum</a>
  <span class="sep">›</span>
  <a href="/forum/board.php?id=<?= $thread['board_id'] ?>"><?= e($thread['board_name']) ?></a>
  <span class="sep">›</span>
  <span style="color:var(--text-primary);"><?= e(mb_strimwidth($thread['title'],0,40,'…')) ?></span>
</div>

<!-- Thread header -->
<div class="flex-between flex-between-mobile mb-1">
  <div>
    <h1 style="font-size:1.3rem;margin-bottom:.25rem;line-height:1.4;">
      <?php if ($thread['is_sticky']): ?>
        <span class="badge badge-amber">📌</span>
      <?php endif; ?>
      <?php if ($thread['is_locked']): ?>
        <span class="badge badge-red">🔒</span>
      <?php endif; ?>
      <?= e($thread['title']) ?>
    </h1>
    <span style="font-size:.8rem;color:var(--text-secondary);">
      by <strong style="color:var(--accent-purple);"><?= e($thread['op_username']) ?></strong>
      &middot; <?= e($thread['created_at']) ?>
      &middot; <?= number_format($thread['views']) ?> views
    </span>
  </div>

  <?php if (hasRole('moderator')): ?>
    <div class="mod-actions">
      <form method="POST" action="/forum/moderate.php">
        <input type="hidden" name="csrf_token" value="<?= e(generateCSRF()) ?>">
        <input type="hidden" name="thread_id" value="<?= $threadId ?>">
        <input type="hidden" name="return_url"
               value="<?= e("/forum/board.php?id={$thread['board_id']}") ?>">
        <input type="hidden" name="action" value="sticky">
        <button class="btn btn-sm btn-secondary">📌 Sticky</button>
      </form>
      <form method="POST" action="/forum/moderate.php">
        <input type="hidden" name="csrf_token" value="<?= e(generateCSRF()) ?>">
        <input type="hidden" name="thread_id" value="<?= $threadId ?>">
        <input type="hidden" name="return_url"
               value="<?= e("/forum/thread.php?id=$threadId&page=$page") ?>">
        <input type="hidden" name="action" value="lock">
        <button class="btn btn-sm btn-secondary">
          <?= $thread['is_locked'] ? '🔓 Unlock' : '🔒 Lock' ?>
        </button>
      </form>
      <form method="POST" action="/forum/moderate.php">
        <input type="hidden" name="csrf_token" value="<?= e(generateCSRF()) ?>">
        <input type="hidden" name="thread_id" value="<?= $threadId ?>">
        <input type="hidden" name="return_url"
               value="<?= e("/forum/board.php?id={$thread['board_id']}") ?>">
        <input type="hidden" name="action" value="delete_thread">
        <button class="btn btn-sm btn-danger"
                onclick="return confirm('Delete this thread?');">🗑️</button>
      </form>
    </div>
  <?php endif; ?>
</div>

<?= pgHtml($page, $totalPages, "/forum/thread.php?id=$threadId") ?>

<!-- ============ POSTS ============ -->
<?php foreach ($posts as $p): ?>
<div class="post-card" id="post-<?= $p['post_id'] ?>">

  <!-- User sidebar (becomes horizontal bar on mobile) -->
  <div class="post-sidebar">
    <div class="avatar">
      <?= strtoupper(mb_substr($p['username'], 0, 1)) ?>
    </div>
    <div>
      <div style="font-weight:700;margin-bottom:.15rem;"><?= e($p['username']) ?></div>
      <span class="badge <?=
        $p['user_role']==='admin' ? 'badge-red' :
        ($p['user_role']==='moderator' ? 'badge-amber' : 'badge-blue')
      ?>"><?= e(ucfirst($p['user_role'])) ?></span>
      <!-- Stats hidden on mobile via .sidebar-stats -->
      <div class="sidebar-stats" style="margin-top:.45rem;">
        <div style="font-size:.72rem;color:var(--text-secondary);">
          Posts: <?= number_format($p['user_posts']) ?>
        </div>
        <div style="font-size:.72rem;color:var(--text-secondary);">
          Joined: <?= e(date('M Y', strtotime($p['user_joined']))) ?>
        </div>
      </div>
    </div>
  </div>

  <!-- Post body -->
  <div class="post-body-wrap">
    <div class="post-meta">
      <span>
        <?= e($p['created_at']) ?>
        <?php if ($p['updated_at']): ?>
          &middot; <em>edited <?= e($p['updated_at']) ?></em>
        <?php endif; ?>
      </span>
      <span style="display:flex;align-items:center;gap:.4rem;">
        <a href="#post-<?= $p['post_id'] ?>" style="color:var(--text-secondary);">
          #<?= $p['post_id'] ?>
        </a>
        <?php if (hasRole('moderator')): ?>
          <form method="POST" action="/forum/moderate.php" style="display:inline;">
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

    <div class="post-content">
      <?= nl2br(e($p['body'])) ?>
    </div>

    <?php if (!empty($p['signature'])): ?>
      <div class="post-signature">
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
        <textarea id="body" name="body" rows="5" required
                  minlength="2" maxlength="50000"
                  placeholder="Write your reply…"><?= e($_POST['body'] ?? '') ?></textarea>
      </div>
      <button type="submit" class="btn btn-primary">Post Reply</button>
    </form>
  </div>
<?php endif; ?>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>