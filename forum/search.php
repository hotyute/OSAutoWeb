<?php
$pageTitle = 'Search Forum';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/forum_helpers.php';
requireLogin();

$query   = trim($_GET['q'] ?? '');
$boardFilter = (int)($_GET['board'] ?? 0);
$results = [];
$total   = 0;
$page    = max(1, (int)($_GET['page'] ?? 1));
$perPage = 20;
$offset  = ($page - 1) * $perPage;
$totalPages = 1;

/* All boards for filter dropdown */
$allBoards = $pdo->query(
    'SELECT b.board_id, b.name, c.name AS cat_name
     FROM `forum_boards` b
     JOIN `forum_categories` c ON c.category_id = b.category_id
     ORDER BY c.sort_order, b.sort_order'
)->fetchAll();

if (strlen($query) >= 2) {
    /* Build WHERE clause */
    $where  = 't.is_deleted = 0 AND p.is_deleted = 0';
    $params = [];

    if ($boardFilter > 0) {
        $where .= ' AND t.board_id = ?';
        $params[] = $boardFilter;
    }

    /* Use FULLTEXT MATCH if query is 3+ chars, else LIKE fallback */
    if (strlen($query) >= 3) {
        $where .= ' AND (MATCH(t.title) AGAINST(? IN BOOLEAN MODE) OR MATCH(p.body) AGAINST(? IN BOOLEAN MODE))';
        $searchTerm = '+' . implode(' +', array_filter(explode(' ', $query)));
        $params[] = $searchTerm;
        $params[] = $searchTerm;
    } else {
        $like = '%' . $query . '%';
        $where .= ' AND (t.title LIKE ? OR p.body LIKE ?)';
        $params[] = $like;
        $params[] = $like;
    }

    /* Count */
    $countStmt = $pdo->prepare(
        "SELECT COUNT(DISTINCT p.post_id)
         FROM `forum_posts` p
         JOIN `forum_threads` t ON t.thread_id = p.thread_id
         WHERE $where"
    );
    $countStmt->execute($params);
    $total = (int)$countStmt->fetchColumn();
    $totalPages = max(1, (int)ceil($total / $perPage));
    $page = min($page, $totalPages);
    $offset = ($page - 1) * $perPage;

    /* Fetch results */
    $rStmt = $pdo->prepare(
        "SELECT p.post_id, p.body, p.created_at,
                t.thread_id, t.title AS thread_title,
                b.name AS board_name, b.board_id,
                u.username
         FROM `forum_posts` p
         JOIN `forum_threads` t ON t.thread_id = p.thread_id
         JOIN `forum_boards` b ON b.board_id = t.board_id
         JOIN `users` u ON u.user_id = p.user_id
         WHERE $where
         ORDER BY p.created_at DESC
         LIMIT $perPage OFFSET $offset"
    );
    $rStmt->execute($params);
    $results = $rStmt->fetchAll();
}

/* Highlight helper */
function highlightSnippet(string $text, string $query, int $len = 200): string
{
    $plain = e($text);
    $pos = mb_stripos($plain, e($query));
    if ($pos !== false) {
        $start = max(0, $pos - 60);
        $snippet = mb_substr($plain, $start, $len);
        if ($start > 0) $snippet = '…' . $snippet;
        if ($start + $len < mb_strlen($plain)) $snippet .= '…';
    } else {
        $snippet = mb_substr($plain, 0, $len);
        if (mb_strlen($plain) > $len) $snippet .= '…';
    }
    return preg_replace('/' . preg_quote(e($query), '/') . '/i', '<mark>$0</mark>', $snippet);
}

require_once __DIR__ . '/../includes/header.php';
forumCSS();
?>

<div class="breadcrumb">
  <a href="/forum/index.php">Forum</a><span class="sep">›</span>
  <span style="color:var(--text-primary);">Search</span>
</div>

<h1 class="mb-1">🔍 Search Forum</h1>

<!-- Search Form -->
<div class="card">
  <form method="GET" action="/forum/search.php">
    <div class="grid-3" style="gap:1rem;">
      <div class="form-group" style="grid-column:span 2;">
        <label for="q">Search Query</label>
        <input type="text" id="q" name="q" value="<?= e($query) ?>"
               placeholder="Search threads and posts…" minlength="2" required>
      </div>
      <div class="form-group">
        <label for="board">Board (optional)</label>
        <select id="board" name="board">
          <option value="0">All Boards</option>
          <?php foreach ($allBoards as $ab): ?>
            <option value="<?= $ab['board_id'] ?>" <?= $boardFilter==$ab['board_id']?'selected':'' ?>>
              <?= e($ab['cat_name']) ?> › <?= e($ab['name']) ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>
    </div>
    <button type="submit" class="btn btn-primary">Search</button>
  </form>
</div>

<!-- Results -->
<?php if ($query !== ''): ?>
  <div class="card">
    <div class="flex-between flex-between-mobile mb-1">
      <h3>Results for "<?= e($query) ?>"</h3>
      <span class="badge badge-blue"><?= number_format($total) ?> found</span>
    </div>

    <?php if ($results): ?>
      <?php foreach ($results as $r): ?>
        <div class="search-result">
          <div style="display:flex;justify-content:space-between;align-items:flex-start;gap:.5rem;flex-wrap:wrap;">
            <div style="min-width:0;flex:1;">
              <a href="/forum/thread.php?id=<?= $r['thread_id'] ?>#post-<?= $r['post_id'] ?>"
                 style="font-weight:600;font-size:.95rem;">
                <?= e($r['thread_title']) ?>
              </a>
              <div style="font-size:.75rem;color:var(--text-secondary);margin-top:.1rem;">
                by <strong style="color:var(--accent-purple);"><?= e($r['username']) ?></strong>
                in <a href="/forum/board.php?id=<?= $r['board_id'] ?>"><?= e($r['board_name']) ?></a>
                &middot; <?= timeAgo($r['created_at']) ?>
              </div>
            </div>
          </div>
          <div class="sr-snippet">
            <?= highlightSnippet($r['body'], $query) ?>
          </div>
        </div>
      <?php endforeach; ?>
      <?= pgHtml($page, $totalPages, "/forum/search.php?q=" . urlencode($query) . "&board=$boardFilter") ?>
    <?php else: ?>
      <p style="color:var(--text-secondary);text-align:center;padding:1.5rem 0;">
        No results found. Try different keywords.
      </p>
    <?php endif; ?>
  </div>
<?php endif; ?>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>