<?php
$pageTitle = 'Thread';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/forum_helpers.php';
requireLogin();

$threadId = (int)($_GET['id'] ?? 0);
if ($threadId < 1) redirect('/forum/index.php');

$pStmt = $pdo->prepare(
    'SELECT p.*, u.username, u.role AS user_role,
            u.post_count AS user_posts, u.signature,
            u.created_at AS user_joined, u.user_id AS author_id,
            u.avatar_path, u.display_role
     FROM `forum_posts` p
     JOIN `users` u ON u.user_id = p.user_id
     WHERE p.thread_id = ? AND p.is_deleted = 0
     ORDER BY p.created_at ASC
     LIMIT ? OFFSET ?'
);
$tStmt->execute([$threadId]);
$thread = $tStmt->fetch();
if (!$thread) redirect('/forum/index.php');
$pageTitle = e($thread['title']);

/* Increment views */
$pdo->prepare('UPDATE `forum_threads` SET `views`=`views`+1 WHERE `thread_id`=?')
    ->execute([$threadId]);

$perPage    = 15;
$totalPosts = (int)$thread['reply_count'] + 1;
$totalPages = max(1, (int)ceil($totalPosts / $perPage));
$page       = max(1, min((int)($_GET['page'] ?? 1), $totalPages));
$offset     = ($page - 1) * $perPage;

/* Fetch posts with post number calculation */
$pStmt = $pdo->prepare(
    'SELECT p.*, u.username, u.role AS user_role,
            u.post_count AS user_posts, u.signature,
            u.created_at AS user_joined, u.user_id AS author_id
     FROM `forum_posts` p
     JOIN `users` u ON u.user_id = p.user_id
     WHERE p.thread_id = ? AND p.is_deleted = 0
     ORDER BY p.created_at ASC
     LIMIT ? OFFSET ?'
);
$pStmt->execute([$threadId, $perPage, $offset]);
$posts = $pStmt->fetchAll();

/* Boards list for move dropdown (mods only) */
$allBoards = [];
if (hasRole('moderator')) {
    $allBoards = $pdo->query(
        'SELECT b.board_id, b.name, c.name AS cat_name
         FROM `forum_boards` b
         JOIN `forum_categories` c ON c.category_id = b.category_id
         ORDER BY c.sort_order, b.sort_order'
    )->fetchAll();
}

/* Handle reply */
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
            $replyErr = 'Reply too long.';
        } else {
            $ins = $pdo->prepare('INSERT INTO `forum_posts` (`thread_id`,`user_id`,`body`) VALUES (?,?,?)');
            $ins->execute([$threadId, $_SESSION['user_id'], $body]);
            $newPostId = (int)$pdo->lastInsertId();
            $pdo->prepare('UPDATE `forum_threads` SET `reply_count`=`reply_count`+1,`last_post_at`=NOW() WHERE `thread_id`=?')
                ->execute([$threadId]);
            $pdo->prepare('UPDATE `forum_boards` SET `post_count`=`post_count`+1,`last_post_id`=? WHERE `board_id`=?')
                ->execute([$newPostId, $thread['board_id']]);
            $pdo->prepare('UPDATE `users` SET `post_count`=`post_count`+1 WHERE `user_id`=?')
                ->execute([$_SESSION['user_id']]);
            $newTotal = $totalPosts + 1;
            $lastPage = max(1, (int)ceil($newTotal / $perPage));
            redirect("/forum/thread.php?id=$threadId&page=$lastPage#post-$newPostId");
        }
    }
}

require_once __DIR__ . '/../includes/header.php';
forumCSS();
?>

<div class="breadcrumb">
  <a href="/forum/index.php">Forum</a><span class="sep">›</span>
  <a href="/forum/board.php?id=<?= $thread['board_id'] ?>"><?= e($thread['board_name']) ?></a><span class="sep">›</span>
  <span style="color:var(--text-primary);"><?= e(mb_strimwidth($thread['title'],0,40,'…')) ?></span>
</div>

