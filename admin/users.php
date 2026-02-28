<?php
/**
 * Admin Panel — User Management
 * --------------------------------------------------------
 * Access: admin only.
 *
 * Capabilities:
 *   • View all users with their subscription & role info.
 *   • Change user roles (promote/demote).
 *   • Grant or revoke subscriptions.
 *   • Ban / unban users.
 */
$pageTitle = 'Manage Users';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
requireRole('admin');

$flash = '';

/* ---- POST actions ---- */
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
                /* ---- CHANGE ROLE ---- */
                case 'change_role':
                    $newRole = $_POST['new_role'] ?? 'user';
                    if (!in_array($newRole, ['user','moderator','admin'])) {
                        $flash = '❌ Invalid role.';
                    } else {
                        $pdo->prepare('UPDATE `users` SET `role`=? WHERE `user_id`=?')
                            ->execute([$newRole, $targetId]);
                        logAction($pdo, $targetId, "role_changed_to:$newRole:by:" . $_SESSION['user_id']);
                        $flash = "✅ User #$targetId role set to $newRole.";
                    }
                    break;

                /* ---- GRANT SUBSCRIPTION (30 days) ---- */
                case 'grant_sub':
                    $days = max(1, (int)($_POST['days'] ?? 30));
                    /* Check for existing active sub */
                    $existing = $pdo->prepare(
                        "SELECT sub_id FROM `subscriptions`
                         WHERE user_id=? AND status='active' AND expires_at > NOW()
                         LIMIT 1"
                    );
                    $existing->execute([$targetId]);
                    if ($existing->fetch()) {
                        /* Extend */
                        $pdo->prepare(
                            "UPDATE `subscriptions`
                             SET `expires_at` = DATE_ADD(`expires_at`, INTERVAL ? DAY)
                             WHERE `user_id`=? AND `status`='active' AND `expires_at` > NOW()"
                        )->execute([$days, $targetId]);
                    } else {
                        $pdo->prepare(
                            "INSERT INTO `subscriptions` (`user_id`,`status`,`expires_at`)
                             VALUES (?, 'active', DATE_ADD(NOW(), INTERVAL ? DAY))"
                        )->execute([$targetId, $days]);
                    }
                    logAction($pdo, $targetId, "sub_granted:{$days}d:by:" . $_SESSION['user_id']);
                    $flash = "✅ Granted $days day(s) to user #$targetId.";
                    break;

                /* ---- REVOKE / EXPIRE SUBSCRIPTION ---- */
                case 'revoke_sub':
                    $pdo->prepare(
                        "UPDATE `subscriptions` SET `status`='expired', `expires_at`=NOW()
                         WHERE `user_id`=? AND `status`='active'"
                    )->execute([$targetId]);
                    logAction($pdo, $targetId, 'sub_revoked:by:' . $_SESSION['user_id']);
                    $flash = "✅ Subscription revoked for user #$targetId.";
                    break;

                /* ---- BAN ---- */
                case 'ban':
                    /* Set subscription status to banned */
                    $hasSub = $pdo->prepare('SELECT sub_id FROM subscriptions WHERE user_id=? LIMIT 1');
                    $hasSub->execute([$targetId]);
                    if ($hasSub->fetch()) {
                        $pdo->prepare("UPDATE `subscriptions` SET `status`='banned' WHERE `user_id`=?")
                            ->execute([$targetId]);
                    } else {
                        $pdo->prepare(
                            "INSERT INTO `subscriptions` (`user_id`,`status`,`expires_at`)
                             VALUES (?, 'banned', NOW())"
                        )->execute([$targetId]);
                    }
                    logAction($pdo, $targetId, 'ban:by:' . $_SESSION['user_id']);
                    $flash = "✅ User #$targetId has been banned.";
                    break;

                /* ---- UNBAN ---- */
                case 'unban':
                    $pdo->prepare("UPDATE `subscriptions` SET `status`='expired' WHERE `user_id`=? AND `status`='banned'")
                        ->execute([$targetId]);
                    logAction($pdo, $targetId, 'unban:by:' . $_SESSION['user_id']);
                    $flash = "✅ User #$targetId has been unbanned.";
                    break;
            }
        }
    }
}

/* ---- Fetch all users with their latest subscription ---- */
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

