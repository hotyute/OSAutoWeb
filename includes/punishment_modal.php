<?php
/**
 * Shared Punishment Modal
 * --------------------------------------------------------
 * Include this file on ANY page that needs inline
 * punishment controls (thread.php, profile.php, board.php, etc.)
 *
 * Requirements before including:
 *   • includes/functions.php must be loaded
 *   • User must be logged in
 *   • Call canIssuePunishments() to gate the include
 *
 * Usage in any page:
 *   <?php if (canIssuePunishments()):
 *       require_once __DIR__ . '/../includes/punishment_modal.php';
 *   endif; ?>
 *
 * Then on any post/profile, add a button:
 *   <button onclick="openPunishModal(userId, 'username')">⚖️</button>
 */

if (!canIssuePunishments()) return;

$_punishCsrf = generateCSRF();
?>

<!-- ===== PUNISHMENT MODAL OVERLAY ===== -->
<div class="ss-modal" id="punishModal">
  <div class="ss-modal-inner" style="max-width:440px;">
    <h3 style="color:var(--accent-red);">⚖️ Issue Punishment</h3>

    <p style="color:var(--text-secondary);font-size:.85rem;margin-bottom:1rem;">
      Target: <strong id="punishTargetName" style="color:var(--accent-green);"></strong>
    </p>

    <form id="punishForm" onsubmit="submitPunishment(event)">
      <input type="hidden" id="punishUserId" value="">

      <div class="form-group">
        <label for="punishType">Type</label>
        <select id="punishType" required>
          <option value="warn">⚠️ Warning</option>
          <option value="mute">🔇 Mute (cannot post)</option>
          <option value="forum_ban">🚫 Forum Ban (full access revoked)</option>
        </select>
      </div>

      <div class="form-group" id="punishDurationGroup">
        <label for="punishDuration">Duration</label>
        <select id="punishDuration" required>
          <option value="1h">1 Hour</option>
          <option value="6h">6 Hours</option>
          <option value="12h">12 Hours</option>
          <option value="1d" selected>1 Day</option>
          <option value="3d">3 Days</option>
          <option value="7d">7 Days</option>
          <option value="14d">14 Days</option>
          <option value="30d">30 Days</option>
          <option value="90d">90 Days</option>
          <option value="permanent">⛔ Permanent</option>
        </select>
      </div>

      <div class="form-group">
        <label for="punishReason">Reason</label>
        <textarea id="punishReason" rows="3" maxlength="500"
                  placeholder="Explain why this action is being taken…"></textarea>
      </div>

      <div style="display:flex;gap:.5rem;flex-wrap:wrap;">
        <button type="submit" class="btn btn-danger" id="punishSubmitBtn">
          Issue Punishment
        </button>
        <button type="button" class="btn btn-secondary" onclick="closePunishModal()">
          Cancel
        </button>
      </div>
    </form>
  </div>
</div>

<!-- Toast (only injected if not already present) -->
<script>
if (!document.getElementById('punishToast')) {
    var toastEl = document.createElement('div');
    toastEl.id = 'punishToast';
    toastEl.className = 'toast';
    document.body.appendChild(toastEl);
}
</script>

<script>
(function(){
    var PUNISH_CSRF = '<?= e($_punishCsrf) ?>';
    var API_URL     = '/api/forum_admin.php';

    /* Show/hide duration when type is "warn" (warnings don't need duration) */
    var typeSelect    = document.getElementById('punishType');
    var durationGroup = document.getElementById('punishDurationGroup');
    var durationSelect = document.getElementById('punishDuration');

    typeSelect.addEventListener('change', function(){
        if (this.value === 'warn') {
            durationGroup.style.display = 'none';
            durationSelect.required = false;
        } else {
            durationGroup.style.display = 'block';
            durationSelect.required = true;
        }
    });

    /* ===== Open / Close ===== */
    window.openPunishModal = function(userId, username) {
        document.getElementById('punishUserId').value = userId;
        document.getElementById('punishTargetName').textContent = username;
        document.getElementById('punishType').value = 'warn';
        document.getElementById('punishDuration').value = '1d';
        document.getElementById('punishReason').value = '';
        durationGroup.style.display = 'none';
        durationSelect.required = false;
        document.getElementById('punishSubmitBtn').disabled = false;
        document.getElementById('punishSubmitBtn').textContent = 'Issue Punishment';
        document.getElementById('punishModal').classList.add('is-open');
    };

    window.closePunishModal = function() {
        document.getElementById('punishModal').classList.remove('is-open');
    };

    /* ===== Submit ===== */
    window.submitPunishment = function(e) {
        e.preventDefault();

        var btn = document.getElementById('punishSubmitBtn');
        btn.disabled = true;
        btn.textContent = 'Processing…';

        var fd = new FormData();
        fd.append('csrf_token', PUNISH_CSRF);
        fd.append('action', 'issue_punishment');
        fd.append('target_user_id', document.getElementById('punishUserId').value);
        fd.append('type', document.getElementById('punishType').value);
        fd.append('reason', document.getElementById('punishReason').value);

        var type = document.getElementById('punishType').value;
        if (type !== 'warn') {
            fd.append('duration', document.getElementById('punishDuration').value);
        }

        fetch(API_URL, { method: 'POST', body: fd })
            .then(function(r){ return r.json(); })
            .then(function(d){
                btn.disabled = false;
                btn.textContent = 'Issue Punishment';
                if (d.status === 'success') {
                    closePunishModal();
                    showPunishToast(d.message || 'Punishment issued.');
                    setTimeout(function(){ location.reload(); }, 1000);
                } else {
                    showPunishToast(d.message || 'Error', true);
                }
            })
            .catch(function(){
                btn.disabled = false;
                btn.textContent = 'Issue Punishment';
                showPunishToast('Network error', true);
            });
    };

    /* ===== Revoke (callable from any page) ===== */
    window.revokePunishment = function(punishmentId) {
        if (!confirm('Revoke this punishment?')) return;

        var fd = new FormData();
        fd.append('csrf_token', PUNISH_CSRF);
        fd.append('action', 'revoke_punishment');
        fd.append('punishment_id', punishmentId);

        fetch(API_URL, { method: 'POST', body: fd })
            .then(function(r){ return r.json(); })
            .then(function(d){
                if (d.status === 'success') {
                    showPunishToast('Punishment revoked.');
                    setTimeout(function(){ location.reload(); }, 800);
                } else {
                    showPunishToast(d.message || 'Error', true);
                }
            })
            .catch(function(){ showPunishToast('Network error', true); });
    };

    /* ===== Toast helper ===== */
    function showPunishToast(msg, isError) {
        /* Try the page's own toast first, fallback to ours */
        var t = document.getElementById('toast') || document.getElementById('punishToast');
        if (!t) return;
        t.textContent = msg;
        t.className = 'toast' + (isError ? ' error' : '');
        t.classList.add('is-visible');
        setTimeout(function(){ t.classList.remove('is-visible'); }, 3000);
    }

    /* Close on Escape */
    document.addEventListener('keydown', function(e){
        if (e.key === 'Escape') closePunishModal();
    });

    /* Close on backdrop click */
    document.getElementById('punishModal').addEventListener('click', function(e){
        if (e.target === this) closePunishModal();
    });
})();
</script>