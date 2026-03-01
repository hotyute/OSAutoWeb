<?php
$pageTitle = 'Account Settings';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';
requireLogin();

$user = currentUser($pdo);

$subStmt = $pdo->prepare('SELECT * FROM `subscriptions` WHERE `user_id`=? ORDER BY `expires_at` DESC LIMIT 1');
$subStmt->execute([$user['user_id']]);
$sub = $subStmt->fetch();

$emailOk = $emailErr = '';
$passOk  = $passErr  = '';
$sigOk   = $sigErr   = '';
$avOk    = $avErr    = '';
$profOk  = $profErr  = '';

$avatarsEnabled = settingEnabled($pdo, 'forum_avatars');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateCSRF($_POST['csrf_token'] ?? '')) {
        $emailErr = $passErr = $sigErr = $avErr = $profErr = 'Invalid session token.';
    } else {
        $formType = $_POST['form_type'] ?? '';

        /* Email */
        if ($formType === 'email') {
            $newEmail = trim($_POST['new_email'] ?? '');
            if (!filter_var($newEmail, FILTER_VALIDATE_EMAIL)) {
                $emailErr = 'Please enter a valid email.';
            } elseif ($newEmail === $user['email']) {
                $emailErr = 'That is already your email.';
            } else {
                $chk = $pdo->prepare('SELECT user_id FROM users WHERE email=? AND user_id!=?');
                $chk->execute([$newEmail, $user['user_id']]);
                if ($chk->fetch()) {
                    $emailErr = 'Email already in use.';
                } else {
                    $pdo->prepare('UPDATE users SET email=? WHERE user_id=?')
                        ->execute([$newEmail, $user['user_id']]);
                    logAction($pdo, $user['user_id'], 'email_changed');
                    $emailOk = 'Email updated.';
                    $user = currentUser($pdo);
                }
            }
        }

        /* Password */
        elseif ($formType === 'password') {
            $curPass  = $_POST['current_password'] ?? '';
            $newPass  = $_POST['new_password'] ?? '';
            $confPass = $_POST['confirm_password'] ?? '';
            if (!password_verify($curPass, $user['password_hash'])) {
                $passErr = 'Current password incorrect.';
            } elseif (strlen($newPass) < 8) {
                $passErr = 'Min 8 characters.';
            } elseif ($newPass !== $confPass) {
                $passErr = 'Passwords don\'t match.';
            } else {
                $pdo->prepare('UPDATE users SET password_hash=? WHERE user_id=?')
                    ->execute([password_hash($newPass, PASSWORD_DEFAULT), $user['user_id']]);
                logAction($pdo, $user['user_id'], 'password_changed');
                $passOk = 'Password updated.';
            }
        }

        /* Signature */
        elseif ($formType === 'signature') {
            $sig = trim($_POST['signature'] ?? '');
            if (strlen($sig) > 500) {
                $sigErr = 'Max 500 characters.';
            } else {
                $pdo->prepare('UPDATE users SET signature=? WHERE user_id=?')
                    ->execute([$sig === '' ? null : $sig, $user['user_id']]);
                $sigOk = 'Signature updated.';
                $user = currentUser($pdo);
            }
        }

        /* Avatar upload */
        elseif ($formType === 'avatar' && $avatarsEnabled) {
            if (isset($_POST['remove_avatar'])) {
                /* Remove current avatar */
                if ($user['avatar_path'] && file_exists(__DIR__ . '/' . $user['avatar_path'])) {
                    @unlink(__DIR__ . '/' . $user['avatar_path']);
                }
                $pdo->prepare('UPDATE users SET avatar_path=NULL WHERE user_id=?')
                    ->execute([$user['user_id']]);
                $avOk = 'Avatar removed.';
                $user = currentUser($pdo);
            } elseif (isset($_FILES['avatar']) && $_FILES['avatar']['error'] !== UPLOAD_ERR_NO_FILE) {
                $uploadErr = '';
                $path = processAvatarUpload($pdo, $_FILES['avatar'], $user['user_id'], $uploadErr);
                if ($path) {
                    $avOk = 'Avatar uploaded!';
                    $user = currentUser($pdo);
                } else {
                    $avErr = $uploadErr;
                }
            } else {
                $avErr = 'No file selected.';
            }
        }

        /* Profile fields */
        elseif ($formType === 'profile') {
            $bio = trim($_POST['bio'] ?? '');
            $discord = trim($_POST['discord_tag'] ?? '');
            $displayRole = trim($_POST['display_role'] ?? '');

            if (strlen($bio) > 1000) {
                $profErr = 'Bio max 1000 chars.';
            } elseif (strlen($displayRole) > 60) {
                $profErr = 'Custom title max 60 chars.';
            } else {
                $pdo->prepare('UPDATE users SET bio=?, discord_tag=?, display_role=? WHERE user_id=?')
                    ->execute([
                        $bio === '' ? null : $bio,
                        $discord === '' ? null : $discord,
                        $displayRole === '' ? null : $displayRole,
                        $user['user_id']
                    ]);
                $profOk = 'Profile updated.';
                $user = currentUser($pdo);
            }
        }
    }
}

