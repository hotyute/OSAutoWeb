<?php
$pageTitle = 'Board';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/forum_helpers.php';
requireLogin();

$boardId = (int)($_GET['id'] ?? 0);
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

$pageTitle = e($board['name']);

$perPage      = 20;
$page         = max(1, (int)($_GET['page'] ?? 1));
$totalThreads = (int)$board['thread_count'];
$totalPages   = max(1, (int)ceil($totalThreads / $perPage));
$page         = min($page, $totalPages);
$offset       = ($page - 1) * $perPage;

/* Fetch threads with OP preview snippet + last poster */
$thStmt = $pdo->prepare(
    'SELECT t.*, u.username AS author,
            op.body AS op_body,
            lp_u.username AS last_poster,
            lp.created_at AS lp_date
     FROM `forum_threads` t
     JOIN `users` u ON u.user_id = t.user_id
     LEFT JOIN `forum_posts` op ON op.post_id = (
         SELECT p1.post_id FROM `forum_posts` p1
         WHERE p1.thread_id = t.thread_id AND p1.is_deleted = 0
         ORDER BY p1.created_at ASC LIMIT 1
     )
     LEFT JOIN `forum_posts` lp ON lp.post_id = (
         SELECT p2.post_id FROM `forum_posts` p2
         WHERE p2.thread_id = t.thread_id AND p2.is_deleted = 0
         ORDER BY p2.created_at DESC LIMIT 1
     )
     LEFT JOIN `users` lp_u ON lp_u.user_id = lp.user_id
     WHERE t.board_id = ? AND t.is_deleted = 0
     ORDER BY t.is_sticky DESC, t.last_post_at DESC
     LIMIT ? OFFSET ?'
);
$thStmt->execute([$boardId, $perPage, $offset]);
$threads = $thStmt->fetchAll();

require_once __DIR__ . '/../includes/header.php';
forumCSS();
?>

<div class="breadcrumb">
  <a href="/forum/index.php">Forum</a>
  <span class="sep">›</span>
  <span style="color:var(--text-primary);"><?= e($board['name']) ?></span>
</div>

<div class="flex-between flex-between-mobile mb-1">
  <h1 style="font-size:1.4rem;"><?= e($board['name']) ?></h1>
  <a href="/forum/new_thread.php?board=<?= $boardId ?>" class="btn btn-primary btn-sm">+ New Thread</a>
</div>
<?php if ($board['description']): ?>
  <p style="color:var(--text-secondary);margin-bottom:1rem;font-size:.9rem;"><?= e($board['description']) ?></p>
<?php endif; ?>

<?= pgHtml($page, $totalPages, "/forum/board.php?id=$boardId") ?>

<!-- Desktop Table -->
<div class="card desktop-only" style="padding:0;overflow:hidden;">
  <div class="table-wrap">
    <table style="margin:0;">
      <thead>
        <tr>
          <th style="width:48%;">Thread</th>
          <th style="text-align:center;">Replies</th>
          <th style="text-align:center;">Views</th>
          <th>Last Activity</th>
          <?php if (hasRole('moderator')): ?><th>Mod</th><?php endif; ?>
        </tr>
      </thead>
      <tbody>
        <?php if (empty($threads)): ?>
          <tr><td colspan="<?= hasRole('moderator')?5:4 ?>" style="text-align:center;color:var(--text-secondary);padding:2rem;">
            No threads. <a href="/forum/new_thread.php?board=<?= $boardId ?>">Start one</a>!
          </td></tr>
        <?php else: foreach ($threads as $t):
          $lastPg = threadLastPage($t['reply_count']);
        ?>
          <tr>
            <td>
              <?php if ($t['is_sticky']): ?><span class="badge badge-amber" style="margin-right:3px;">📌</span><?php endif; ?>
              <?php if ($t['is_locked']): ?><span class="badge badge-red" style="margin-right:3px;">🔒</span><?php endif; ?>
              <a href="/forum/thread.php?id=<?= $t['thread_id'] ?>" style="font-weight:600;">
                <?= e($t['title']) ?>
              </a>
              <?php if ($lastPg > 1): ?>
                <span style="font-size:.72rem;margin-left:.3rem;">
                  <?php for ($pg = 1; $pg <= min($lastPg, 4); $pg++): ?>
                    <a href="/forum/thread.php?id=<?= $t['thread_id'] ?>&page=<?= $pg ?>"
                       class="btn btn-sm btn-secondary" style="padding:1px 5px;font-size:.65rem;">
                      <?= $pg ?>
                    </a>
                  <?php endfor; ?>
                  <?php if ($lastPg > 4): ?>
                    <a href="/forum/thread.php?id=<?= $t['thread_id'] ?>&page=<?= $lastPg ?>"
                       class="btn btn-sm btn-secondary" style="padding:1px 5px;font-size:.65rem;">
                      <?= $lastPg ?> »
                    </a>
                  <?php endif; ?>
                </span>
              <?php endif; ?>
              <div class="thread-preview">
                <?= e(mb_strimwidth(strip_tags($t['op_body'] ?? ''), 0, 100, '…')) ?>
              </div>
              <div style="font-size:.76rem;color:var(--text-secondary);margin-top:.1rem;">
                by <a href="/forum/profile.php?id=<?= $t['user_id'] ?>" style="color:var(--accent-purple);"><?= e($t['author']) ?></a>
                &middot; <?= timeAgo($t['created_at']) ?>
              </div>
            </td>
            <td style="text-align:center;font-family:var(--font-mono);"><?= number_format($t['reply_count']) ?></td>
            <td style="text-align:center;font-family:var(--font-mono);"><?= number_format($t['views']) ?></td>
            <td style="font-size:.82rem;">
              <?php if ($t['last_poster']): ?>
                <a href="/forum/profile.php?id=<?= $t['user_id'] ?>" style="color:var(--accent-purple);"><?= e($t['last_poster']) ?></a><br>
              <?php endif; ?>
              <span style="color:var(--text-secondary);"><?= timeAgo($t['lp_date'] ?? $t['last_post_at']) ?></span>
            </td>
            <?php if (hasRole('moderator')): ?>
              <td>
                <div class="mod-actions">
                  <form method="POST" action="/forum/moderate.php">
                    <input type="hidden" name="csrf_token" value="<?= e(generateCSRF()) ?>">
                    <input type="hidden" name="thread_id" value="<?= $t['thread_id'] ?>">
                    <input type="hidden" name="return_url" value="<?= e("/forum/board.php?id=$boardId&page=$page") ?>">
                    <input type="hidden" name="action" value="sticky">
                    <button type="submit" class="btn btn-sm btn-secondary" title="Sticky">📌</button>
                  </form>
                  <form method="POST" action="/forum/moderate.php">
                    <input type="hidden" name="csrf_token" value="<?= e(generateCSRF()) ?>">
                    <input type="hidden" name="thread_id" value="<?= $t['thread_id'] ?>">
                    <input type="hidden" name="return_url" value="<?= e("/forum/board.php?id=$boardId&page=$page") ?>">
                    <input type="hidden" name="action" value="lock">
                    <button type="submit" class="btn btn-sm btn-secondary" title="Lock">🔒</button>
                  </form>
                  <form method="POST" action="/forum/moderate.php">
                    <input type="hidden" name="csrf_token" value="<?= e(generateCSRF()) ?>">
                    <input type="hidden" name="thread_id" value="<?= $t['thread_id'] ?>">
                    <input type="hidden" name="return_url" value="<?= e("/forum/board.php?id=$boardId&page=$page") ?>">
                    <input type="hidden" name="action" value="delete_thread">
                    <button type="submit" class="btn btn-sm btn-danger" title="Delete" onclick="return confirm('Delete?');">🗑️</button>
                  </form>
                </div>
              </td>
            <?php endif; ?>
          </tr>
        <?php endforeach; endif; ?>
      </tbody>
    </table>
  </div>
