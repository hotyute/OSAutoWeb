<?php
$pageTitle = 'Forum';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/forum_helpers.php';
requireLogin();

if (!settingEnabled($pdo, 'forum_enabled')) {
    redirect('/dashboard.php');
}

$categories = $pdo->query('SELECT * FROM `forum_categories` ORDER BY `sort_order` ASC')->fetchAll();

$boardRows = $pdo->query(
    'SELECT b.*, lp.created_at AS lp_date, lp.user_id AS lp_user_id,
            lu.username AS lp_username, lu.avatar_path AS lp_avatar,
            lt.title AS lp_thread_title, lt.thread_id AS lp_thread_id
     FROM `forum_boards` b
     LEFT JOIN `forum_posts`   lp ON lp.post_id   = b.last_post_id
     LEFT JOIN `users`         lu ON lu.user_id    = lp.user_id
     LEFT JOIN `forum_threads` lt ON lt.thread_id  = lp.thread_id
     ORDER BY b.category_id ASC, b.sort_order ASC'
)->fetchAll();
$boardsByCat = [];
foreach ($boardRows as $b) $boardsByCat[$b['category_id']][] = $b;

$totalThreads = $pdo->query("SELECT SUM(thread_count) FROM forum_boards")->fetchColumn() ?: 0;
$totalPosts   = $pdo->query("SELECT SUM(post_count) FROM forum_boards")->fetchColumn() ?: 0;
$totalMembers = $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn() ?: 0;

$showWhosOnline   = settingEnabled($pdo, 'forum_whos_online');
$showRecentThreads = settingEnabled($pdo, 'forum_recent_threads');
$showSearch       = settingEnabled($pdo, 'forum_search');
$showProfiles     = settingEnabled($pdo, 'forum_user_profiles');
$avatarsEnabled   = settingEnabled($pdo, 'forum_avatars');

$onlineUsers = $showWhosOnline ? getOnlineUsers($pdo) : [];

$recentThreads = [];
if ($showRecentThreads) {
    $recentThreads = $pdo->query(
        'SELECT t.thread_id, t.title, t.last_post_at, t.reply_count, t.views,
                t.user_id, u.username, u.avatar_path,
                b.name AS board_name, b.board_id
         FROM `forum_threads` t
         JOIN `users` u ON u.user_id = t.user_id
         JOIN `forum_boards` b ON b.board_id = t.board_id
         WHERE t.is_deleted = 0
         ORDER BY t.last_post_at DESC LIMIT 5'
    )->fetchAll();
}

require_once __DIR__ . '/../includes/header.php';
forumCSS();
?>

<style>
.online-grid{display:flex;flex-wrap:wrap;gap:.5rem;margin-top:.5rem;}
.online-chip{display:inline-flex;align-items:center;gap:.35rem;background:var(--bg-secondary);border:1px solid var(--border-color);border-radius:20px;padding:.25rem .65rem .25rem .3rem;font-size:.8rem;transition:border-color .2s;}
.online-chip:hover{border-color:var(--accent-green);}
.online-chip img{width:22px;height:22px;border-radius:50%;object-fit:cover;}
.online-chip .oc-letter{width:22px;height:22px;border-radius:50%;background:var(--bg-primary);display:flex;align-items:center;justify-content:center;font-size:.65rem;color:var(--accent-green);font-weight:700;}
.online-chip .dot{margin-right:0;}
</style>

<div class="flex-between flex-between-mobile mb-1">
  <h1>💬 Community Forum</h1>
  <div style="display:flex;gap:.4rem;flex-wrap:wrap;">
    <?php if ($showSearch): ?>
      <a href="/forum/search.php" class="btn btn-secondary btn-sm">🔍 Search</a>
    <?php endif; ?>
    <?php if (hasRole('admin')): ?>
      <a href="/admin/forums.php" class="btn btn-secondary btn-sm role-admin">🏗️ Structure</a>
    <?php endif; ?>
  </div>
</div>

