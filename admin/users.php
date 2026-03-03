<?php
/**
 * User Management — Role-Aware
 * --------------------------------------------------------
 * Admin: Full access — all actions, all roles.
 * Moderator: Limited access:
 *   • Can change roles (but NOT to/from admin)
 *   • Can grant subs (max 3 days)
 *   • Can ban/unban (but NOT admins)
 *   • Cannot see admin actions on admin accounts
 */
$pageTitle = 'Manage Users';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
requireRole('moderator'); /* Mods + Admins */

$isAdmin   = hasRole('admin');
$myRole    = $_SESSION['role'];
$maxSubDays = $isAdmin ? 365 : 3; /* Mods capped at 3 days */

$flash = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateCSRF($_POST['csrf_token'] ?? '')) {
        $flash = '❌ CSRF failed.';
    } else {
        $action   = $_POST['action'] ?? '';
        $targetId = (int)($_POST['target_user_id'] ?? 0);

        if ($targetId < 1) {
            $flash = '❌ Invalid user.';
        } else {
            /* Fetch target user to check their role */
            $tgtStmt = $pdo->prepare('SELECT `role` FROM `users` WHERE `user_id`=?');
            $tgtStmt->execute([$targetId]);
            $target = $tgtStmt->fetch();

            if (!$target) {
                $flash = '❌ User not found.';
            } elseif (!canModerate($target['role'])) {
                /* Security: can't act on equal or higher rank */
                $flash = '❌ You cannot moderate this user.';
            } else {
                switch ($action) {
                    case 'change_role':
                        $newRole = $_POST['new_role'] ?? 'user';
                        $assignable = getAssignableRoles();
                        if (!in_array($newRole, $assignable)) {
                            $flash = '❌ You cannot assign that role.';
                        } else {
                            $pdo->prepare('UPDATE `users` SET `role`=? WHERE `user_id`=?')
                                ->execute([$newRole, $targetId]);
                            logAction($pdo, $targetId, "role_changed_to:$newRole:by:" . $_SESSION['user_id']);
                            $flash = "✅ Role → " . roleDisplayName($newRole) . ".";
                        }
                        break;

                    case 'grant_sub':
                        $days = max(1, min((int)($_POST['days'] ?? 3), $maxSubDays));
                        $existing = $pdo->prepare(
                            "SELECT sub_id FROM subscriptions WHERE user_id=? AND status='active' AND expires_at>NOW() LIMIT 1"
                        );
                        $existing->execute([$targetId]);
                        if ($existing->fetch()) {
                            $pdo->prepare(
                                "UPDATE subscriptions SET expires_at=DATE_ADD(expires_at, INTERVAL ? DAY)
                                 WHERE user_id=? AND status='active' AND expires_at>NOW()"
                            )->execute([$days, $targetId]);
                        } else {
                            $pdo->prepare(
                                "INSERT INTO subscriptions (user_id,status,expires_at) VALUES (?,'active',DATE_ADD(NOW(), INTERVAL ? DAY))"
                            )->execute([$targetId, $days]);
                        }
                        logAction($pdo, $targetId, "sub_granted:{$days}d:by:" . $_SESSION['user_id']);
                        $flash = "✅ +{$days}d sub.";
                        break;

                    case 'revoke_sub':
                        $pdo->prepare("UPDATE subscriptions SET status='expired',expires_at=NOW() WHERE user_id=? AND status='active'")
                            ->execute([$targetId]);
                        logAction($pdo, $targetId, 'sub_revoked:by:' . $_SESSION['user_id']);
                        $flash = '✅ Sub revoked.';
                        break;

                    case 'ban':
                        $hasSub = $pdo->prepare('SELECT sub_id FROM subscriptions WHERE user_id=? LIMIT 1');
                        $hasSub->execute([$targetId]);
                        if ($hasSub->fetch()) {
                            $pdo->prepare("UPDATE subscriptions SET status='banned' WHERE user_id=?")->execute([$targetId]);
                        } else {
                            $pdo->prepare("INSERT INTO subscriptions (user_id,status,expires_at) VALUES (?,'banned',NOW())")->execute([$targetId]);
                        }
                        logAction($pdo, $targetId, 'ban:by:' . $_SESSION['user_id']);
                        $flash = '✅ Banned.';
                        break;

                    case 'unban':
                        $pdo->prepare("UPDATE subscriptions SET status='expired' WHERE user_id=? AND status='banned'")->execute([$targetId]);
                        logAction($pdo, $targetId, 'unban:by:' . $_SESSION['user_id']);
                        $flash = '✅ Unbanned.';
                        break;
                }
            }
        }
    }
}

