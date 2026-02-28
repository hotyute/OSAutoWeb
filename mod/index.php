<?php
/**
 * Moderator Panel — Responsive Dashboard
 * Tables wrapped for horizontal scroll. Filter bar stacks on mobile.
 */
$pageTitle = 'Mod Panel';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
requireRole('moderator');

$flash = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if (!validateCSRF($_POST['csrf_token'] ?? '')) {
        $flash = '❌ CSRF validation failed.';
    } else {
        $targetId = (int)($_POST['target_user_id'] ?? 0);
        if ($_POST['action'] === 'hwid_clear' && $targetId > 0) {
            $pdo->prepare(
                'UPDATE `users` SET `hwid` = NULL, `hwid_updated_at` = NOW() WHERE `user_id` = ?'
            )->execute([$targetId]);
            logAction($pdo, $targetId, 'hwid_reset_by_mod:' . $_SESSION['user_id']);
            $flash = "✅ HWID cleared for user #$targetId.";
        }
    }
}

$filterAction = trim($_GET['filter'] ?? '');
if ($filterAction !== '') {
    $logStmt = $pdo->prepare(
        'SELECT l.*, u.username FROM `logs` l
         LEFT JOIN `users` u ON u.user_id = l.user_id
         WHERE l.action LIKE ?
         ORDER BY l.timestamp DESC LIMIT 200'
    );
    $logStmt->execute(["%$filterAction%"]);
} else {
    $logStmt = $pdo->query(
        'SELECT l.*, u.username FROM `logs` l
         LEFT JOIN `users` u ON u.user_id = l.user_id
         ORDER BY l.timestamp DESC LIMIT 200'
    );
}
$logs = $logStmt->fetchAll();

$hwidUsers = $pdo->query(
    'SELECT user_id, username, hwid, hwid_updated_at
     FROM `users` WHERE `hwid` IS NOT NULL
     ORDER BY hwid_updated_at DESC LIMIT 50'
)->fetchAll();

require_once __DIR__ . '/../includes/header.php';
?>

<div class="flex-between flex-between-mobile mb-1">
  <h1>🛡️ Moderator Panel</h1>
  <a href="/mod/scripts.php" class="btn btn-secondary btn-sm">📜 Manage Scripts</a>
</div>

<?php if ($flash): ?>
  <div class="alert <?= str_starts_with($flash, '✅') ? 'alert-success' : 'alert-error' ?>">
    <?= e($flash) ?>
  </div>
<?php endif; ?>

<!-- HWID Management -->
<div class="card">
  <h3>🖥️ Bound HWIDs — Manual Clear</h3>
  <div class="table-wrap">
    <table>
      <thead>
        <tr><th>User</th><th>HWID</th><th>Last Updated</th><th>Action</th></tr>
      </thead>
      <tbody>
        <?php foreach ($hwidUsers as $hu): ?>
          <tr>
            <td><strong><?= e($hu['username']) ?></strong></td>
            <td style="font-family:var(--font-mono);font-size:.76rem;max-width:200px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;"
                title="<?= e($hu['hwid']) ?>">
              <?= e($hu['hwid']) ?>
            </td>
            <td style="font-size:.82rem;"><?= e($hu['hwid_updated_at'] ?? '—') ?></td>
            <td>
              <form method="POST" style="display:inline;">
                <input type="hidden" name="csrf_token" value="<?= e(generateCSRF()) ?>">
                <input type="hidden" name="action" value="hwid_clear">
                <input type="hidden" name="target_user_id" value="<?= $hu['user_id'] ?>">
                <button type="submit" class="btn btn-danger btn-sm"
                        onclick="return confirm('Clear HWID for <?= e($hu['username']) ?>?');">
                  Clear
                </button>
              </form>
            </td>
          </tr>
        <?php endforeach; ?>
        <?php if (!$hwidUsers): ?>
          <tr><td colspan="4" style="color:var(--text-secondary);text-align:center;">No bound HWIDs.</td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<!-- Audit Logs -->
<div class="card">
  <div class="flex-between flex-between-mobile mb-1">
    <h3>📄 Audit Logs</h3>
    <form method="GET" class="filter-bar">
      <input type="text" name="filter" placeholder="Filter action…"
             value="<?= e($filterAction) ?>">
      <button type="submit" class="btn btn-secondary btn-sm">Filter</button>
      <?php if ($filterAction): ?>
        <a href="/mod/index.php" class="btn btn-sm btn-secondary">Clear</a>
      <?php endif; ?>
    </form>
  </div>
  <div class="log-scroll">
    <div class="table-wrap">
      <table>
        <thead>
          <tr><th>ID</th><th>User</th><th>Action</th><th>IP</th><th>Time</th></tr>
        </thead>
        <tbody>
          <?php foreach ($logs as $log): ?>
            <tr>
              <td><?= $log['log_id'] ?></td>
              <td><?= e($log['username'] ?? 'N/A') ?></td>
              <td>
                <span class="badge <?=
                  str_contains($log['action'], 'ban') ? 'badge-red' :
                  (str_contains($log['action'], 'hwid') ? 'badge-purple' :
                  (str_contains($log['action'], 'login') ? 'badge-green' : 'badge-blue'))
                ?>"><?= e($log['action']) ?></span>
              </td>
              <td style="font-family:var(--font-mono);font-size:.8rem;"><?= e($log['ip_address']) ?></td>
              <td style="font-size:.8rem;white-space:nowrap;"><?= e($log['timestamp']) ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>