require_once __DIR__ . '/includes/header.php';
?>

<div class="flex-between flex-between-mobile mb-1">
  <h1>⚙️ Account Settings</h1>
  <a href="/dashboard.php" class="btn btn-secondary btn-sm">&larr; Dashboard</a>
</div>

<!-- Account Overview -->
<div class="card mb-2">
  <h3>📋 Account Overview</h3>
  <div class="grid-3">
    <div>
      <div style="color:var(--text-secondary);font-size:.78rem;text-transform:uppercase;">Username</div>
      <div style="font-size:1.05rem;font-weight:600;"><?= e($user['username']) ?></div>
    </div>
    <div>
      <div style="color:var(--text-secondary);font-size:.78rem;text-transform:uppercase;">Email</div>
      <div style="font-size:.92rem;word-break:break-all;"><?= e($user['email']) ?></div>
    </div>
    <div>
      <div style="color:var(--text-secondary);font-size:.78rem;text-transform:uppercase;">Role</div>
      <span class="badge <?= $user['role']==='admin'?'badge-red':($user['role']==='moderator'?'badge-amber':'badge-blue') ?>">
        <?= e(ucfirst($user['role'])) ?>
      </span>
    </div>
    <div>
      <div style="color:var(--text-secondary);font-size:.78rem;text-transform:uppercase;">Subscription</div>
      <?php if ($sub): ?>
        <?php
          $isActive = ($sub['status']==='active' && strtotime($sub['expires_at'])>time());
          $isBanned = ($sub['status']==='banned');
        ?>
        <span class="badge <?= $isBanned?'badge-red':($isActive?'badge-green':'badge-amber') ?>">
          <?= e(strtoupper($sub['status'])) ?>
        </span>
      <?php else: ?><span style="color:var(--text-secondary);">None</span><?php endif; ?>
    </div>
    <div>
      <div style="color:var(--text-secondary);font-size:.78rem;text-transform:uppercase;">Expires</div>
      <div style="font-size:.9rem;"><?= $sub ? e($sub['expires_at']) : '—' ?></div>
    </div>
    <div>
      <div style="color:var(--text-secondary);font-size:.78rem;text-transform:uppercase;">HWID</div>
      <div style="font-family:var(--font-mono);font-size:.75rem;color:var(--accent-purple);word-break:break-all;">
        <?= $user['hwid'] ? e($user['hwid']) : '<span style="color:var(--text-secondary);">Not bound</span>' ?>
      </div>
    </div>
  </div>
</div>

<!-- Avatar + Profile (side by side) -->
<div class="grid-2">
  <?php if ($avatarsEnabled): ?>
  <!-- Avatar Card -->
  <div class="card">
    <h3>🖼️ Profile Picture</h3>
    <?php if ($avOk): ?><div class="alert alert-success"><?= e($avOk) ?></div><?php endif; ?>
    <?php if ($avErr): ?><div class="alert alert-error"><?= e($avErr) ?></div><?php endif; ?>

    <div style="display:flex;gap:1rem;align-items:center;margin-bottom:1rem;">
      <?php
        $avUrl = getAvatarUrl($pdo, $user['avatar_path'], $user['username']);
      ?>
      <?php if ($avUrl): ?>
        <img src="<?= e($avUrl) ?>?t=<?= time() ?>"
             alt="Avatar"
             style="width:80px;height:80px;border-radius:50%;object-fit:cover;border:3px solid var(--accent-green);">
      <?php else: ?>
        <div style="width:80px;height:80px;border-radius:50%;background:var(--bg-secondary);
                    border:3px solid var(--border-color);display:flex;align-items:center;
                    justify-content:center;font-size:2rem;color:var(--accent-green);flex-shrink:0;">
          <?= strtoupper(mb_substr($user['username'], 0, 1)) ?>
        </div>
      <?php endif; ?>
      <div style="font-size:.82rem;color:var(--text-secondary);">
        Max <?= e(getSetting($pdo, 'avatar_max_size_kb', '2048')) ?>KB<br>
        JPG, PNG, GIF, WebP
      </div>
    </div>

    <form method="POST" enctype="multipart/form-data">
      <input type="hidden" name="csrf_token" value="<?= e(generateCSRF()) ?>">
      <input type="hidden" name="form_type" value="avatar">
      <div class="form-group">
        <input type="file" name="avatar" accept="image/jpeg,image/png,image/gif,image/webp"
               style="font-size:.85rem;">
      </div>
      <div style="display:flex;gap:.4rem;flex-wrap:wrap;">
        <button type="submit" class="btn btn-primary btn-sm">Upload Avatar</button>
        <?php if ($user['avatar_path']): ?>
          <button type="submit" name="remove_avatar" value="1" class="btn btn-danger btn-sm"
                  onclick="return confirm('Remove your avatar?');">Remove</button>
        <?php endif; ?>
      </div>
    </form>
  </div>
  <?php endif; ?>

  <!-- Profile Info -->
  <div class="card">
    <h3>👤 Profile Info</h3>
    <?php if ($profOk): ?><div class="alert alert-success"><?= e($profOk) ?></div><?php endif; ?>
    <?php if ($profErr): ?><div class="alert alert-error"><?= e($profErr) ?></div><?php endif; ?>
    <form method="POST" autocomplete="off">
      <input type="hidden" name="csrf_token" value="<?= e(generateCSRF()) ?>">
      <input type="hidden" name="form_type" value="profile">
      <div class="form-group">
        <label>Bio (max 1000 chars)</label>
        <textarea name="bio" rows="3" maxlength="1000"><?= e($user['bio'] ?? '') ?></textarea>
      </div>
      <div class="form-group">
        <label>Discord Tag</label>
        <input type="text" name="discord_tag" maxlength="60" placeholder="username#0000"
               value="<?= e($user['discord_tag'] ?? '') ?>">
      </div>
      <div class="form-group">
        <label>Custom Title (displayed under username on posts)</label>
        <input type="text" name="display_role" maxlength="60" placeholder="e.g. Script Developer"
               value="<?= e($user['display_role'] ?? '') ?>">
      </div>
      <button type="submit" class="btn btn-primary">Save Profile</button>
    </form>
  </div>
