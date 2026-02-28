<?php
/**
 * HWID Reset — Responsive
 * Uses .form-card for centering, responsive max-width.
 */
$pageTitle = 'HWID Reset';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';
requireLogin();

$user    = currentUser($pdo);
$error   = '';
$success = '';

$canReset  = true;
$nextReset = null;
if ($user['hwid_updated_at']) {
    $lastReset   = strtotime($user['hwid_updated_at']);
    $cooldownEnd = $lastReset + (7 * 86400);
    if (time() < $cooldownEnd) {
        $canReset  = false;
        $nextReset = date('Y-m-d H:i:s', $cooldownEnd);
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateCSRF($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid session token.';
    } elseif (!$canReset) {
        $error = 'You must wait until ' . e($nextReset) . ' before resetting again.';
    } else {
        $stmt = $pdo->prepare(
            'UPDATE `users` SET `hwid` = NULL, `hwid_updated_at` = NOW() WHERE `user_id` = ?'
        );
        $stmt->execute([$user['user_id']]);
        logAction($pdo, $user['user_id'], 'hwid_reset');
        $success   = 'HWID has been cleared. Launch the client to bind a new one.';
        $user      = currentUser($pdo);
        $canReset  = false;
        $nextReset = date('Y-m-d H:i:s', time() + 7 * 86400);
    }
}

require_once __DIR__ . '/includes/header.php';
?>

<div style="max-width:520px;margin:2rem auto;">
  <div class="card">
    <h2>🖥️ Hardware ID Reset</h2>

    <?php if ($success): ?>
      <div class="alert alert-success"><?= e($success) ?></div>
    <?php endif; ?>
    <?php if ($error): ?>
      <div class="alert alert-error"><?= e($error) ?></div>
    <?php endif; ?>

    <p class="mb-1" style="color:var(--text-secondary);">
      Current HWID:
      <strong style="font-family:var(--font-mono);color:var(--accent-purple);word-break:break-all;">
        <?= $user['hwid'] ? e($user['hwid']) : '(not bound)' ?>
      </strong>
    </p>

    <?php if ($canReset): ?>
      <form method="POST">
        <input type="hidden" name="csrf_token" value="<?= e(generateCSRF()) ?>">
        <p style="color:var(--text-secondary);font-size:.88rem;" class="mb-1">
          ⚠️ You may only reset once every 7 days. This action is logged.
        </p>
        <button type="submit" class="btn btn-danger w-full">Reset My HWID</button>
      </form>
    <?php else: ?>
      <div class="alert alert-warn">
        ⏳ Cooldown active. Next reset available: <strong><?= e($nextReset) ?></strong>
      </div>
    <?php endif; ?>

    <p class="mt-1"><a href="/dashboard.php">&larr; Back to Dashboard</a></p>
  </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>