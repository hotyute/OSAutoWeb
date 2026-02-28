<?php
$pageTitle = 'Profile';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/forum_helpers.php';
requireLogin();

$userId = (int)($_GET['id'] ?? 0);
if ($userId < 1) redirect('/forum/index.php');

$uStmt = $pdo->prepare('SELECT * FROM `users` WHERE `user_id` = ?');
$uStmt->execute([$userId]);
$profile = $uStmt->fetch();
if (!$profile) redirect('/forum/index.php');

$pageTitle = e($profile['username']) . ' — Profile';

/* Recent threads by this user */
$threads = $pdo->prepare(
    'SELECT t.thread_id, t.title, t.reply_count, t.views, t.last_post_at, t.created_at,
            b.name AS board_name, b.board_id
     FROM `forum_threads` t
     JOIN `forum_boards` b ON b.board_id = t.board_id
     WHERE t.user_id = ? AND t.is_deleted = 0
     ORDER BY t.created_at DESC
     LIMIT 10'
);
$threads->execute([$userId]);
$userThreads = $threads->fetchAll();

/* Recent posts by this user */
$page     = max(1, (int)($_GET['page'] ?? 1));
$perPage  = 10;
$offset   = ($page - 1) * $perPage;
$totalPosts = (int)$profile['post_count'];
$totalPages = max(1, (int)ceil($totalPosts / $perPage));
$page       = min($page, $totalPages);
$offset     = ($page - 1) * $perPage;

$postsStmt = $pdo->prepare(
    'SELECT p.post_id, p.body, p.created_at,
            t.thread_id, t.title AS thread_title,
            b.name AS board_name, b.board_id
     FROM `forum_posts` p
     JOIN `forum_threads` t ON t.thread_id = p.thread_id
     JOIN `forum_boards` b ON b.board_id = t.board_id
     WHERE p.user_id = ? AND p.is_deleted = 0 AND t.is_deleted = 0
     ORDER BY p.created_at DESC
     LIMIT ? OFFSET ?'
);
$postsStmt->execute([$userId, $perPage, $offset]);
$userPosts = $postsStmt->fetchAll();

require_once __DIR__ . '/../includes/header.php';
forumCSS();
?>

<div class="breadcrumb">
  <a href="/forum/index.php">Forum</a><span class="sep">›</span>
  <span style="color:var(--text-primary);">Profile</span>
</div>

<!-- Profile Header -->
<div class="card">
  <div class="profile-header">
    <div class="profile-avatar-lg">
      <?= strtoupper(mb_substr($profile['username'], 0, 1)) ?>
    </div>
    <div>
      <h1 style="font-size:1.5rem;margin-bottom:.25rem;"><?= e($profile['username']) ?></h1>
      <span class="badge <?=
        $profile['role']==='admin'?'badge-red':($profile['role']==='moderator'?'badge-amber':'badge-blue')
      ?>" style="margin-bottom:.5rem;">
        <?= e(ucfirst($profile['role'])) ?>
      </span>
      <div style="display:flex;gap:1.5rem;flex-wrap:wrap;margin-top:.5rem;font-size:.88rem;color:var(--text-secondary);">
        <div>📝 <strong style="color:var(--text-primary);"><?= number_format($profile['post_count']) ?></strong> posts</div>
        <div>📅 Joined <strong style="color:var(--text-primary);"><?= e(date('M j, Y', strtotime($profile['created_at']))) ?></strong></div>
      </div>
      <?php if ($profile['signature']): ?>
        <div style="margin-top:.6rem;font-style:italic;font-size:.85rem;color:var(--text-secondary);
                    border-left:2px solid var(--border-color);padding-left:.6rem;">
          <?= nl2br(e($profile['signature'])) ?>
        </div>
      <?php endif; ?>
    </div>
  </div>
</div>

<!-- Recent Threads -->
<div class="card">
  <h3>📋 Recent Threads</h3>
  <?php if ($userThreads): ?>
    <?php foreach ($userThreads as $ut): ?>
      <div style="padding:.5rem 0;border-bottom:1px solid var(--border-color);display:flex;justify-content:space-between;align-items:center;gap:.5rem;flex-wrap:wrap;">
        <div style="min-width:0;flex:1;">
          <a href="/forum/thread.php?id=<?= $ut['thread_id'] ?>" style="font-weight:600;"><?= e($ut['title']) ?></a>
          <div style="font-size:.75rem;color:var(--text-secondary);">
            in <a href="/forum/board.php?id=<?= $ut['board_id'] ?>"><?= e($ut['board_name']) ?></a>
            &middot; <?= timeAgo($ut['created_at']) ?>
          </div>
        </div>
        <div style="font-size:.75rem;color:var(--text-secondary);flex-shrink:0;">
          <?= $ut['reply_count'] ?> replies &middot; <?= $ut['views'] ?> views
        </div>
      </div>
    <?php endforeach; ?>
  <?php else: ?>
    <p style="color:var(--text-secondary);">No threads yet.</p>
  <?php endif; ?>
</div>

<!-- Recent Posts -->
<div class="card">
  <h3>💬 Recent Posts</h3>
  <?php if ($userPosts): ?>
    <?php foreach ($userPosts as $up): ?>
      <div style="padding:.6rem 0;border-bottom:1px solid var(--border-color);">
        <div style="font-size:.82rem;color:var(--text-secondary);margin-bottom:.3rem;">
          In <a href="/forum/thread.php?id=<?= $up['thread_id'] ?>"><?= e($up['thread_title']) ?></a>
          (<a href="/forum/board.php?id=<?= $up['board_id'] ?>"><?= e($up['board_name']) ?></a>)
          &middot; <?= timeAgo($up['created_at']) ?>
        </div>
        <div style="font-size:.88rem;color:var(--text-primary);background:var(--bg-secondary);
                    padding:.5rem .75rem;border-radius:var(--radius);border-left:3px solid var(--accent-green);">
          <?= e(mb_strimwidth($up['body'], 0, 200, '…')) ?>
        </div>
      </div>
    <?php endforeach; ?>
    <?= pgHtml($page, $totalPages, "/forum/profile.php?id=$userId") ?>
  <?php else: ?>
    <p style="color:var(--text-secondary);">No posts yet.</p>
  <?php endif; ?>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>