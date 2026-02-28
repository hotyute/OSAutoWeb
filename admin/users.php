<?php
/**
 * Admin — User Management
 * --------------------------------------------------------
 * Desktop: full-featured data table.
 * Mobile:  card-per-user layout with stacked action forms.
 * CSS toggles .desktop-only / .mobile-only at 680px.
 */
$pageTitle = 'Manage Users';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
requireRole('admin');

$flash = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateCSRF($_POST['csrf_token'] ?? '')) {
        $flash = '❌ CSRF validation failed.';
    } else {
        $action   = $_POST['action'] ?? '';
        $targetId = (int)($_POST['target_user_id'] ?? 0);

        if ($targetId < 1) {
            $flash = '❌ Invalid user.';
        } else {
            switch ($action) {
                case 'change_role':
                    $newRole = $_POST['new_role'] ?? 'user';
                    if (in_array($newRole, ['user','moderator','admin'])) {
                        $pdo->prepare('UPDATE `users` SET `role`=? WHERE `user_id`=?')
                            ->execute([$newRole, $targetId]);
                        logAction($pdo, $targetId, "role_changed_to:$newRole:by:" . $_SESSION['user_id']);
                        $flash = "✅ Role → $newRole.";
                    }
                    break;

                case 'grant_sub':
                    $days = max(1, (int)($_POST['days'] ?? 30));
                    $existing = $pdo->prepare(
                        "SELECT sub_id FROM `subscriptions`
                         WHERE user_id=? AND status='active' AND expires_at > NOW() LIMIT 1"
                    );
                    $existing->execute([$targetId]);
                    if ($existing->fetch()) {
                        $pdo->prepare(
                            "UPDATE `subscriptions`
                             SET `expires_at`=DATE_ADD(`expires_at`, INTERVAL ? DAY)
                             WHERE `user_id`=? AND `status`='active' AND `expires_at`>NOW()"
                        )->execute([$days, $targetId]);
                    } else {
                        $pdo->prepare(
                            "INSERT INTO `subscriptions` (`user_id`,`status`,`expires_at`)
                             VALUES (?,'active',DATE_ADD(NOW(), INTERVAL ? DAY))"
                        )->execute([$targetId, $days]);
                    }
                    logAction($pdo, $targetId, "sub_granted:{$days}d:by:" . $_SESSION['user_id']);
                    $flash = "✅ +{$days}d subscription.";
                    break;

                case 'revoke_sub':
                    $pdo->prepare(
                        "UPDATE `subscriptions` SET `status`='expired',`expires_at`=NOW()
                         WHERE `user_id`=? AND `status`='active'"
                    )->execute([$targetId]);
                    logAction($pdo, $targetId, 'sub_revoked:by:' . $_SESSION['user_id']);
                    $flash = '✅ Subscription revoked.';
                    break;

                case 'ban':
                    $hasSub = $pdo->prepare('SELECT sub_id FROM subscriptions WHERE user_id=? LIMIT 1');
                    $hasSub->execute([$targetId]);
                    if ($hasSub->fetch()) {
                        $pdo->prepare("UPDATE `subscriptions` SET `status`='banned' WHERE `user_id`=?")
                            ->execute([$targetId]);
                    } else {
                        $pdo->prepare(
                            "INSERT INTO `subscriptions` (`user_id`,`status`,`expires_at`) VALUES (?,'banned',NOW())"
                        )->execute([$targetId]);
                    }
                    logAction($pdo, $targetId, 'ban:by:' . $_SESSION['user_id']);
                    $flash = '✅ User banned.';
                    break;

                case 'unban':
                    $pdo->prepare(
                        "UPDATE `subscriptions` SET `status`='expired' WHERE `user_id`=? AND `status`='banned'"
                    )->execute([$targetId]);
                    logAction($pdo, $targetId, 'unban:by:' . $_SESSION['user_id']);
                    $flash = '✅ User unbanned.';
                    break;
            }
        }
    }
}

$users = $pdo->query(
    'SELECT u.*,
            s.status AS sub_status,
            s.expires_at AS sub_expires
     FROM `users` u
     LEFT JOIN (
        SELECT user_id, status, expires_at
        FROM `subscriptions`
        WHERE sub_id IN (SELECT MAX(sub_id) FROM subscriptions GROUP BY user_id)
     ) s ON s.user_id = u.user_id
     ORDER BY u.user_id ASC'
)->fetchAll();

/* Helper to determine sub display */
function subBadge(array $u): string {
    $ss = $u['sub_status'] ?? null;
    if ($ss === 'banned') return '<span class="badge badge-red">Banned</span>';
    if ($ss === 'active' && strtotime($u['sub_expires']) > time())
        return '<span class="badge badge-green">Active</span>';
    return '<span style="color:var(--text-secondary);">—</span>';
}