$users = $pdo->query(
    'SELECT u.*, s.status AS sub_status, s.expires_at AS sub_expires
     FROM users u
     LEFT JOIN (
        SELECT user_id, status, expires_at
        FROM subscriptions WHERE sub_id IN (SELECT MAX(sub_id) FROM subscriptions GROUP BY user_id)
     ) s ON s.user_id = u.user_id
     ORDER BY u.user_id ASC'
)->fetchAll();

function subBadge(array $u): string {
    $ss = $u['sub_status'] ?? null;
    if ($ss === 'banned') return '<span class="badge badge-red">Banned</span>';
    if ($ss === 'active' && strtotime($u['sub_expires']) > time()) return '<span class="badge badge-green">Active</span>';
    return '<span style="color:var(--text-secondary);">—</span>';
}

require_once __DIR__ . '/../includes/header.php';
?>

<div class="flex-between flex-between-mobile mb-1">
  <h1>👥 User Management</h1>
  <div style="display:flex;gap:.4rem;flex-wrap:wrap;">
    <?php if (!$isAdmin): ?>
      <div class="alert alert-warn" style="margin:0;padding:.3rem .8rem;font-size:.78rem;">
        🔒 Mod mode: max <?= $maxSubDays ?>d subs, cannot affect admins
      </div>
    <?php endif; ?>
    <a href="<?= $isAdmin ? '/admin/index.php' : '/mod/index.php' ?>" class="btn btn-secondary btn-sm">&larr; Back</a>
  </div>
</div>

<?php if ($flash): ?>
  <div class="alert <?= str_starts_with($flash, '✅') ? 'alert-success' : 'alert-error' ?>"><?= e($flash) ?></div>
<?php endif; ?>

<!-- Desktop Table -->
<div class="card desktop-only" style="padding:1rem;">
  <div class="table-wrap">
    <table>
      <thead>
        <tr><th>ID</th><th>Username</th><th>Email</th><th>Role</th><th>Sub</th><th>Expires</th><th>Joined</th><th style="min-width:260px;">Actions</th></tr>
      </thead>
      <tbody>
        <?php foreach ($users as $u):
          $canAct = canModerate($u['role']);
        ?>
          <tr<?= !$canAct ? ' style="opacity:.5;"' : '' ?>>
            <td><?= $u['user_id'] ?></td>
            <td><strong><?= e($u['username']) ?></strong></td>
            <td style="font-size:.82rem;"><?= e($u['email']) ?></td>
            <td><span class="badge <?= roleBadgeClass($u['role']) ?>"><?= roleDisplayName($u['role']) ?></span></td>
            <td><?= subBadge($u) ?></td>
            <td style="font-size:.82rem;white-space:nowrap;"><?= e($u['sub_expires'] ?? '—') ?></td>
            <td style="font-size:.82rem;white-space:nowrap;"><?= e(date('Y-m-d', strtotime($u['created_at']))) ?></td>
            <td>
              <?php if ($canAct): ?>
                <!-- Role -->
                <form method="POST" style="display:inline-flex;gap:.3rem;align-items:center;margin-bottom:.3rem;">
                  <input type="hidden" name="csrf_token" value="<?= e(generateCSRF()) ?>">
                  <input type="hidden" name="action" value="change_role">
                  <input type="hidden" name="target_user_id" value="<?= $u['user_id'] ?>">
                  <select name="new_role" style="padding:.2rem;font-size:.78rem;border-radius:4px;border:1px solid var(--border-color);background:var(--bg-secondary);color:var(--text-primary);">
                    <?php foreach (getAssignableRoles() as $r): ?>
                      <option value="<?= $r ?>" <?= $u['role']===$r?'selected':'' ?>><?= roleDisplayName($r) ?></option>
                    <?php endforeach; ?>
                  </select>
                  <button type="submit" class="btn btn-secondary btn-sm">Set</button>
                </form>
                <!-- Sub -->
                <form method="POST" style="display:inline-flex;gap:.3rem;align-items:center;margin-bottom:.3rem;">
                  <input type="hidden" name="csrf_token" value="<?= e(generateCSRF()) ?>">
                  <input type="hidden" name="action" value="grant_sub">
                  <input type="hidden" name="target_user_id" value="<?= $u['user_id'] ?>">
                  <input type="number" name="days" value="<?= min(30, $maxSubDays) ?>" min="1" max="<?= $maxSubDays ?>"
                         style="width:55px;padding:.2rem;font-size:.78rem;border-radius:4px;border:1px solid var(--border-color);background:var(--bg-secondary);color:var(--text-primary);">
                  <button type="submit" class="btn btn-primary btn-sm">+Sub</button>
                </form>
                <!-- Ban/Unban/Revoke -->
                <div style="display:flex;gap:.25rem;flex-wrap:wrap;">
                  <form method="POST" style="display:inline;">
                    <input type="hidden" name="csrf_token" value="<?= e(generateCSRF()) ?>">
                    <input type="hidden" name="target_user_id" value="<?= $u['user_id'] ?>">
                    <?php if (($u['sub_status'] ?? '')==='banned'): ?>
                      <input type="hidden" name="action" value="unban">
                      <button class="btn btn-secondary btn-sm">Unban</button>
                    <?php else: ?>
                      <input type="hidden" name="action" value="ban">
                      <button class="btn btn-danger btn-sm" onclick="return confirm('Ban?');">Ban</button>
                    <?php endif; ?>
                  </form>
                  <?php if (($u['sub_status'] ?? '')==='active'): ?>
                    <form method="POST" style="display:inline;">
                      <input type="hidden" name="csrf_token" value="<?= e(generateCSRF()) ?>">
                      <input type="hidden" name="action" value="revoke_sub">
                      <input type="hidden" name="target_user_id" value="<?= $u['user_id'] ?>">
                      <button class="btn btn-danger btn-sm" onclick="return confirm('Revoke?');">Revoke</button>
                    </form>
                  <?php endif; ?>
                </div>
              <?php else: ?>
                <span style="font-size:.78rem;color:var(--text-secondary);">🔒 Protected</span>
              <?php endif; ?>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<!-- Mobile Cards -->
