<?php
$pageTitle = 'Forum Infractions';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/forum_helpers.php';
requireLogin();
if (!canIssuePunishments()) { http_response_code(403); exit('Forbidden'); }

/* Fetch active punishments */
$active = $pdo->query(
    'SELECT fp.*, u.username, u.role AS user_role, iu.username AS issuer_name
     FROM forum_punishments fp
     JOIN users u ON u.user_id = fp.user_id
     JOIN users iu ON iu.user_id = fp.issued_by
     WHERE fp.is_active = 1 AND (fp.expires_at IS NULL OR fp.expires_at > NOW())
     ORDER BY fp.created_at DESC'
)->fetchAll();

/* Fetch recent history */
$history = $pdo->query(
    'SELECT fp.*, u.username, iu.username AS issuer_name, ru.username AS revoker_name
     FROM forum_punishments fp
     JOIN users u ON u.user_id = fp.user_id
     JOIN users iu ON iu.user_id = fp.issued_by
     LEFT JOIN users ru ON ru.user_id = fp.revoked_by
     ORDER BY fp.created_at DESC LIMIT 100'
)->fetchAll();

$csrf = generateCSRF();

require_once __DIR__ . '/../includes/header.php';
forumCSS();
?>

<style>
.pun-type{font-weight:700;text-transform:uppercase;font-size:.72rem;}
.pun-warn{color:var(--accent-amber);}
.pun-mute{color:var(--accent-purple);}
.pun-forum_ban{color:var(--accent-red);}
</style>

<div class="flex-between flex-between-mobile mb-1">
  <h1>⚖️ Forum Infractions</h1>
  <a href="/forum/index.php" class="btn btn-secondary btn-sm">&larr; Forum</a>
</div>

<!-- Active Punishments -->
<div class="card">
  <h3>🔴 Active Punishments (<?= count($active) ?>)</h3>

  <?php if (empty($active)): ?>
    <p style="color:var(--text-secondary);">No active punishments.</p>
  <?php else: ?>
    <?php foreach ($active as $p): ?>
      <div style="padding:.65rem 0;border-bottom:1px solid var(--border-color);display:flex;justify-content:space-between;align-items:flex-start;gap:.5rem;flex-wrap:wrap;">
        <div style="min-width:0;flex:1;">
          <div style="display:flex;align-items:center;gap:.4rem;flex-wrap:wrap;">
            <strong><?= e($p['username']) ?></strong>
            <span class="badge <?= $p['type']==='forum_ban'?'badge-red':($p['type']==='mute'?'badge-purple':'badge-amber') ?>">
              <?= e(strtoupper(str_replace('_',' ',$p['type']))) ?>
            </span>
            <?php if ($p['expires_at']): ?>
              <span style="font-size:.78rem;color:var(--text-secondary);">
                Expires: <?= e($p['expires_at']) ?>
              </span>
            <?php else: ?>
              <span class="badge badge-red">PERMANENT</span>
            <?php endif; ?>
          </div>
          <div style="font-size:.78rem;color:var(--text-secondary);margin-top:.2rem;">
            Reason: <?= e($p['reason'] ?? 'None') ?> — by <?= e($p['issuer_name']) ?> on <?= e($p['created_at']) ?>
          </div>
        </div>
        <button class="btn btn-secondary btn-sm" onclick="revokePunishment(<?= $p['punishment_id'] ?>)">
          ↩️ Revoke
        </button>
      </div>
    <?php endforeach; ?>
  <?php endif; ?>
</div>

<!-- History -->
<div class="card">
  <h3>📜 Recent History</h3>
  <div style="max-height:500px;overflow-y:auto;">
    <?php foreach ($history as $h): ?>
      <div style="padding:.5rem 0;border-bottom:1px solid var(--border-color);font-size:.85rem;">
        <div style="display:flex;align-items:center;gap:.3rem;flex-wrap:wrap;">
          <strong><?= e($h['username']) ?></strong>
          <span class="badge <?= $h['type']==='forum_ban'?'badge-red':($h['type']==='mute'?'badge-purple':'badge-amber') ?>" style="font-size:.65rem;">
            <?= e(strtoupper(str_replace('_',' ',$h['type']))) ?>
          </span>
          <?php if (!$h['is_active']): ?>
            <span class="badge" style="background:rgba(156,163,175,.15);color:var(--text-secondary);font-size:.6rem;">
              <?= $h['revoked_by'] ? 'REVOKED' : 'EXPIRED' ?>
            </span>
          <?php elseif (!$h['expires_at']): ?>
            <span class="badge badge-red" style="font-size:.6rem;">PERM</span>
          <?php endif; ?>
        </div>
        <div style="font-size:.75rem;color:var(--text-secondary);margin-top:.15rem;">
          <?= e($h['reason'] ?? 'No reason') ?> — by <?= e($h['issuer_name']) ?>
          <?= $h['revoker_name'] ? ' — revoked by ' . e($h['revoker_name']) : '' ?>
          &middot; <?= timeAgo($h['created_at']) ?>
        </div>
      </div>
    <?php endforeach; ?>
  </div>
</div>

<div class="toast" id="toast"></div>

<script>
var CSRF='<?= e($csrf) ?>';
function showToast(m,e){var t=document.getElementById('toast');t.textContent=m;t.className='toast'+(e?' error':'');t.classList.add('is-visible');setTimeout(function(){t.classList.remove('is-visible');},2500);}

function revokePunishment(id){
  if(!confirm('Revoke this punishment?'))return;
  var fd=new FormData();fd.append('csrf_token',CSRF);fd.append('action','revoke_punishment');fd.append('punishment_id',id);
  fetch('/api/forum_admin.php',{method:'POST',body:fd}).then(function(r){return r.json();}).then(function(d){
    if(d.status==='success'){showToast('Revoked!');setTimeout(function(){location.reload();},800);}
    else showToast(d.message||'Error',true);
  });
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>