require_once __DIR__ . '/../includes/header.php';
?>

<div class="flex-between flex-between-mobile mb-1">
  <h1>👥 User Management</h1>
  <a href="/admin/index.php" class="btn btn-secondary btn-sm">&larr; Admin Home</a>
</div>

<?php if ($flash): ?>
  <div class="alert <?= str_starts_with($flash, '✅') ? 'alert-success' : 'alert-error' ?>">
    <?= e($flash) ?>
  </div>
<?php endif; ?>

<!-- ===================== DESKTOP TABLE ===================== -->
<div class="card desktop-only" style="padding:1rem;">
  <div class="table-wrap">
    <table>
      <thead>
        <tr>
          <th>ID</th><th>Username</th><th>Email</th><th>Role</th>
          <th>Sub</th><th>Expires</th><th>HWID</th><th>Joined</th>
          <th style="min-width:260px;">Actions</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($users as $u): ?>
          <tr>
            <td><?= $u['user_id'] ?></td>
            <td><strong><?= e($u['username']) ?></strong></td>
            <td style="font-size:.82rem;"><?= e($u['email']) ?></td>
            <td>
              <span class="badge <?=
                $u['role']==='admin'?'badge-red':($u['role']==='moderator'?'badge-amber':'badge-blue')
              ?>"><?= e($u['role']) ?></span>
            </td>
            <td><?= subBadge($u) ?></td>
            <td style="font-size:.82rem;white-space:nowrap;"><?= e($u['sub_expires'] ?? '—') ?></td>
            <td style="font-family:var(--font-mono);font-size:.72rem;max-width:90px;
                        overflow:hidden;text-overflow:ellipsis;white-space:nowrap;"
                title="<?= e($u['hwid'] ?? '') ?>">
              <?= $u['hwid'] ? e(substr($u['hwid'],0,14)).'…' : '—' ?>
            </td>
            <td style="font-size:.82rem;white-space:nowrap;">
              <?= e(date('Y-m-d', strtotime($u['created_at']))) ?>
            </td>
            <td>
              <!-- Role -->
              <form method="POST" style="display:inline-flex;gap:.3rem;align-items:center;margin-bottom:.3rem;">
                <input type="hidden" name="csrf_token" value="<?= e(generateCSRF()) ?>">
                <input type="hidden" name="action" value="change_role">
                <input type="hidden" name="target_user_id" value="<?= $u['user_id'] ?>">
                <select name="new_role" style="padding:.2rem;font-size:.78rem;border-radius:4px;
                  border:1px solid var(--border-color);background:var(--bg-secondary);color:var(--text-primary);">
                  <option value="user" <?= $u['role']==='user'?'selected':'' ?>>User</option>
                  <option value="moderator" <?= $u['role']==='moderator'?'selected':'' ?>>Mod</option>
                  <option value="admin" <?= $u['role']==='admin'?'selected':'' ?>>Admin</option>
                </select>
                <button type="submit" class="btn btn-secondary btn-sm">Set</button>
              </form>
              <!-- Grant -->
              <form method="POST" style="display:inline-flex;gap:.3rem;align-items:center;margin-bottom:.3rem;">
                <input type="hidden" name="csrf_token" value="<?= e(generateCSRF()) ?>">
                <input type="hidden" name="action" value="grant_sub">
                <input type="hidden" name="target_user_id" value="<?= $u['user_id'] ?>">
                <input type="number" name="days" value="30" min="1" max="365"
                       style="width:55px;padding:.2rem;font-size:.78rem;border-radius:4px;
                       border:1px solid var(--border-color);background:var(--bg-secondary);color:var(--text-primary);">
                <button type="submit" class="btn btn-primary btn-sm">+Sub</button>
              </form>
              <!-- Ban/Unban + Revoke -->
              <div style="display:flex;gap:.25rem;flex-wrap:wrap;">
                <form method="POST" style="display:inline;">
                  <input type="hidden" name="csrf_token" value="<?= e(generateCSRF()) ?>">
                  <input type="hidden" name="target_user_id" value="<?= $u['user_id'] ?>">
                  <?php if (($u['sub_status'] ?? '') === 'banned'): ?>
                    <input type="hidden" name="action" value="unban">
                    <button type="submit" class="btn btn-secondary btn-sm">Unban</button>
                  <?php else: ?>
                    <input type="hidden" name="action" value="ban">
                    <button type="submit" class="btn btn-danger btn-sm"
                            onclick="return confirm('Ban?');">Ban</button>
                  <?php endif; ?>
                </form>
                <?php if (($u['sub_status'] ?? '')==='active'): ?>
                  <form method="POST" style="display:inline;">
                    <input type="hidden" name="csrf_token" value="<?= e(generateCSRF()) ?>">
                    <input type="hidden" name="action" value="revoke_sub">
                    <input type="hidden" name="target_user_id" value="<?= $u['user_id'] ?>">
                    <button type="submit" class="btn btn-danger btn-sm"
                            onclick="return confirm('Revoke?');">Revoke</button>
                  </form>
                <?php endif; ?>
              </div>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<!-- ===================== MOBILE CARDS ===================== -->
