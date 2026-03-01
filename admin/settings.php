<?php
/**
 * Admin — Site Settings
 * --------------------------------------------------------
 * Toggle-based interface for all site_settings rows.
 * Boolean settings render as toggle switches.
 * Numeric/text settings render as input fields.
 */
$pageTitle = 'Site Settings';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
requireRole('admin');

$flash = '';

/* Handle POST — bulk update all settings */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateCSRF($_POST['csrf_token'] ?? '')) {
        $flash = '❌ CSRF validation failed.';
    } else {
        $settings = getAllSettings($pdo);
        foreach ($settings as $s) {
            $key = $s['setting_key'];
            /*
             * Boolean settings: checkbox present = '1', absent = '0'.
             * Text/numeric settings: read from input.
             */
            if (in_array($s['setting_value'], ['0', '1']) && !in_array($key, ['avatar_max_size_kb', 'online_threshold_min'])) {
                $newVal = isset($_POST['setting'][$key]) ? '1' : '0';
            } else {
                $newVal = trim($_POST['setting'][$key] ?? $s['setting_value']);
            }

            if ($newVal !== $s['setting_value']) {
                $pdo->prepare('UPDATE `site_settings` SET `setting_value` = ? WHERE `setting_key` = ?')
                    ->execute([$newVal, $key]);
            }
        }
        logAction($pdo, $_SESSION['user_id'], 'admin:settings_updated');
        $flash = '✅ Settings saved successfully.';
    }
}

$settings = getAllSettings($pdo);

/* Group by category */
$grouped = [];
foreach ($settings as $s) {
    $grouped[$s['category']][] = $s;
}

$categoryLabels = [
    'forum'   => '💬 Forum Features',
    'landing' => '🏠 Landing Page',
    'limits'  => '📏 Limits & Thresholds',
    'general' => '⚙️ General',
];

require_once __DIR__ . '/../includes/header.php';
?>

<style>
.toggle-wrap{display:flex;align-items:center;justify-content:space-between;padding:.75rem 0;border-bottom:1px solid var(--border-color);}
.toggle-wrap:last-child{border-bottom:none;}
.toggle-label{font-size:.92rem;}
.toggle-desc{font-size:.78rem;color:var(--text-secondary);margin-top:.1rem;}
.toggle-switch{position:relative;width:48px;height:26px;flex-shrink:0;}
.toggle-switch input{opacity:0;width:0;height:0;}
.toggle-slider{position:absolute;top:0;left:0;right:0;bottom:0;background:var(--border-color);border-radius:13px;cursor:pointer;transition:background .3s;}
.toggle-slider::before{content:'';position:absolute;width:20px;height:20px;border-radius:50%;background:#fff;top:3px;left:3px;transition:transform .3s;}
.toggle-switch input:checked+.toggle-slider{background:var(--accent-green);}
.toggle-switch input:checked+.toggle-slider::before{transform:translateX(22px);}
.setting-input{display:flex;align-items:center;gap:.5rem;flex-wrap:wrap;}
.setting-input input{max-width:120px;padding:.4rem .6rem;border-radius:var(--radius);border:1px solid var(--border-color);background:var(--bg-secondary);color:var(--text-primary);font-size:.88rem;}
</style>

<div class="flex-between flex-between-mobile mb-1">
  <h1>⚙️ Site Settings</h1>
  <a href="/admin/index.php" class="btn btn-secondary btn-sm">&larr; Admin Home</a>
</div>

<?php if ($flash): ?>
  <div class="alert <?= str_starts_with($flash, '✅') ? 'alert-success' : 'alert-error' ?>">
    <?= e($flash) ?>
  </div>
<?php endif; ?>

<form method="POST">
  <input type="hidden" name="csrf_token" value="<?= e(generateCSRF()) ?>">

  <?php foreach ($grouped as $cat => $items): ?>
    <div class="card">
      <h3><?= $categoryLabels[$cat] ?? e(ucfirst($cat)) ?></h3>

      <?php foreach ($items as $s):
        $key  = $s['setting_key'];
        $val  = $s['setting_value'];
        $isBool = in_array($val, ['0','1']) && !in_array($key, ['avatar_max_size_kb','online_threshold_min']);
      ?>
        <div class="toggle-wrap">
          <div>
            <div class="toggle-label"><?= e($s['label'] ?? $key) ?></div>
            <div class="toggle-desc">
              <code style="background:var(--bg-secondary);padding:1px 5px;border-radius:3px;font-size:.72rem;">
                <?= e($key) ?>
              </code>
            </div>
          </div>

          <?php if ($isBool): ?>
            <label class="toggle-switch">
              <input type="checkbox" name="setting[<?= e($key) ?>]" value="1"
                     <?= $val === '1' ? 'checked' : '' ?>>
              <span class="toggle-slider"></span>
            </label>
          <?php else: ?>
            <div class="setting-input">
              <input type="text" name="setting[<?= e($key) ?>]"
                     value="<?= e($val) ?>">
            </div>
          <?php endif; ?>
        </div>
      <?php endforeach; ?>
    </div>
  <?php endforeach; ?>

  <div style="position:sticky;bottom:0;background:var(--bg-primary);padding:1rem 0;border-top:1px solid var(--border-color);">
    <button type="submit" class="btn btn-primary" style="width:100%;max-width:300px;">
      💾 Save All Settings
    </button>
  </div>
</form>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>