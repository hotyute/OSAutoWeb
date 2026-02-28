<?php
$pageTitle = 'Dashboard';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';
requireLogin();

$user = currentUser($pdo);

$subStmt = $pdo->prepare(
    'SELECT * FROM `subscriptions` WHERE `user_id`=? ORDER BY `expires_at` DESC LIMIT 1'
);
$subStmt->execute([$user['user_id']]);
$sub = $subStmt->fetch();

$scripts = $pdo->query(
    'SELECT s.*, u.username AS author_name FROM `scripts` s
     LEFT JOIN `users` u ON u.user_id = s.author_id ORDER BY s.title ASC'
)->fetchAll();

require_once __DIR__ . '/includes/header.php';
?>

<h1 class="mb-1">👋 Welcome, <?= e($user['username']) ?></h1>

<div class="grid-3">
  <div class="card">
    <h3>📋 Subscription</h3>
    <?php if ($sub): ?>
      <?php
        $isActive = ($sub['status']==='active' && strtotime($sub['expires_at'])>time());
        $isBanned = ($sub['status']==='banned');
      ?>
      <p>
        Status:
        <?php if ($isBanned): ?><span class="badge badge-red">BANNED</span>
        <?php elseif ($isActive): ?><span class="badge badge-green">ACTIVE</span>
        <?php else: ?><span class="badge badge-amber">EXPIRED</span>
        <?php endif; ?>
      </p>
      <p style="color:var(--text-secondary);font-size:.88rem;">Expires: <?= e($sub['expires_at']) ?></p>
    <?php else: ?>
      <p style="color:var(--text-secondary);">No active subscription.</p>
      <a href="/index.php#pricing" class="btn btn-primary btn-sm mt-1">View Plans</a>
    <?php endif; ?>
  </div>

  <div class="card">
    <h3>🖥️ Hardware ID</h3>
    <?php if ($user['hwid']): ?>
      <p style="font-family:var(--font-mono);font-size:.78rem;color:var(--accent-purple);word-break:break-all;">
        <?= e($user['hwid']) ?>
      </p>
      <a href="/hwid_reset.php" class="btn btn-secondary btn-sm mt-1">Reset HWID</a>
    <?php else: ?>
      <p style="color:var(--text-secondary);">
        <span class="dot dot-amber"></span>Not bound — launch client to bind.
      </p>
    <?php endif; ?>
  </div>

  <div class="card text-center">
    <h3>⬇️ Download</h3>
    <p style="color:var(--text-secondary);font-size:.88rem;">Latest stable build</p>
    <a href="#" class="btn btn-primary mt-1" onclick="alert('Download placeholder');">Download v2.4.1</a>
  </div>
</div>

<!-- Scripts -->
<div class="card">
  <div class="flex-between flex-between-mobile mb-1">
    <h3>📜 Script Library</h3>
    <span class="badge badge-blue"><?= count($scripts) ?> scripts</span>
  </div>

  <?php if ($scripts): ?>
    <!-- Desktop -->
    <div class="desktop-only">
      <div class="table-wrap">
        <table>
          <thead>
            <tr><th>Title</th><th>Category</th><th>Version</th><th>Type</th><th>Author</th></tr>
          </thead>
          <tbody>
            <?php foreach ($scripts as $s): ?>
              <tr>
                <td><?= e($s['title']) ?></td>
                <td><?= e($s['category']) ?></td>
                <td style="font-family:var(--font-mono);"><?= e($s['version']) ?></td>
                <td>
                  <?= $s['is_premium']
                    ? '<span class="badge badge-purple">Premium</span>'
                    : '<span class="badge badge-green">Free</span>' ?>
                </td>
                <td><?= e($s['author_name'] ?? 'System') ?></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>

    <!-- Mobile -->
    <div class="mobile-only">
      <?php foreach ($scripts as $s): ?>
        <div style="padding:.65rem 0;border-bottom:1px solid var(--border-color);">
          <div style="display:flex;justify-content:space-between;align-items:center;gap:.5rem;">
            <strong style="font-size:.92rem;"><?= e($s['title']) ?></strong>
            <?= $s['is_premium']
              ? '<span class="badge badge-purple">Premium</span>'
              : '<span class="badge badge-green">Free</span>' ?>
          </div>
          <div style="font-size:.78rem;color:var(--text-secondary);margin-top:.15rem;">
            <?= e($s['category']) ?> &middot;
            <span style="font-family:var(--font-mono);">v<?= e($s['version']) ?></span> &middot;
            <?= e($s['author_name'] ?? 'System') ?>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  <?php else: ?>
    <p style="color:var(--text-secondary);">No scripts available yet.</p>
  <?php endif; ?>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>