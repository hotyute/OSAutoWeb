<?php
$pageTitle = 'Admin Panel';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
requireRole('admin');

$totalUsers  = $pdo->query('SELECT COUNT(*) FROM `users`')->fetchColumn();
$totalActive = $pdo->query("SELECT COUNT(*) FROM `subscriptions` WHERE `status`='active' AND `expires_at` > NOW()")->fetchColumn();
$totalBanned = $pdo->query("SELECT COUNT(*) FROM `subscriptions` WHERE `status`='banned'")->fetchColumn();
$totalScripts = $pdo->query('SELECT COUNT(*) FROM `scripts`')->fetchColumn();
$totalLogs    = $pdo->query('SELECT COUNT(*) FROM `logs`')->fetchColumn();

$onlineCount = count(getOnlineUsers($pdo));

$recentLogs = $pdo->query(
    'SELECT l.*, u.username FROM `logs` l
     LEFT JOIN `users` u ON u.user_id = l.user_id
     ORDER BY l.timestamp DESC LIMIT 20'
)->fetchAll();

require_once __DIR__ . '/../includes/header.php';
?>

<div class="flex-between flex-between-mobile mb-1">
  <h1>⚙️ Admin Dashboard</h1>
  <div style="display:flex;gap:.4rem;flex-wrap:wrap;">
    <a href="/admin/users.php" class="btn btn-primary btn-sm">👥 Users</a>
    <a href="/admin/settings.php" class="btn btn-secondary btn-sm">🎛️ Settings</a>
  </div>
</div>

<div class="grid-3 mb-2">
  <div class="card text-center">
    <div style="font-size:2rem;font-weight:800;color:var(--accent-green);"><?= $totalUsers ?></div>
    <div style="color:var(--text-secondary);font-size:.85rem;">Total Users</div>
  </div>
  <div class="card text-center">
    <div style="font-size:2rem;font-weight:800;color:var(--accent-blue);"><?= $totalActive ?></div>
    <div style="color:var(--text-secondary);font-size:.85rem;">Active Subs</div>
  </div>
  <div class="card text-center">
    <div style="font-size:2rem;font-weight:800;color:var(--accent-red);"><?= $totalBanned ?></div>
    <div style="color:var(--text-secondary);font-size:.85rem;">Banned</div>
  </div>
  <div class="card text-center">
    <div style="font-size:2rem;font-weight:800;color:var(--accent-purple);"><?= $totalScripts ?></div>
    <div style="color:var(--text-secondary);font-size:.85rem;">Scripts</div>
  </div>
  <div class="card text-center">
    <div style="font-size:2rem;font-weight:800;color:var(--accent-amber);"><?= $onlineCount ?></div>
    <div style="color:var(--text-secondary);font-size:.85rem;">Online Now</div>
  </div>
  <div class="card" style="display:flex;flex-direction:column;justify-content:center;gap:.5rem;">
    <a href="/admin/users.php" class="btn btn-primary w-full">Manage Users</a>
    <a href="/admin/forums.php" class="btn btn-secondary w-full">Forum Structure</a>
    <a href="/admin/settings.php" class="btn btn-secondary w-full">Site Settings</a>
    <a href="/mod/scripts.php" class="btn btn-secondary w-full">Scripts</a>
  </div>
</div>

<!-- Recent Activity -->
<div class="card">
  <h3>📄 Recent Activity</h3>
  <div class="desktop-only">
    <div class="table-wrap">
      <table>
        <thead><tr><th>User</th><th>Action</th><th>IP</th><th>Time</th></tr></thead>
        <tbody>
          <?php foreach ($recentLogs as $log): ?>
            <tr>
              <td><?= e($log['username'] ?? 'System') ?></td>
              <td><span class="badge <?=
                str_contains($log['action'],'ban')?'badge-red':
                (str_contains($log['action'],'hwid')?'badge-purple':
                (str_contains($log['action'],'login')?'badge-green':'badge-blue'))
              ?>"><?= e($log['action']) ?></span></td>
              <td style="font-family:var(--font-mono);font-size:.8rem;"><?= e($log['ip_address']) ?></td>
              <td style="font-size:.8rem;white-space:nowrap;"><?= e($log['timestamp']) ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
  <div class="mobile-only">
    <?php foreach ($recentLogs as $log): ?>
      <div style="padding:.5rem 0;border-bottom:1px solid var(--border-color);">
        <div style="display:flex;justify-content:space-between;align-items:center;gap:.3rem;flex-wrap:wrap;">
          <strong style="font-size:.85rem;"><?= e($log['username'] ?? 'System') ?></strong>
          <span class="badge <?=
            str_contains($log['action'],'ban')?'badge-red':
            (str_contains($log['action'],'hwid')?'badge-purple':
            (str_contains($log['action'],'login')?'badge-green':'badge-blue'))
          ?>"><?= e($log['action']) ?></span>
        </div>
        <div style="font-size:.72rem;color:var(--text-secondary);margin-top:.15rem;">
          <?= e($log['ip_address']) ?> &middot; <?= e($log['timestamp']) ?>
        </div>
      </div>
    <?php endforeach; ?>
  </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>