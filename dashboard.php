<?php
/**
 * User Dashboard — Complete Responsive Version
 * --------------------------------------------------------
 * Access: Any authenticated user.
 *
 * Shows:
 *   • Subscription status card with badge + expiry
 *   • HWID binding status with reset link
 *   • Client download card
 *   • Full script library (desktop table + mobile cards)
 *   • Quick links to settings, HWID reset, forum
 *
 * Desktop: 3-column grid for top cards, full data table for scripts.
 * Mobile:  Single-column stacked cards, compact script list.
 */
$pageTitle = 'Dashboard';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';
requireLogin();

$user = currentUser($pdo);

/* Fetch latest subscription */
$subStmt = $pdo->prepare(
    'SELECT * FROM `subscriptions`
     WHERE `user_id` = ?
     ORDER BY `expires_at` DESC
     LIMIT 1'
);
$subStmt->execute([$user['user_id']]);
$sub = $subStmt->fetch();

/* Determine subscription state once for reuse */
$isActive = false;
$isBanned = false;
$isExpired = false;
if ($sub) {
    $isBanned = ($sub['status'] === 'banned');
    $isActive = ($sub['status'] === 'active' && strtotime($sub['expires_at']) > time());
    $isExpired = (!$isActive && !$isBanned);
}

/* Calculate remaining days if active */
$daysLeft = 0;
if ($isActive) {
    $daysLeft = max(0, (int)ceil((strtotime($sub['expires_at']) - time()) / 86400));
}

/* Fetch all scripts with author info */
$scripts = $pdo->query(
    'SELECT s.*, u.username AS author_name
     FROM `scripts` s
     LEFT JOIN `users` u ON u.user_id = s.author_id
     ORDER BY s.title ASC'
)->fetchAll();

/* Count script types */
$freeCount    = 0;
$premiumCount = 0;
foreach ($scripts as $s) {
    $s['is_premium'] ? $premiumCount++ : $freeCount++;
}

require_once __DIR__ . '/includes/header.php';
?>

<!-- Page heading with quick links -->
<div class="flex-between flex-between-mobile mb-1">
  <h1>👋 Welcome, <?= e($user['username']) ?></h1>
  <div style="display:flex;gap:.4rem;flex-wrap:wrap;">
    <a href="/settings.php" class="btn btn-secondary btn-sm">⚙️ Settings</a>
    <a href="/forum/index.php" class="btn btn-secondary btn-sm">💬 Forum</a>
  </div>
</div>

<!-- ============================================================
     TOP ROW: Subscription / HWID / Download (3-col → 1-col mobile)
     ============================================================ -->
