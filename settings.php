<?php
/**
 * User Settings / Profile Page
 * --------------------------------------------------------
 * Access: Authenticated users only.
 *
 * Features:
 *   1. Read-only account overview (sub status, expiry, HWID).
 *   2. Change email  — validates format & uniqueness.
 *   3. Change password — verifies current, hashes new with
 *      PASSWORD_DEFAULT.
 *   4. Forum signature editor.
 *
 * Security:
 *   • CSRF on every form.
 *   • Current password required for password change.
 *   • All output escaped with e().
 */
$pageTitle = 'Account Settings';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';
requireLogin();

$user = currentUser($pdo);

/* Fetch latest subscription row */
$subStmt = $pdo->prepare(
    'SELECT * FROM `subscriptions`
     WHERE `user_id` = ?
     ORDER BY `expires_at` DESC
     LIMIT 1'
);
$subStmt->execute([$user['user_id']]);
$sub = $subStmt->fetch();

/* Flash messages per section */
$emailOk  = $emailErr  = '';
$passOk   = $passErr   = '';
$sigOk    = $sigErr    = '';

/* ---- Handle POST ---- */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    /* CSRF gate (applies to all three forms) */
    if (!validateCSRF($_POST['csrf_token'] ?? '')) {
        $emailErr = $passErr = $sigErr = 'Invalid session token. Please reload.';
    } else {

        $formType = $_POST['form_type'] ?? '';

        /* === CHANGE EMAIL === */
        if ($formType === 'email') {
            $newEmail = trim($_POST['new_email'] ?? '');

            if (!filter_var($newEmail, FILTER_VALIDATE_EMAIL)) {
                $emailErr = 'Please enter a valid email address.';
            } elseif ($newEmail === $user['email']) {
                $emailErr = 'That is already your current email.';
            } else {
                /* Uniqueness check — must not belong to another user */
                $chk = $pdo->prepare(
                    'SELECT `user_id` FROM `users` WHERE `email` = ? AND `user_id` != ?'
                );
                $chk->execute([$newEmail, $user['user_id']]);
                if ($chk->fetch()) {
                    $emailErr = 'That email is already in use by another account.';
                } else {
                    $pdo->prepare('UPDATE `users` SET `email` = ? WHERE `user_id` = ?')
                        ->execute([$newEmail, $user['user_id']]);
                    logAction($pdo, $user['user_id'], 'email_changed');
                    $emailOk = 'Email updated successfully.';
                    $user = currentUser($pdo);
                }
            }
        }

        /* === CHANGE PASSWORD === */
        elseif ($formType === 'password') {
            $curPass  = $_POST['current_password']  ?? '';
            $newPass  = $_POST['new_password']       ?? '';
            $confPass = $_POST['confirm_password']   ?? '';

            if (!password_verify($curPass, $user['password_hash'])) {
                $passErr = 'Current password is incorrect.';
            } elseif (strlen($newPass) < 8) {
                $passErr = 'New password must be at least 8 characters.';
            } elseif ($newPass !== $confPass) {
                $passErr = 'New passwords do not match.';
            } else {
                $hash = password_hash($newPass, PASSWORD_DEFAULT);
                $pdo->prepare('UPDATE `users` SET `password_hash` = ? WHERE `user_id` = ?')
                    ->execute([$hash, $user['user_id']]);
                logAction($pdo, $user['user_id'], 'password_changed');
                $passOk = 'Password updated successfully.';
            }
        }

        /* === FORUM SIGNATURE === */
        elseif ($formType === 'signature') {
            $sig = trim($_POST['signature'] ?? '');
            if (strlen($sig) > 500) {
                $sigErr = 'Signature must be 500 characters or fewer.';
            } else {
                $pdo->prepare('UPDATE `users` SET `signature` = ? WHERE `user_id` = ?')
                    ->execute([$sig === '' ? null : $sig, $user['user_id']]);
                $sigOk = 'Forum signature updated.';
                $user = currentUser($pdo);
            }
        }
    }
}

require_once __DIR__ . '/includes/header.php';
?>

<div class="flex-between mb-1">
  <h1>⚙️ Account Settings</h1>
  <a href="/dashboard.php" class="btn btn-secondary btn-sm">&larr; Dashboard</a>
</div>