require_once __DIR__ . '/../includes/header.php';
?>

<div class="flex-between mb-1">
  <h1>👥 User Management</h1>
  <a href="/admin/index.php" class="btn btn-secondary btn-sm">&larr; Admin Home</a>
</div>

<?php if ($flash): ?>
  <div class="alert <?= str_starts_with($flash, '✅') ? 'alert-success' : 'alert-error' ?>">
    <?= e($flash) ?>
  </div>
<?php endif; ?>

<div class="card" style="overflow-x:auto;">
  <table>
    <thead>
      <tr>
        <th>ID</th>
        <th>Username</th>
        <th>Email</th>
        <th>Role</th>
        <th>Sub</th>
        <th>Expires</th>
        <th>HWID</th>
        <th>Joined</th>
        <th>Actions</th>
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
              $u['role'] === 'admin' ? 'badge-red' :
              ($u['role'] === 'moderator' ? 'badge-amber' : 'badge-blue')
            ?>"><?= e($u['role']) ?></span>
          </td>
          <td>
            <?php
              $ss = $u['sub_status'] ?? null;
              if ($ss === 'active' && strtotime($u['sub_expires']) > time()):
            ?>
              <span class="dot dot-green"></span>Active
            <?php elseif ($ss === 'banned'): ?>
              <span class="dot dot-red"></span>Banned
            <?php else: ?>
              <span style="color:var(--text-secondary);">—</span>
            <?php endif; ?>
          </td>
          <td style="font-size:.82rem;"><?= e($u['sub_expires'] ?? '—') ?></td>
          <td style="font-family:var(--font-mono);font-size:.72rem;max-width:90px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;"
              title="<?= e($u['hwid'] ?? '') ?>">
            <?= $u['hwid'] ? e(substr($u['hwid'], 0, 16)) . '…' : '—' ?>
          </td>
          <td style="font-size:.82rem;"><?= e($u['created_at']) ?></td>
          <td style="min-width:280px;">
            <!-- Role change -->
            <form method="POST" style="display:inline-flex;gap:.3rem;align-items:center;margin-bottom:.3rem;">
              <input type="hidden" name="csrf_token" value="<?= e(generateCSRF()) ?>">
              <input type="hidden" name="action" value="change_role">
              <input type="hidden" name="target_user_id" value="<?= $u['user_id'] ?>">
              <select name="new_role" style="padding:.2rem;font-size:.78rem;border-radius:4px;border:1px solid var(--border-color);background:var(--bg-secondary);color:var(--text-primary);">
                <option value="user"      <?= $u['role']==='user'?'selected':'' ?>>User</option>
                <option value="moderator"  <?= $u['role']==='moderator'?'selected':'' ?>>Mod</option>
                <option value="admin"      <?= $u['role']==='admin'?'selected':'' ?>>Admin</option>
              </select>
              <button type="submit" class="btn btn-secondary btn-sm">Set</button>
            </form>
            <br>
            <!-- Subscription grant -->
            <form method="POST" style="display:inline-flex;gap:.3rem;align-items:center;margin-bottom:.3rem;">
              <input type="hidden" name="csrf_token" value="<?= e(generateCSRF()) ?>">
              <input type="hidden" name="action" value="grant_sub">
              <input type="hidden" name="target_user_id" value="<?= $u['user_id'] ?>">
              <input type="number" name="days" value="30" min="1" max="365"
                     style="width:55px;padding:.2rem;font-size:.78rem;border-radius:4px;border:1px solid var(--border-color);background:var(--bg-secondary);color:var(--text-primary);">
              <button type="submit" class="btn btn-primary btn-sm">+Sub</button>
            </form>

            <!-- Revoke / Ban / Unban -->
            <form method="POST" style="display:inline;">
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

            <?php if (($u['sub_status'] ?? '') === 'active'): ?>
              <form method="POST" style="display:inline;">
                <input type="hidden" name="csrf_token" value="<?= e(generateCSRF()) ?>">
                <input type="hidden" name="action" value="revoke_sub">
                <input type="hidden" name="target_user_id" value="<?= $u['user_id'] ?>">
                <button type="submit" class="btn btn-danger btn-sm"
                        onclick="return confirm('Revoke subscription?');">Revoke</button>
              </form>
            <?php endif; ?>
          </td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>