<div class="mobile-only">
  <?php foreach ($users as $u): ?>
    <div class="user-mgmt-card">
      <div class="umc-header">
        <span class="umc-name"><?= e($u['username']) ?></span>
        <span class="badge <?=
          $u['role']==='admin'?'badge-red':($u['role']==='moderator'?'badge-amber':'badge-blue')
        ?>"><?= e(ucfirst($u['role'])) ?></span>
      </div>

      <div class="umc-body">
        <div class="umc-row">
          <span class="umc-label">ID</span>
          <span class="umc-value">#<?= $u['user_id'] ?></span>
        </div>
        <div class="umc-row">
          <span class="umc-label">Email</span>
          <span class="umc-value" style="font-size:.82rem;"><?= e($u['email']) ?></span>
        </div>
        <div class="umc-row">
          <span class="umc-label">Status</span>
          <span class="umc-value"><?= subBadge($u) ?></span>
        </div>
        <div class="umc-row">
          <span class="umc-label">Expires</span>
          <span class="umc-value" style="font-size:.82rem;">
            <?= e($u['sub_expires'] ?? '—') ?>
          </span>
        </div>
        <div class="umc-row">
          <span class="umc-label">HWID</span>
          <span class="umc-value" style="font-family:var(--font-mono);font-size:.72rem;">
            <?= $u['hwid'] ? e(substr($u['hwid'],0,20)).'…' : '—' ?>
          </span>
        </div>
        <div class="umc-row">
          <span class="umc-label">Joined</span>
          <span class="umc-value" style="font-size:.82rem;">
            <?= e(date('Y-m-d', strtotime($u['created_at']))) ?>
          </span>
        </div>
      </div>

      <div class="umc-actions">
        <!-- Role change -->
        <form method="POST">
          <input type="hidden" name="csrf_token" value="<?= e(generateCSRF()) ?>">
          <input type="hidden" name="action" value="change_role">
          <input type="hidden" name="target_user_id" value="<?= $u['user_id'] ?>">
          <select name="new_role">
            <option value="user" <?= $u['role']==='user'?'selected':'' ?>>User</option>
            <option value="moderator" <?= $u['role']==='moderator'?'selected':'' ?>>Mod</option>
            <option value="admin" <?= $u['role']==='admin'?'selected':'' ?>>Admin</option>
          </select>
          <button type="submit" class="btn btn-secondary btn-sm">Set Role</button>
        </form>

        <!-- Grant sub -->
        <form method="POST">
          <input type="hidden" name="csrf_token" value="<?= e(generateCSRF()) ?>">
          <input type="hidden" name="action" value="grant_sub">
          <input type="hidden" name="target_user_id" value="<?= $u['user_id'] ?>">
          <input type="number" name="days" value="30" min="1" max="365">
          <button type="submit" class="btn btn-primary btn-sm">+Sub</button>
        </form>

        <!-- Ban / Unban / Revoke -->
        <div class="umc-btn-row">
          <form method="POST">
            <input type="hidden" name="csrf_token" value="<?= e(generateCSRF()) ?>">
            <input type="hidden" name="target_user_id" value="<?= $u['user_id'] ?>">
            <?php if (($u['sub_status'] ?? '') === 'banned'): ?>
              <input type="hidden" name="action" value="unban">
              <button type="submit" class="btn btn-secondary btn-sm">Unban</button>
            <?php else: ?>
              <input type="hidden" name="action" value="ban">
              <button type="submit" class="btn btn-danger btn-sm"
                      onclick="return confirm('Ban <?= e($u['username']) ?>?');">Ban</button>
            <?php endif; ?>
          </form>
          <?php if (($u['sub_status'] ?? '')==='active'): ?>
            <form method="POST">
              <input type="hidden" name="csrf_token" value="<?= e(generateCSRF()) ?>">
              <input type="hidden" name="action" value="revoke_sub">
              <input type="hidden" name="target_user_id" value="<?= $u['user_id'] ?>">
              <button type="submit" class="btn btn-danger btn-sm"
                      onclick="return confirm('Revoke?');">Revoke</button>
            </form>
          <?php endif; ?>
        </div>
      </div>
    </div>
  <?php endforeach; ?>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>