<div class="mobile-only">
  <?php foreach ($users as $u):
    $canAct = canModerate($u['role']);
  ?>
    <div class="user-mgmt-card" <?= !$canAct ? 'style="opacity:.55;"' : '' ?>>
      <div class="umc-header">
        <span class="umc-name"><?= e($u['username']) ?></span>
        <span class="badge <?= roleBadgeClass($u['role']) ?>"><?= roleDisplayName($u['role']) ?></span>
      </div>
      <div class="umc-body">
        <div class="umc-row"><span class="umc-label">Sub</span><span class="umc-value"><?= subBadge($u) ?></span></div>
        <div class="umc-row"><span class="umc-label">Expires</span><span class="umc-value" style="font-size:.82rem;"><?= e($u['sub_expires'] ?? '—') ?></span></div>
      </div>
      <?php if ($canAct): ?>
        <div class="umc-actions">
          <form method="POST"><input type="hidden" name="csrf_token" value="<?= e(generateCSRF()) ?>"><input type="hidden" name="action" value="change_role"><input type="hidden" name="target_user_id" value="<?= $u['user_id'] ?>">
            <select name="new_role"><?php foreach (getAssignableRoles() as $r): ?><option value="<?= $r ?>" <?= $u['role']===$r?'selected':'' ?>><?= roleDisplayName($r) ?></option><?php endforeach; ?></select>
            <button class="btn btn-secondary btn-sm">Set</button>
          </form>
          <form method="POST"><input type="hidden" name="csrf_token" value="<?= e(generateCSRF()) ?>"><input type="hidden" name="action" value="grant_sub"><input type="hidden" name="target_user_id" value="<?= $u['user_id'] ?>">
            <input type="number" name="days" value="<?= min(3, $maxSubDays) ?>" min="1" max="<?= $maxSubDays ?>">
            <button class="btn btn-primary btn-sm">+Sub</button>
          </form>
          <div class="umc-btn-row">
            <form method="POST"><input type="hidden" name="csrf_token" value="<?= e(generateCSRF()) ?>"><input type="hidden" name="target_user_id" value="<?= $u['user_id'] ?>">
              <?php if (($u['sub_status'] ?? '')==='banned'): ?><input type="hidden" name="action" value="unban"><button class="btn btn-secondary btn-sm">Unban</button>
              <?php else: ?><input type="hidden" name="action" value="ban"><button class="btn btn-danger btn-sm" onclick="return confirm('Ban?');">Ban</button><?php endif; ?>
            </form>
          </div>
        </div>
      <?php endif; ?>
    </div>
  <?php endforeach; ?>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>