<div class="flex-between flex-between-mobile mb-1">
  <div>
    <h1 style="font-size:1.3rem;margin-bottom:.25rem;line-height:1.4;">
      <?php if ($thread['is_sticky']): ?><span class="badge badge-amber">📌</span><?php endif; ?>
      <?php if ($thread['is_locked']): ?><span class="badge badge-red">🔒</span><?php endif; ?>
      <?= e($thread['title']) ?>
    </h1>
    <span style="font-size:.8rem;color:var(--text-secondary);">
      by <a href="/forum/profile.php?id=<?= $thread['user_id'] ?>" style="color:var(--accent-purple);"><?= e($thread['op_username']) ?></a>
      &middot; <?= timeAgo($thread['created_at']) ?>
      &middot; <?= number_format($thread['views']) ?> views
      &middot; <?= number_format($thread['reply_count']) ?> replies
    </span>
  </div>

  <?php if (hasRole('moderator')): ?>
    <div style="display:flex;gap:.3rem;flex-wrap:wrap;align-items:center;">
      <form method="POST" action="/forum/moderate.php" style="display:inline;">
        <input type="hidden" name="csrf_token" value="<?= e(generateCSRF()) ?>">
        <input type="hidden" name="thread_id" value="<?= $threadId ?>">
        <input type="hidden" name="return_url" value="<?= e("/forum/thread.php?id=$threadId&page=$page") ?>">
        <input type="hidden" name="action" value="sticky">
        <button class="btn btn-sm btn-secondary">📌</button>
      </form>
      <form method="POST" action="/forum/moderate.php" style="display:inline;">
        <input type="hidden" name="csrf_token" value="<?= e(generateCSRF()) ?>">
        <input type="hidden" name="thread_id" value="<?= $threadId ?>">
        <input type="hidden" name="return_url" value="<?= e("/forum/thread.php?id=$threadId&page=$page") ?>">
        <input type="hidden" name="action" value="lock">
        <button class="btn btn-sm btn-secondary"><?= $thread['is_locked']?'🔓':'🔒' ?></button>
      </form>
      <form method="POST" action="/forum/moderate.php" style="display:inline;">
        <input type="hidden" name="csrf_token" value="<?= e(generateCSRF()) ?>">
        <input type="hidden" name="thread_id" value="<?= $threadId ?>">
        <input type="hidden" name="return_url" value="<?= e("/forum/board.php?id={$thread['board_id']}") ?>">
        <input type="hidden" name="action" value="delete_thread">
        <button class="btn btn-sm btn-danger" onclick="return confirm('Delete thread?');">🗑️</button>
      </form>
      <!-- Move thread -->
      <form method="POST" action="/forum/moderate.php" class="move-form">
        <input type="hidden" name="csrf_token" value="<?= e(generateCSRF()) ?>">
        <input type="hidden" name="thread_id" value="<?= $threadId ?>">
        <input type="hidden" name="return_url" value="<?= e("/forum/thread.php?id=$threadId&page=$page") ?>">
        <input type="hidden" name="action" value="move_thread">
        <select name="new_board_id">
          <?php foreach ($allBoards as $ab): ?>
            <option value="<?= $ab['board_id'] ?>" <?= $ab['board_id']==$thread['board_id']?'selected':'' ?>>
              <?= e($ab['cat_name']) ?> › <?= e($ab['name']) ?>
            </option>
          <?php endforeach; ?>
        </select>
        <button class="btn btn-sm btn-secondary">Move</button>
      </form>
    </div>
  <?php endif; ?>
</div>

<?= pgHtml($page, $totalPages, "/forum/thread.php?id=$threadId") ?>

<!-- Posts -->
<?php foreach ($posts as $idx => $p):
  $postNum = $offset + $idx + 1;
  $canEdit = ($p['author_id'] == $_SESSION['user_id'] || hasRole('moderator'));