<div class="grid-3">

  <!-- ===== SUBSCRIPTION CARD ===== -->
  <div class="card">
    <h3>📋 Subscription</h3>
    <?php if ($sub): ?>
      <p style="margin-bottom:.5rem;">
        Status:
        <?php if ($isBanned): ?>
          <span class="badge badge-red">BANNED</span>
        <?php elseif ($isActive): ?>
          <span class="badge badge-green">ACTIVE</span>
        <?php else: ?>
          <span class="badge badge-amber">EXPIRED</span>
        <?php endif; ?>
      </p>

      <?php if ($isActive): ?>
        <p style="color:var(--text-secondary);font-size:.88rem;">
          Expires: <strong style="color:var(--text-primary);"><?= e($sub['expires_at']) ?></strong>
        </p>
        <p style="color:var(--text-secondary);font-size:.82rem;margin-top:.3rem;">
          ⏳ <?= $daysLeft ?> day<?= $daysLeft !== 1 ? 's' : '' ?> remaining
        </p>
      <?php elseif ($isBanned): ?>
        <p style="color:var(--accent-red);font-size:.88rem;">
          Your account has been suspended. Contact support if you believe this is an error.
        </p>
      <?php else: ?>
        <p style="color:var(--text-secondary);font-size:.88rem;">
          Your subscription expired on <?= e($sub['expires_at']) ?>.
        </p>
        <a href="/index.php#pricing" class="btn btn-primary btn-sm mt-1">Renew Now</a>
      <?php endif; ?>
    <?php else: ?>
      <p style="color:var(--text-secondary);margin-bottom:.75rem;">
        You don't have an active subscription yet.
      </p>
      <a href="/index.php#pricing" class="btn btn-primary btn-sm">View Plans</a>
    <?php endif; ?>
  </div>

  <!-- ===== HWID CARD ===== -->
  <div class="card">
    <h3>🖥️ Hardware ID</h3>
    <?php if ($user['hwid']): ?>
      <p style="color:var(--text-secondary);font-size:.82rem;margin-bottom:.4rem;">
        Your client is bound to:
      </p>
      <p style="font-family:var(--font-mono);font-size:.78rem;color:var(--accent-purple);
                word-break:break-all;background:var(--bg-secondary);
                padding:.5rem .75rem;border-radius:var(--radius);
                border:1px solid var(--border-color);">
        <?= e($user['hwid']) ?>
      </p>

      <?php
        /* Show cooldown status */
        $canReset  = true;
        $nextReset = null;
        if ($user['hwid_updated_at']) {
            $cooldownEnd = strtotime($user['hwid_updated_at']) + (7 * 86400);
            if (time() < $cooldownEnd) {
                $canReset  = false;
                $nextReset = date('M j, Y', $cooldownEnd);
            }
        }
      ?>
      <div class="mt-1">
        <?php if ($canReset): ?>
          <a href="/hwid_reset.php" class="btn btn-secondary btn-sm">🔄 Reset HWID</a>
        <?php else: ?>
          <p style="font-size:.78rem;color:var(--accent-amber);">
            🔒 Reset available <?= e($nextReset) ?>
          </p>
          <a href="/hwid_reset.php" class="btn btn-secondary btn-sm mt-1" style="opacity:.6;">
            View Reset Page
          </a>
        <?php endif; ?>
      </div>
    <?php else: ?>
      <p style="color:var(--text-secondary);">
        <span class="dot dot-amber"></span>Not bound yet
      </p>
      <p style="color:var(--text-secondary);font-size:.82rem;margin-top:.4rem;">
        Launch the desktop client to automatically bind your Hardware ID to this account.
      </p>
    <?php endif; ?>
  </div>

  <!-- ===== DOWNLOAD CARD ===== -->
  <div class="card text-center">
    <h3>⬇️ Client Download</h3>
    <p style="color:var(--text-secondary);font-size:.88rem;margin-bottom:.75rem;">
      Latest stable build
    </p>

    <div style="background:var(--bg-secondary);border:1px solid var(--border-color);
                border-radius:var(--radius);padding:.75rem;margin-bottom:1rem;">
      <div style="font-family:var(--font-mono);font-size:1.1rem;font-weight:700;
                  color:var(--accent-green);">
        v2.4.1
      </div>
      <div style="font-size:.75rem;color:var(--text-secondary);margin-top:.2rem;">
        Released: <?= date('M j, Y') ?>
      </div>
    </div>

    <?php if ($isActive): ?>
      <a href="#" class="btn btn-primary w-full"
         onclick="alert('Download link placeholder'); return false;">
        Download Client
      </a>
    <?php else: ?>
      <button class="btn btn-secondary w-full" disabled
              style="opacity:.5;cursor:not-allowed;"
              title="Active subscription required">
        Subscription Required
      </button>
      <p style="font-size:.75rem;color:var(--text-secondary);margin-top:.4rem;">
        An active subscription is needed to download.
      </p>
    <?php endif; ?>
  </div>
</div>

<!-- ============================================================
     ACCOUNT QUICK STATS (small info row)
     ============================================================ -->
<div class="card" style="padding:1rem 1.25rem;">
  <div style="display:flex;gap:1.5rem;flex-wrap:wrap;justify-content:flex-start;align-items:center;">
    <div>
      <span style="font-size:.75rem;color:var(--text-secondary);text-transform:uppercase;letter-spacing:.3px;">
        Account
      </span>
      <div style="font-weight:600;"><?= e($user['username']) ?></div>
    </div>
    <div>
      <span style="font-size:.75rem;color:var(--text-secondary);text-transform:uppercase;letter-spacing:.3px;">
        Role
      </span>
      <div>
        <span class="badge <?=
          $user['role']==='admin' ? 'badge-red' :
          ($user['role']==='moderator' ? 'badge-amber' : 'badge-blue')
        ?>"><?= e(ucfirst($user['role'])) ?></span>
      </div>
    </div>
    <div>
      <span style="font-size:.75rem;color:var(--text-secondary);text-transform:uppercase;letter-spacing:.3px;">
        Member Since
      </span>
      <div style="font-size:.9rem;"><?= e(date('M j, Y', strtotime($user['created_at']))) ?></div>
    </div>
    <div>
      <span style="font-size:.75rem;color:var(--text-secondary);text-transform:uppercase;letter-spacing:.3px;">
        Forum Posts
      </span>
      <div style="font-size:.9rem;font-family:var(--font-mono);">
        <?= number_format($user['post_count'] ?? 0) ?>
      </div>
    </div>
  </div>
</div>

<!-- ============================================================
     SCRIPT LIBRARY
     Desktop: full table  |  Mobile: compact card list
     ============================================================ -->
