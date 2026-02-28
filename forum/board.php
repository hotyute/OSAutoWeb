<?php
/**
 * Board View — Responsive Thread Listing + Pagination
 */
$pageTitle = 'Board';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
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

$pageTitle = e($board['name']) . ' — Forum';

$perPage      = 20;
$page         = max(1, (int)($_GET['page'] ?? 1));
$totalThreads = (int)$board['thread_count'];
$totalPages   = max(1, (int)ceil($totalThreads / $perPage));
$page         = min($page, $totalPages);
$offset       = ($page - 1) * $perPage;

$thStmt = $pdo->prepare(
    'SELECT t.*, u.username,
            lp_u.username AS last_poster
     FROM `forum_threads` t
     JOIN `users` u ON u.user_id = t.user_id
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

/** Pagination HTML renderer */
function pgHtml(int $cur, int $tot, string $base): string {
    if ($tot <= 1) return '';
    $s = str_contains($base, '?') ? '&' : '?';
    $h = '<div class="pagination">';
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

require_once __DIR__ . '/../includes/header.php';
?>

<!-- Breadcrumb -->
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
  <p style="color:var(--text-secondary);margin-bottom:1rem;font-size:.9rem;">
    <?= e($board['description']) ?>
  </p>
<?php endif; ?>

<?= pgHtml($page, $totalPages, "/forum/board.php?id=$boardId") ?>

<div class="card" style="padding:0;overflow:hidden;">
  <div class="table-wrap">
    <table style="margin:0;">
      <thead>
        <tr>
          <th style="width:48%;">Thread</th>
          <th style="text-align:center;">Replies</th>
          <th style="text-align:center;">Views</th>
          <th>Last Activity</th>
          <?php if (hasRole('moderator')): ?>
            <th>Mod</th>
          <?php endif; ?>
        </tr>
      </thead>
      <tbody>
        <?php if (empty($threads)): ?>
          <tr>
            <td colspan="<?= hasRole('moderator') ? 5 : 4 ?>"
                style="text-align:center;color:var(--text-secondary);padding:2rem;">
              No threads yet.
              <a href="/forum/new_thread.php?board=<?= $boardId ?>">Start one</a>!
            </td>
          </tr>
        <?php else: foreach ($threads as $t): ?>
          <tr>
            <td>
              <?php if ($t['is_sticky']): ?>
                <span class="badge badge-amber" style="margin-right:3px;">📌</span>
              <?php endif; ?>
              <?php if ($t['is_locked']): ?>
                <span class="badge badge-red" style="margin-right:3px;">🔒</span>
              <?php endif; ?>
              <a href="/forum/thread.php?id=<?= $t['thread_id'] ?>" style="font-weight:600;">
                <?= e($t['title']) ?>
              </a>
              <div style="font-size:.76rem;color:var(--text-secondary);margin-top:2px;">
                by <strong style="color:var(--accent-purple);"><?= e($t['username']) ?></strong>
                &middot; <?= e($t['created_at']) ?>
              </div>
            </td>
            <td style="text-align:center;font-family:var(--font-mono);">
              <?= number_format($t['reply_count']) ?>
            </td>
            <td style="text-align:center;font-family:var(--font-mono);">
              <?= number_format($t['views']) ?>
            </td>
            <td style="font-size:.82rem;">
              <?php if ($t['last_poster']): ?>
                <span style="color:var(--accent-purple);"><?= e($t['last_poster']) ?></span><br>
              <?php endif; ?>
              <span style="color:var(--text-secondary);"><?= e($t['last_post_at']) ?></span>
            </td>
            <?php if (hasRole('moderator')): ?>
              <td>
                <div class="mod-actions">
                  <form method="POST" action="/forum/moderate.php">
                    <input type="hidden" name="csrf_token" value="<?= e(generateCSRF()) ?>">
                    <input type="hidden" name="thread_id" value="<?= $t['thread_id'] ?>">
                    <input type="hidden" name="return_url"
                           value="<?= e("/forum/board.php?id=$boardId&page=$page") ?>">
                    <input type="hidden" name="action" value="sticky">
                    <button type="submit" class="btn btn-sm btn-secondary" title="Toggle Sticky">📌</button>
                  </form>
                  <form method="POST" action="/forum/moderate.php">
                    <input type="hidden" name="csrf_token" value="<?= e(generateCSRF()) ?>">
                    <input type="hidden" name="thread_id" value="<?= $t['thread_id'] ?>">
                    <input type="hidden" name="return_url"
                           value="<?= e("/forum/board.php?id=$boardId&page=$page") ?>">
                    <input type="hidden" name="action" value="lock">
                    <button type="submit" class="btn btn-sm btn-secondary" title="Toggle Lock">🔒</button>
                  </form>
                  <form method="POST" action="/forum/moderate.php">
                    <input type="hidden" name="csrf_token" value="<?= e(generateCSRF()) ?>">
                    <input type="hidden" name="thread_id" value="<?= $t['thread_id'] ?>">
                    <input type="hidden" name="return_url"
                           value="<?= e("/forum/board.php?id=$boardId&page=$page") ?>">
                    <input type="hidden" name="action" value="delete_thread">
                    <button type="submit" class="btn btn-sm btn-danger" title="Delete"
                            onclick="return confirm('Delete this thread?');">🗑️</button>
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

<?= pgHtml($page, $totalPages, "/forum/board.php?id=$boardId") ?>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>