</div>

<!-- Email & Password -->
<div class="grid-2">
  <div class="card">
    <h3>✉️ Change Email</h3>
    <?php if ($emailOk): ?><div class="alert alert-success"><?= e($emailOk) ?></div><?php endif; ?>
    <?php if ($emailErr): ?><div class="alert alert-error"><?= e($emailErr) ?></div><?php endif; ?>
    <form method="POST" autocomplete="off">
      <input type="hidden" name="csrf_token" value="<?= e(generateCSRF()) ?>">
      <input type="hidden" name="form_type" value="email">
      <div class="form-group">
        <label>Current Email</label>
        <input type="email" value="<?= e($user['email']) ?>" disabled style="opacity:.6;">
      </div>
      <div class="form-group">
        <label for="new_email">New Email</label>
        <input type="email" id="new_email" name="new_email" required placeholder="new@example.com">
      </div>
      <button type="submit" class="btn btn-primary w-full">Update Email</button>
    </form>
  </div>

  <div class="card">
    <h3>🔑 Change Password</h3>
    <?php if ($passOk): ?><div class="alert alert-success"><?= e($passOk) ?></div><?php endif; ?>
    <?php if ($passErr): ?><div class="alert alert-error"><?= e($passErr) ?></div><?php endif; ?>
    <form method="POST" autocomplete="off">
      <input type="hidden" name="csrf_token" value="<?= e(generateCSRF()) ?>">
      <input type="hidden" name="form_type" value="password">
      <div class="form-group">
        <label>Current Password</label>
        <input type="password" name="current_password" required>
      </div>
      <div class="form-group">
        <label>New Password (min 8)</label>
        <input type="password" name="new_password" required minlength="8">
      </div>
      <div class="form-group">
        <label>Confirm New Password</label>
        <input type="password" name="confirm_password" required>
      </div>
      <button type="submit" class="btn btn-primary w-full">Update Password</button>
    </form>
  </div>
</div>

<!-- Signature -->
<?php if (settingEnabled($pdo, 'forum_signatures')): ?>
<div class="card mt-1">
  <h3>✍️ Forum Signature</h3>
  <?php if ($sigOk): ?><div class="alert alert-success"><?= e($sigOk) ?></div><?php endif; ?>
  <?php if ($sigErr): ?><div class="alert alert-error"><?= e($sigErr) ?></div><?php endif; ?>
  <form method="POST">
    <input type="hidden" name="csrf_token" value="<?= e(generateCSRF()) ?>">
    <input type="hidden" name="form_type" value="signature">
    <div class="form-group">
      <textarea name="signature" maxlength="500" rows="3"
                placeholder="Displayed below forum posts…"><?= e($user['signature'] ?? '') ?></textarea>
    </div>
    <button type="submit" class="btn btn-primary">Save Signature</button>
  </form>
</div>
<?php endif; ?>

<?php require_once __DIR__ . '/includes/footer.php'; ?>