<!-- Stats Bar -->
<div class="card" style="padding:.75rem 1.25rem;">
  <div class="forum-stat-bar">
    <div class="forum-stat-item">
      <div class="forum-stat-num" style="color:var(--accent-green);"><?= number_format($totalThreads) ?></div>
      <div class="forum-stat-label">Threads</div>
    </div>
    <div class="forum-stat-item">
      <div class="forum-stat-num" style="color:var(--accent-blue);"><?= number_format($totalPosts) ?></div>
      <div class="forum-stat-label">Posts</div>
    </div>
    <div class="forum-stat-item">
      <div class="forum-stat-num" style="color:var(--accent-purple);"><?= number_format($totalMembers) ?></div>
      <div class="forum-stat-label">Members</div>
    </div>
    <?php if ($showWhosOnline): ?>
    <div class="forum-stat-item">
      <div class="forum-stat-num" style="color:var(--accent-amber);"><?= count($onlineUsers) ?></div>
      <div class="forum-stat-label">Online</div>
    </div>
    <?php endif; ?>
  </div>
</div>

<!-- Categories & Boards -->
<?php foreach ($categories as $cat): ?>
<div class="card" style="padding:0;overflow:hidden;margin-bottom:1.5rem;">
  <div style="background:var(--bg-secondary);padding:.75rem 1.1rem;border-bottom:1px solid var(--border-color);">
    <h3 style="margin:0;font-size:1rem;color:var(--accent-green);"><?= e($cat['name']) ?></h3>
    <?php if ($cat['description']): ?>
      <span style="font-size:.8rem;color:var(--text-secondary);"><?= e($cat['description']) ?></span>
    <?php endif; ?>
  </div>

  <div class="desktop-only">
    <div class="table-wrap">
      <table style="margin:0;">
        <thead>
          <tr><th style="width:45%;">Board</th><th style="width:10%;text-align:center;">Threads</th><th style="width:10%;text-align:center;">Posts</th><th style="width:35%;">Last Post</th></tr>
        </thead>
        <tbody>
          <?php
            $catBoards = $boardsByCat[$cat['category_id']] ?? [];
            if (empty($catBoards)):
          ?>
            <tr><td colspan="4" style="color:var(--text-secondary);text-align:center;">No boards.</td></tr>
          <?php else: foreach ($catBoards as $b): ?>
            <tr>
              <td>
                <a href="/forum/board.php?id=<?= $b['board_id'] ?>" style="font-weight:600;">📁 <?= e($b['name']) ?></a>
                <?php if ($b['description']): ?>
                  <div style="font-size:.78rem;color:var(--text-secondary);margin-top:2px;"><?= e($b['description']) ?></div>
                <?php endif; ?>
              </td>
              <td style="text-align:center;font-family:var(--font-mono);"><?= number_format($b['thread_count']) ?></td>
              <td style="text-align:center;font-family:var(--font-mono);"><?= number_format($b['post_count']) ?></td>
              <td style="font-size:.82rem;">
                <?php if ($b['lp_date']): ?>
                  <a href="/forum/thread.php?id=<?= $b['lp_thread_id'] ?>" style="color:var(--text-primary);">
                    <?= e(mb_strimwidth($b['lp_thread_title'] ?? '', 0, 38, '…')) ?>
                  </a>
                  <div style="color:var(--text-secondary);margin-top:2px;">
                    by <?php if ($showProfiles): ?><a href="/forum/profile.php?id=<?= $b['lp_user_id'] ?>" style="color:var(--accent-purple);"><?= e($b['lp_username']) ?></a><?php else: ?><strong style="color:var(--accent-purple);"><?= e($b['lp_username']) ?></strong><?php endif; ?>
                    &middot; <?= timeAgo($b['lp_date']) ?>
                  </div>
                <?php else: ?>
                  <span style="color:var(--text-secondary);">No posts yet</span>
                <?php endif; ?>
              </td>
            </tr>
          <?php endforeach; endif; ?>
        </tbody>
      </table>
    </div>
  </div>

  <div class="mobile-only" style="padding:.5rem;">
    <?php
      $catBoards = $boardsByCat[$cat['category_id']] ?? [];
      if (empty($catBoards)):
    ?>
      <p style="color:var(--text-secondary);text-align:center;padding:.5rem;">No boards.</p>
    <?php else: foreach ($catBoards as $b): ?>
      <a href="/forum/board.php?id=<?= $b['board_id'] ?>" style="display:block;padding:.65rem .5rem;border-bottom:1px solid var(--border-color);color:var(--text-primary);">
        <div style="display:flex;justify-content:space-between;align-items:center;">
          <strong style="font-size:.92rem;">📁 <?= e($b['name']) ?></strong>
          <span style="font-size:.72rem;color:var(--text-secondary);"><?= $b['thread_count'] ?>t / <?= $b['post_count'] ?>p</span>
        </div>
        <?php if ($b['description']): ?>
          <div style="font-size:.78rem;color:var(--text-secondary);margin-top:.15rem;"><?= e($b['description']) ?></div>
        <?php endif; ?>
        <?php if ($b['lp_date']): ?>
          <div style="font-size:.72rem;color:var(--text-secondary);margin-top:.25rem;">
            Last: <?= e(mb_strimwidth($b['lp_thread_title'] ?? '', 0, 30, '…')) ?>
            by <?= e($b['lp_username']) ?> &middot; <?= timeAgo($b['lp_date']) ?>
          </div>
        <?php endif; ?>
      </a>
    <?php endforeach; endif; ?>
  </div>