?>
<div class="post-card" id="post-<?= $p['post_id'] ?>">
  <div class="post-sidebar">
    <?php
      $avUrl = getAvatarUrl($pdo, $p['avatar_path'] ?? null, $p['username']);
    ?>
    <?php if ($avUrl): ?>
      <img src="<?= e($avUrl) ?>" alt="<?= e($p['username']) ?>"
           style="width:56px;height:56px;border-radius:50%;object-fit:cover;
                  border:2px solid var(--border-color);margin:0 auto .5rem;">
    <?php else: ?>
      <div class="avatar"><?= strtoupper(mb_substr($p['username'], 0, 1)) ?></div>
    <?php endif; ?>
    <div>
      <div style="font-weight:700;margin-bottom:.15rem;">
        <?php if (settingEnabled($pdo, 'forum_user_profiles')): ?>
          <a href="/forum/profile.php?id=<?= $p['author_id'] ?>" style="color:var(--text-primary);"><?= e($p['username']) ?></a>
        <?php else: ?>
          <?= e($p['username']) ?>
        <?php endif; ?>
      </div>
      <span class="badge <?= $p['user_role']==='admin'?'badge-red':($p['user_role']==='moderator'?'badge-amber':'badge-blue') ?>">
        <?= e(ucfirst($p['user_role'])) ?>
      </span>
      <?php if ($p['display_role'] ?? null): ?>
        <div style="font-size:.72rem;color:var(--accent-purple);font-style:italic;margin-top:.2rem;">
          <?= e($p['display_role']) ?>
        </div>
      <?php endif; ?>
      <div class="sidebar-stats" style="margin-top:.45rem;">
        <div style="font-size:.72rem;color:var(--text-secondary);">Posts: <?= number_format($p['user_posts']) ?></div>
        <div style="font-size:.72rem;color:var(--text-secondary);">Joined: <?= e(date('M Y', strtotime($p['user_joined']))) ?></div>
      </div>
    </div>
  </div>

  <div class="post-body-wrap">
    <div class="post-meta">
      <span>
        #<?= $postNum ?> &middot; <?= e($p['created_at']) ?>
        <?php if ($p['updated_at']): ?>
          &middot; <em>edited <?= timeAgo($p['updated_at']) ?><?php if ($p['edited_by'] && $p['edited_by'] != $p['author_id']): ?> by mod<?php endif; ?></em>
        <?php endif; ?>
      </span>
      <span style="display:flex;align-items:center;gap:.3rem;flex-wrap:wrap;">
        <a href="#post-<?= $p['post_id'] ?>" style="color:var(--text-secondary);">#<?= $p['post_id'] ?></a>
        <!-- Quote button -->
        <button type="button" class="btn btn-sm btn-secondary" style="padding:2px 8px;font-size:.7rem;"
                onclick="quotePost(<?= $p['post_id'] ?>, '<?= e(addslashes($p['username'])) ?>');">Quote</button>
        <?php if ($canEdit): ?>
          <a href="/forum/edit_post.php?id=<?= $p['post_id'] ?>" class="btn btn-sm btn-secondary"
             style="padding:2px 8px;font-size:.7rem;">Edit</a>
        <?php endif; ?>
        <?php if (hasRole('moderator')): ?>
          <form method="POST" action="/forum/moderate.php" style="display:inline;">
            <input type="hidden" name="csrf_token" value="<?= e(generateCSRF()) ?>">
            <input type="hidden" name="post_id" value="<?= $p['post_id'] ?>">
            <input type="hidden" name="action" value="delete_post">
            <input type="hidden" name="return_url" value="<?= e("/forum/thread.php?id=$threadId&page=$page") ?>">
            <button type="submit" class="btn btn-sm btn-danger" style="padding:2px 8px;font-size:.7rem;"
                    onclick="return confirm('Delete post?');">🗑️</button>
          </form>
        <?php endif; ?>
      </span>
    </div>
    <div class="post-content"><?= formatPost($p['body']) ?></div>
    <?php if (!empty($p['signature'])): ?>
      <div class="post-signature"><?= nl2br(e($p['signature'])) ?></div>
    <?php endif; ?>
  </div>
</div>
<?php endforeach; ?>

<?= pgHtml($page, $totalPages, "/forum/thread.php?id=$threadId") ?>

<!-- Reply Form -->
<?php if ($thread['is_locked']): ?>
  <div class="alert alert-warn">🔒 This thread is locked.</div>
<?php else: ?>
  <div class="card" id="reply">
    <h3>💬 Post a Reply</h3>
    <?php if ($replyErr): ?><div class="alert alert-error"><?= e($replyErr) ?></div><?php endif; ?>
    <form method="POST" action="/forum/thread.php?id=<?= $threadId ?>&page=<?= $totalPages ?>#reply">
      <input type="hidden" name="csrf_token" value="<?= e(generateCSRF()) ?>">
      <div class="form-group">
        <label for="reply-body">Your Reply</label>
        <textarea id="reply-body" name="body" rows="6" required minlength="2" maxlength="50000"
                  placeholder="Write your reply…"><?= e($_POST['body'] ?? '') ?></textarea>
      </div>
      <button type="submit" class="btn btn-primary">Post Reply</button>
    </form>
  </div>
<?php endif; ?>

<!-- Quote JS -->
<script>
function quotePost(postId, username) {
    var el = document.querySelector('#post-' + postId + ' .post-content');
    if (!el) return;
    var text = el.innerText.trim();
    var lines = text.split('\n');
    var quoted = '> @' + username + ' wrote:\n';
    for (var i = 0; i < lines.length; i++) {
        quoted += '> ' + lines[i] + '\n';
    }
    quoted += '\n';
    var ta = document.getElementById('reply-body');
    if (ta) {
        ta.value += quoted;
        ta.focus();
        ta.scrollIntoView({behavior:'smooth', block:'center'});
    }
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>