<div class="card">
  <div class="flex-between flex-between-mobile mb-1">
    <h3>📜 Script Library</h3>
    <div style="display:flex;gap:.4rem;flex-wrap:wrap;">
      <span class="badge badge-blue"><?= count($scripts) ?> total</span>
      <span class="badge badge-green"><?= $freeCount ?> free</span>
      <span class="badge badge-purple"><?= $premiumCount ?> premium</span>
    </div>
  </div>

  <?php if ($scripts): ?>

    <!-- ===== DESKTOP TABLE ===== -->
    <div class="desktop-only">
      <div class="table-wrap">
        <table>
          <thead>
            <tr>
              <th>Title</th>
              <th>Category</th>
              <th>Version</th>
              <th>Type</th>
              <th>Author</th>
              <th>Access</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($scripts as $s): ?>
              <tr>
                <td>
                  <strong><?= e($s['title']) ?></strong>
                  <?php if ($s['description']): ?>
                    <div style="font-size:.78rem;color:var(--text-secondary);margin-top:2px;
                                max-width:300px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">
                      <?= e($s['description']) ?>
                    </div>
                  <?php endif; ?>
                </td>
                <td>
                  <span class="badge badge-blue"><?= e($s['category']) ?></span>
                </td>
                <td>
                  <span style="font-family:var(--font-mono);font-size:.85rem;">
                    v<?= e($s['version']) ?>
                  </span>
                </td>
                <td>
                  <?php if ($s['is_premium']): ?>
                    <span class="badge badge-purple">Premium</span>
                  <?php else: ?>
                    <span class="badge badge-green">Free</span>
                  <?php endif; ?>
                </td>
                <td style="font-size:.85rem;">
                  <?= e($s['author_name'] ?? 'System') ?>
                </td>
                <td>
                  <?php if (!$s['is_premium'] || $isActive): ?>
                    <span class="dot dot-green"></span>
                    <span style="font-size:.82rem;color:var(--accent-green);">Available</span>
                  <?php else: ?>
                    <span class="dot dot-amber"></span>
                    <span style="font-size:.82rem;color:var(--accent-amber);">Sub Required</span>
                  <?php endif; ?>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>

    <!-- ===== MOBILE CARD LIST ===== -->
    <div class="mobile-only">
      <?php foreach ($scripts as $s): ?>
        <div style="padding:.75rem 0;border-bottom:1px solid var(--border-color);">
          <!-- Title row -->
          <div style="display:flex;justify-content:space-between;align-items:flex-start;gap:.5rem;">
            <div style="min-width:0;flex:1;">
              <strong style="font-size:.92rem;"><?= e($s['title']) ?></strong>
              <?php if ($s['description']): ?>
                <div style="font-size:.76rem;color:var(--text-secondary);margin-top:.15rem;
                            display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;
                            overflow:hidden;">
                  <?= e($s['description']) ?>
                </div>
              <?php endif; ?>
            </div>
            <div style="flex-shrink:0;">
              <?php if ($s['is_premium']): ?>
                <span class="badge badge-purple">Premium</span>
              <?php else: ?>
                <span class="badge badge-green">Free</span>
              <?php endif; ?>
            </div>
          </div>

          <!-- Meta row -->
          <div style="display:flex;gap:.6rem;flex-wrap:wrap;margin-top:.4rem;
                      font-size:.76rem;color:var(--text-secondary);align-items:center;">
            <span class="badge badge-blue" style="font-size:.65rem;">
              <?= e($s['category']) ?>
            </span>
            <span style="font-family:var(--font-mono);">v<?= e($s['version']) ?></span>
            <span>by <?= e($s['author_name'] ?? 'System') ?></span>
            <span>
              <?php if (!$s['is_premium'] || $isActive): ?>
                <span class="dot dot-green"></span>Available
              <?php else: ?>
                <span class="dot dot-amber"></span>Sub Required
              <?php endif; ?>
            </span>
          </div>
        </div>
      <?php endforeach; ?>
    </div>

  <?php else: ?>
    <div class="text-center" style="padding:2rem 1rem;">
      <p style="color:var(--text-secondary);font-size:1.1rem;margin-bottom:.5rem;">
        📭 No scripts available yet
      </p>
      <p style="color:var(--text-secondary);font-size:.85rem;">
        Check back soon — our development team is always adding new automation scripts.
      </p>
    </div>
  <?php endif; ?>
</div>

<!-- ============================================================
     QUICK HELP / LINKS
     ============================================================ -->
<div class="card" style="padding:1rem 1.25rem;">
  <h3 style="font-size:.95rem;">🔗 Quick Links</h3>
  <div style="display:flex;gap:.75rem;flex-wrap:wrap;margin-top:.5rem;">
    <a href="/settings.php" class="btn btn-secondary btn-sm">⚙️ Account Settings</a>
    <a href="/hwid_reset.php" class="btn btn-secondary btn-sm">🖥️ HWID Reset</a>
    <a href="/forum/index.php" class="btn btn-secondary btn-sm">💬 Community Forum</a>
    <a href="/index.php#pricing" class="btn btn-secondary btn-sm">💰 Pricing</a>
  </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>