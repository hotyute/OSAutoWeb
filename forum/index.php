<?php
/**
 * Forum Home — Category & Board Listing
 * --------------------------------------------------------
 * Access: Authenticated users.
 *
 * Performance: Board stats (thread_count, post_count,
 *   last_post_id) are read directly from cached columns
 *   on the `forum_boards` table — zero aggregation queries.
 *   Last-post metadata is fetched via a single JOIN on the
 *   denormalized last_post_id foreign key.
 */
$pageTitle = 'Forum';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
requireLogin();

/* Fetch every category */
$categories = $pdo->query(
    'SELECT * FROM `forum_categories` ORDER BY `sort_order` ASC'
)->fetchAll();

/*
 * Fetch every board with its last-post info in ONE query.
 * Joins:
 *   forum_posts  (lp)  → the cached last_post_id on the board row
 *   users        (lu)  → author of that last post
 *   forum_threads (lt) → thread the last post belongs to
 * No COUNT / MAX — purely reading cached columns + JOIN.
 */
$boardRows = $pdo->query(
    'SELECT b.*,
            lp.created_at  AS lp_date,
            lu.username     AS lp_username,
            lt.title        AS lp_thread_title,
            lt.thread_id    AS lp_thread_id
     FROM `forum_boards` b
     LEFT JOIN `forum_posts`   lp ON lp.post_id   = b.last_post_id
     LEFT JOIN `users`         lu ON lu.user_id    = lp.user_id
     LEFT JOIN `forum_threads` lt ON lt.thread_id  = lp.thread_id
     ORDER BY b.category_id ASC, b.sort_order ASC'
)->fetchAll();

/* Group boards by category_id for easy rendering */
$boardsByCat = [];
foreach ($boardRows as $b) {
    $boardsByCat[$b['category_id']][] = $b;
}

require_once __DIR__ . '/../includes/header.php';
?>

<div class="flex-between mb-1">
  <h1>💬 Community Forum</h1>
</div>

<?php foreach ($categories as $cat): ?>
<div class="card" style="padding:0;overflow:hidden;margin-bottom:1.5rem;">

  <!-- Category header -->
  <div style="background:var(--bg-secondary);padding:.85rem 1.25rem;border-bottom:1px solid var(--border-color);">
    <h3 style="margin:0;font-size:1rem;color:var(--accent-green);">
      <?= e($cat['name']) ?>
    </h3>
    <?php if ($cat['description']): ?>
      <span style="font-size:.8rem;color:var(--text-secondary);"><?= e($cat['description']) ?></span>
    <?php endif; ?>
  </div>

  <!-- Board rows -->
  <table style="margin:0;">
    <thead>
      <tr>
        <th style="width:45%;">Board</th>
        <th style="width:10%;text-align:center;">Threads</th>
        <th style="width:10%;text-align:center;">Posts</th>
        <th style="width:35%;">Last Post</th>
      </tr>
    </thead>
    <tbody>
      <?php
        $boards = $boardsByCat[$cat['category_id']] ?? [];
        if (empty($boards)):
      ?>
        <tr><td colspan="4" style="color:var(--text-secondary);text-align:center;">No boards yet.</td></tr>
      <?php else: foreach ($boards as $b): ?>
        <tr>
          <td>
            <a href="/forum/board.php?id=<?= $b['board_id'] ?>"
               style="font-weight:600;font-size:.95rem;">
              📁 <?= e($b['name']) ?>
            </a>
            <?php if ($b['description']): ?>
              <div style="font-size:.8rem;color:var(--text-secondary);margin-top:2px;">
                <?= e($b['description']) ?>
              </div>
            <?php endif; ?>
          </td>
          <td style="text-align:center;font-family:var(--font-mono);">
            <?= number_format($b['thread_count']) ?>
          </td>
          <td style="text-align:center;font-family:var(--font-mono);">
            <?= number_format($b['post_count']) ?>
          </td>
          <td style="font-size:.82rem;">
            <?php if ($b['lp_date']): ?>
              <a href="/forum/thread.php?id=<?= $b['lp_thread_id'] ?>"
                 style="color:var(--text-primary);">
                <?= e(mb_strimwidth($b['lp_thread_title'] ?? '', 0, 38, '…')) ?>
              </a>
              <div style="color:var(--text-secondary);margin-top:2px;">
                by <strong style="color:var(--accent-purple);"><?= e($b['lp_username']) ?></strong>
                &middot; <?= e($b['lp_date']) ?>
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
<?php endforeach; ?>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>