</div>
<?php endforeach; ?>

<!-- Who's Online Panel -->
<?php if ($showWhosOnline && $onlineUsers): ?>
<div class="card">
  <div class="flex-between mb-1">
    <h3>🟢 Who's Online (<?= count($onlineUsers) ?>)</h3>
    <span style="font-size:.78rem;color:var(--text-secondary);">
      Last <?= e(getSetting($pdo, 'online_threshold_min', '15')) ?> minutes
    </span>
  </div>
  <div class="online-grid">
    <?php foreach ($onlineUsers as $ou):
      $avUrl = getAvatarUrl($pdo, $ou['avatar_path'], $ou['username']);
    ?>
      <<?= $showProfiles ? 'a href="/forum/profile.php?id='.$ou['user_id'].'"' : 'span' ?>
        class="online-chip" style="color:var(--text-primary);">
        <?php if ($avUrl): ?>
          <img src="<?= e($avUrl) ?>" alt="">
        <?php else: ?>
          <span class="oc-letter"><?= strtoupper(mb_substr($ou['username'], 0, 1)) ?></span>
        <?php endif; ?>
        <span class="dot dot-green"></span>
        <?= e($ou['username']) ?>
        <?php if ($ou['role'] !== 'user'): ?>
          <span class="badge <?= $ou['role']==='admin'?'badge-red':'badge-amber' ?>" style="font-size:.6rem;padding:1px 5px;">
            <?= e(ucfirst($ou['role'])) ?>
          </span>
        <?php endif; ?>
      </<?= $showProfiles ? 'a' : 'span' ?>>
    <?php endforeach; ?>
  </div>
</div>
<?php endif; ?>

<!-- Recent Threads -->
<?php if ($showRecentThreads && $recentThreads): ?>
<div class="card">
  <h3>🕐 Recent Threads</h3>
  <?php foreach ($recentThreads as $rt): ?>
    <div style="padding:.5rem 0;border-bottom:1px solid var(--border-color);display:flex;justify-content:space-between;align-items:center;gap:.5rem;flex-wrap:wrap;">
      <div style="min-width:0;flex:1;">
        <a href="/forum/thread.php?id=<?= $rt['thread_id'] ?>" style="font-weight:600;font-size:.92rem;"><?= e($rt['title']) ?></a>
        <div style="font-size:.75rem;color:var(--text-secondary);margin-top:.1rem;">
          in <a href="/forum/board.php?id=<?= $rt['board_id'] ?>"><?= e($rt['board_name']) ?></a>
          by <?php if ($showProfiles): ?><a href="/forum/profile.php?id=<?= $rt['user_id'] ?>" style="color:var(--accent-purple);"><?= e($rt['username']) ?></a><?php else: ?><strong style="color:var(--accent-purple);"><?= e($rt['username']) ?></strong><?php endif; ?>
        </div>
      </div>
      <div style="text-align:right;flex-shrink:0;">
        <div style="font-size:.78rem;color:var(--text-secondary);"><?= timeAgo($rt['last_post_at']) ?></div>
        <div style="font-size:.72rem;color:var(--text-secondary);"><?= $rt['reply_count'] ?> replies</div>
      </div>
    </div>
  <?php endforeach; ?>
</div>
<?php endif; ?>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>