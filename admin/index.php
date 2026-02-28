<?php
/**
 * Admin Dashboard — Responsive
 * Stat grid uses grid-3, falls to 2-col and then 1-col via CSS.
 */
$pageTitle = 'Admin Panel';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
requireRole('admin');

$totalUsers  = $pdo->query('SELECT COUNT(*) FROM `users`')->fetchColumn();
$totalActive = $pdo->query(
    "SELECT COUNT(*) FROM `subscriptions` WHERE `status`='active' AND `expires_at` > NOW()"
)->fetchColumn();
$totalBanned = $pdo->query(
    "SELECT COUNT(*) FROM `subscriptions` WHERE `status`='banned'"
)->fetchColumn();
$totalScripts = $pdo->query('SELECT COUNT(*) FROM `scripts`')->fetchColumn();
$totalLogs    = $pdo->query('SELECT COUNT(*) FROM `logs`')->fetchColumn();

$recentLogs = $pdo->query(
    'SELECT l.*, u.username FROM `logs` l
     LEFT JOIN `users` u ON u.user_id = l.user_id
     ORDER BY l.timestamp DESC LIMIT 20'
)->fetchAll();

require_once __DIR__ . '/../includes/header.php';
?>

<div class="flex-between flex-between-mobile mb-1">
  <h1>⚙️ Admin Dashboard</h1>
  <a href="/admin/users.php" class="btn btn-primary btn-sm">👥 Manage Users</a>
</div>

<!-- Stat Cards -->
<div class="grid-3 mb-2">
  <div class="card text-center">
    <div class="stat-number" style="color:var(--accent-green);"><?= $totalUsers ?></div>
    <div class="stat-label">Total Users</div>
  </div>
  <div class="card text-center">
    <div class="stat-number" style="color:var(--accent-blue);"><?= $totalActive ?></div>
    <div class="stat-label">Active Subs</div>
  </div>
  <div class="card text-center">
    <div class="stat-number" style="color:var(--accent-red);"><?= $totalBanned ?></div>
    <div class="stat-label">Banned</div>
  </div>
  <div class="card text-center">
    <div class="stat-number" style="color:var(--accent-purple);"><?= $totalScripts ?></div>
    <div class="stat-label">Scripts</div>
  </div>
  <div class="card text-center">
    <div class="stat-number" style="color:var(--accent-amber);"><?= $totalLogs ?></div>
    <div class="stat-label">Log Entries</div>
  </div>
  <div class="card text-center" style="display:flex;flex-direction:column;justify-content:center;gap:.5rem;">
    <a href="/admin/users.php" class="btn btn-primary w-full">Manage Users</a>
    <a href="/mod/scripts.php" class="btn btn-secondary w-full">Manage Scripts</a>
    <a href="/mod/index.php" class="btn btn-secondary w-full">Mod Panel</a>
  </div>
</div>

<!-- Recent Activity -->
<div class="card">
  <h3>📄 Recent Activity</h3>
  <div class="table-wrap">
    <table>
      <thead>
        <tr><th>User</th><th>Action</th><th>IP</th><th>Time</th></tr>
      </thead>
      <tbody>
        <?php foreach ($recentLogs as $log): ?>
          <tr>
            <td><?= e($log['username'] ?? 'System') ?></td>
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

<?php require_once __DIR__ . '/../includes/footer.php'; ?>