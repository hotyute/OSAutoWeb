<?php
/**
 * Admin Panel — Dashboard
 * --------------------------------------------------------
 * Access: role = admin ONLY.
 * Overview stats + quick links to sub-pages.
 */
$pageTitle = 'Admin Panel';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
requireRole('admin');

/* Quick stats */
$totalUsers  = $pdo->query('SELECT COUNT(*) FROM `users`')->fetchColumn();
$totalActive = $pdo->query(
    "SELECT COUNT(*) FROM `subscriptions` WHERE `status`='active' AND `expires_at` > NOW()"
)->fetchColumn();
$totalBanned = $pdo->query(
    "SELECT COUNT(*) FROM `subscriptions` WHERE `status`='banned'"
)->fetchColumn();
$totalScripts = $pdo->query('SELECT COUNT(*) FROM `scripts`')->fetchColumn();
$totalLogs    = $pdo->query('SELECT COUNT(*) FROM `logs`')->fetchColumn();

/* Recent logs (last 20) */
$recentLogs = $pdo->query(
    'SELECT l.*, u.username FROM `logs` l
     LEFT JOIN `users` u ON u.user_id = l.user_id
     ORDER BY l.timestamp DESC LIMIT 20'
)->fetchAll();

require_once __DIR__ . '/../includes/header.php';
?>

<h1 class="mb-1">⚙️ Admin Dashboard</h1>

<!-- Stat Cards -->
<div class="grid-3 mb-2">
  <div class="card text-center">
    <div style="font-size:2.2rem;font-weight:800;color:var(--accent-green);"><?= $totalUsers ?></div>
    <div style="color:var(--text-secondary);">Total Users</div>
  </div>
  <div class="card text-center">
    <div style="font-size:2.2rem;font-weight:800;color:var(--accent-blue);"><?= $totalActive ?></div>
    <div style="color:var(--text-secondary);">Active Subs</div>
  </div>
  <div class="card text-center">
    <div style="font-size:2.2rem;font-weight:800;color:var(--accent-red);"><?= $totalBanned ?></div>
    <div style="color:var(--text-secondary);">Banned</div>
  </div>
  <div class="card text-center">
    <div style="font-size:2.2rem;font-weight:800;color:var(--accent-purple);"><?= $totalScripts ?></div>
    <div style="color:var(--text-secondary);">Scripts</div>
  </div>
  <div class="card text-center">
    <div style="font-size:2.2rem;font-weight:800;color:var(--accent-amber);"><?= $totalLogs ?></div>
    <div style="color:var(--text-secondary);">Log Entries</div>
  </div>
  <div class="card text-center">
    <a href="/admin/users.php" class="btn btn-primary" style="width:100%;">Manage Users</a>
    <div class="mt-1">
      <a href="/mod/scripts.php" class="btn btn-secondary btn-sm" style="width:100%;">Manage Scripts</a>
    </div>
  </div>
</div>

<!-- Recent Activity -->
<div class="card">
  <h3>📄 Recent Activity</h3>
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
          <td style="font-family:var(--font-mono);font-size:.82rem;"><?= e($log['ip_address']) ?></td>
          <td style="font-size:.82rem;"><?= e($log['timestamp']) ?></td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>