</div>

<!-- Mobile Cards -->
<div class="mobile-only">
  <?php if (empty($threads)): ?>
    <div class="card text-center" style="color:var(--text-secondary);padding:2rem;">
      No threads. <a href="/forum/new_thread.php?board=<?= $boardId ?>">Start one</a>!
    </div>
  <?php else: foreach ($threads as $t): ?>
    <div class="card" style="padding:.75rem 1rem;">
      <div style="display:flex;justify-content:space-between;align-items:flex-start;gap:.4rem;">
        <div style="min-width:0;flex:1;">
          <?php if ($t['is_sticky']): ?><span class="badge badge-amber">📌</span><?php endif; ?>
          <?php if ($t['is_locked']): ?><span class="badge badge-red">🔒</span><?php endif; ?>
          <a href="/forum/thread.php?id=<?= $t['thread_id'] ?>" style="font-weight:600;font-size:.92rem;">
            <?= e($t['title']) ?>
          </a>
          <div class="thread-preview"><?= e(mb_strimwidth(strip_tags($t['op_body'] ?? ''), 0, 80, '…')) ?></div>
        </div>
        <div style="font-size:.72rem;color:var(--text-secondary);text-align:right;flex-shrink:0;">
          <?= $t['reply_count'] ?>💬 <?= $t['views'] ?>👁️
        </div>
      </div>
      <div style="font-size:.75rem;color:var(--text-secondary);margin-top:.3rem;display:flex;justify-content:space-between;flex-wrap:wrap;gap:.3rem;">
        <span>by <strong style="color:var(--accent-purple);"><?= e($t['author']) ?></strong></span>
        <span><?= timeAgo($t['last_post_at']) ?></span>
      </div>
      <?php if (hasRole('moderator')): ?>
        <div class="mod-actions" style="margin-top:.4rem;">
          <form method="POST" action="/forum/moderate.php">
            <input type="hidden" name="csrf_token" value="<?= e(generateCSRF()) ?>">
            <input type="hidden" name="thread_id" value="<?= $t['thread_id'] ?>">
            <input type="hidden" name="return_url" value="<?= e("/forum/board.php?id=$boardId&page=$page") ?>">
            <input type="hidden" name="action" value="sticky"><button class="btn btn-sm btn-secondary">📌</button>
          </form>
          <form method="POST" action="/forum/moderate.php">
            <input type="hidden" name="csrf_token" value="<?= e(generateCSRF()) ?>">
            <input type="hidden" name="thread_id" value="<?= $t['thread_id'] ?>">
            <input type="hidden" name="return_url" value="<?= e("/forum/board.php?id=$boardId&page=$page") ?>">
            <input type="hidden" name="action" value="lock"><button class="btn btn-sm btn-secondary">🔒</button>
          </form>
          <form method="POST" action="/forum/moderate.php">
            <input type="hidden" name="csrf_token" value="<?= e(generateCSRF()) ?>">
            <input type="hidden" name="thread_id" value="<?= $t['thread_id'] ?>">
            <input type="hidden" name="return_url" value="<?= e("/forum/board.php?id=$boardId&page=$page") ?>">
            <input type="hidden" name="action" value="delete_thread"><button class="btn btn-sm btn-danger" onclick="return confirm('Delete?');">🗑️</button>
          </form>
        </div>
      <?php endif; ?>
    </div>
  <?php endforeach; endif; ?>
</div>

<?= pgHtml($page, $totalPages, "/forum/board.php?id=$boardId") ?>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>