<!-- ============ ACCOUNT OVERVIEW (read-only) ============ -->
<div class="card mb-2">
  <h3>📋 Account Overview</h3>
  <div class="grid-3" style="gap:1.5rem;">
    <div>
      <div style="color:var(--text-secondary);font-size:.8rem;text-transform:uppercase;letter-spacing:.5px;">Username</div>
      <div style="font-size:1.1rem;font-weight:600;"><?= e($user['username']) ?></div>
    </div>
    <div>
      <div style="color:var(--text-secondary);font-size:.8rem;text-transform:uppercase;letter-spacing:.5px;">Email</div>
      <div style="font-size:1.1rem;"><?= e($user['email']) ?></div>
    </div>
    <div>
      <div style="color:var(--text-secondary);font-size:.8rem;text-transform:uppercase;letter-spacing:.5px;">Role</div>
      <div>
        <span class="badge <?=
          $user['role'] === 'admin' ? 'badge-red' :
          ($user['role'] === 'moderator' ? 'badge-amber' : 'badge-blue')
        ?>"><?= e(ucfirst($user['role'])) ?></span>
      </div>
    </div>
    <div>
      <div style="color:var(--text-secondary);font-size:.8rem;text-transform:uppercase;letter-spacing:.5px;">Subscription</div>
      <?php if ($sub): ?>
        <?php
          $isActive = ($sub['status'] === 'active' && strtotime($sub['expires_at']) > time());
          $isBanned = ($sub['status'] === 'banned');
        ?>
        <span class="badge <?= $isBanned ? 'badge-red' : ($isActive ? 'badge-green' : 'badge-amber') ?>">
          <?= e(strtoupper($sub['status'])) ?>
        </span>
      <?php else: ?>
        <span style="color:var(--text-secondary);">None</span>
      <?php endif; ?>
    </div>
    <div>
      <div style="color:var(--text-secondary);font-size:.8rem;text-transform:uppercase;letter-spacing:.5px;">Subscription Expiry</div>
      <div><?= $sub ? e($sub['expires_at']) : '—' ?></div>
    </div>
    <div>
      <div style="color:var(--text-secondary);font-size:.8rem;text-transform:uppercase;letter-spacing:.5px;">Hardware ID</div>
      <div style="font-family:var(--font-mono);font-size:.82rem;color:var(--accent-purple);word-break:break-all;">
        <?= $user['hwid'] ? e($user['hwid']) : '<span style="color:var(--text-secondary);">Not bound</span>' ?>
      </div>
    </div>
  </div>
</div>

<div class="grid-3" style="grid-template-columns:1fr 1fr;gap:1.5rem;">

  <!-- ============ CHANGE EMAIL ============ -->
  <div class="card">
    <h3>✉️ Change Email</h3>
    <?php if ($emailOk): ?><div class="alert alert-success"><?= e($emailOk) ?></div><?php endif; ?>
    <?php if ($emailErr): ?><div class="alert alert-error"><?= e($emailErr) ?></div><?php endif; ?>

    <form method="POST" autocomplete="off">
      <input type="hidden" name="csrf_token" value="<?= e(generateCSRF()) ?>">
      <input type="hidden" name="form_type" value="email">

      <div class="form-group">
        <label for="current_email">Current Email</label>
        <input type="email" id="current_email" value="<?= e($user['email']) ?>" disabled
               style="opacity:.6;">
      </div>
      <div class="form-group">
        <label for="new_email">New Email</label>
        <input type="email" id="new_email" name="new_email" required
               placeholder="new@example.com">
      </div>
      <button type="submit" class="btn btn-primary" style="width:100%;">Update Email</button>
    </form>
  </div>

  <!-- ============ CHANGE PASSWORD ============ -->
  <div class="card">
    <h3>🔑 Change Password</h3>
    <?php if ($passOk): ?><div class="alert alert-success"><?= e($passOk) ?></div><?php endif; ?>
    <?php if ($passErr): ?><div class="alert alert-error"><?= e($passErr) ?></div><?php endif; ?>

    <form method="POST" autocomplete="off">
      <input type="hidden" name="csrf_token" value="<?= e(generateCSRF()) ?>">
      <input type="hidden" name="form_type" value="password">

      <div class="form-group">
        <label for="current_password">Current Password</label>
        <input type="password" id="current_password" name="current_password" required>
      </div>
      <div class="form-group">
        <label for="new_password">New Password (min 8 chars)</label>
        <input type="password" id="new_password" name="new_password" required minlength="8">
      </div>
      <div class="form-group">
        <label for="confirm_password">Confirm New Password</label>
        <input type="password" id="confirm_password" name="confirm_password" required>
      </div>
      <button type="submit" class="btn btn-primary" style="width:100%;">Update Password</button>
    </form>
  </div>
</div>

<!-- ============ FORUM SIGNATURE ============ -->
<div class="card mt-1">
  <h3>✍️ Forum Signature</h3>
  <p style="color:var(--text-secondary);font-size:.85rem;margin-bottom:.75rem;">
    Displayed below your posts in the forum. Max 500 characters. HTML is not allowed.
  </p>
  <?php if ($sigOk): ?><div class="alert alert-success"><?= e($sigOk) ?></div><?php endif; ?>
  <?php if ($sigErr): ?><div class="alert alert-error"><?= e($sigErr) ?></div><?php endif; ?>

  <form method="POST">
    <input type="hidden" name="csrf_token" value="<?= e(generateCSRF()) ?>">
    <input type="hidden" name="form_type" value="signature">

    <div class="form-group">
      <textarea name="signature" maxlength="500" rows="3"
                placeholder="Your forum signature…"><?= e($user['signature'] ?? '') ?></textarea>
    </div>
    <button type="submit" class="btn btn-primary">Save Signature</button>
  </form>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>