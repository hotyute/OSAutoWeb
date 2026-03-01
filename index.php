<?php
$pageTitle = 'OS Auto — Home';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';
initSession();

$isAdmin = hasRole('admin');

/* Load settings */
$showScreenshots = settingEnabled($pdo, 'hero_screenshots');
$showFeatures    = settingEnabled($pdo, 'hero_features');
$showPricing     = settingEnabled($pdo, 'hero_pricing');

$heroBg      = getSetting($pdo, 'hero_bg_image', '');
$heroHeading = getSetting($pdo, 'hero_heading', '&gt; Dominate <span class="purple">Gielinor</span>');
$heroSubtext = getSetting($pdo, 'hero_subtext', 'The most advanced Old School RuneScape automation client.');

/* Load screenshots */
$screenshots = $pdo->query(
    'SELECT * FROM `hero_screenshots` ORDER BY `sort_order` ASC, `screenshot_id` ASC'
)->fetchAll();

$csrfToken = generateCSRF();

require_once __DIR__ . '/includes/header.php';
?>

<style>
/* ===== HERO WITH BACKGROUND ===== */
.hero-bg {
  position: relative;
  text-align: center;
  padding: 6rem 1.5rem 4rem;
  overflow: hidden;
  border-radius: var(--radius);
  margin-bottom: 2rem;
}
.hero-bg::before {
  content: '';
  position: absolute;
  inset: 0;
  background-size: cover;
  background-position: center;
  background-repeat: no-repeat;
  z-index: 0;
  filter: brightness(.35) saturate(.8);
  transition: filter .3s;
}
.hero-bg.has-bg::before {
  background-image: var(--hero-bg-url);
}
.hero-bg:not(.has-bg)::before {
  background: linear-gradient(135deg, #0f1117 0%, #1a1d27 40%, #22252f 100%);
}
.hero-bg > * { position: relative; z-index: 1; }
.hero-bg h1 {
  font-size: 3rem;
  font-family: var(--font-mono);
  margin-bottom: 1rem;
  text-shadow: 0 2px 20px rgba(0,0,0,.6);
}
.hero-bg h1 .green { color: var(--accent-green); }
.hero-bg h1 .purple { color: var(--accent-purple); }
.hero-bg p {
  font-size: 1.15rem;
  color: var(--text-secondary);
  max-width: 640px;
  margin: 0 auto 2rem;
  text-shadow: 0 1px 8px rgba(0,0,0,.5);
}

/* ===== ADMIN OVERLAY CONTROLS ===== */
.admin-overlay {
  position: absolute;
  top: .75rem; right: .75rem;
  z-index: 10;
  display: flex;
  gap: .4rem;
  flex-wrap: wrap;
}
.admin-overlay .abtn {
  background: rgba(15,17,23,.85);
  border: 1px solid var(--accent-green);
  color: var(--accent-green);
  padding: .35rem .7rem;
  border-radius: var(--radius);
  font-size: .78rem;
  cursor: pointer;
  font-weight: 600;
  transition: background .2s;
  backdrop-filter: blur(4px);
}
.admin-overlay .abtn:hover { background: rgba(57,255,20,.15); }
.admin-overlay .abtn.red { border-color: var(--accent-red); color: var(--accent-red); }
.admin-overlay .abtn.red:hover { background: rgba(239,68,68,.15); }

/* Admin text editor modal */
.hero-editor {
  display: none;
  position: absolute;
  inset: 0;
  z-index: 20;
  background: rgba(15,17,23,.92);
  backdrop-filter: blur(6px);
  padding: 2rem;
  align-items: center;
  justify-content: center;
}
.hero-editor.is-open { display: flex; }
.hero-editor-inner {
  background: var(--bg-card);
  border: 1px solid var(--border-color);
  border-radius: var(--radius);
  padding: 1.5rem;
  width: 100%;
  max-width: 550px;
  box-shadow: var(--shadow);
}
.hero-editor-inner h3 { color: var(--accent-green); margin-bottom: 1rem; }

/* ===== SCREENSHOT GALLERY ===== */
.screenshot-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
  gap: 1rem;
}
.screenshot-card {
  position: relative;
  border-radius: var(--radius);
  overflow: hidden;
  border: 1px solid var(--border-color);
  aspect-ratio: 16/9;
  background: var(--bg-secondary);
  cursor: pointer;
  transition: transform .2s, border-color .2s;
}
.screenshot-card:hover { transform: translateY(-3px); border-color: var(--accent-green); }
.screenshot-card img {
  width: 100%; height: 100%;
  object-fit: cover; display: block;
}
.ss-overlay {
  position: absolute; bottom: 0; left: 0; right: 0;
  background: linear-gradient(transparent, rgba(15,17,23,.9));
  padding: .75rem 1rem;
}
.ss-overlay .ss-title { font-weight: 600; font-size: .9rem; color: var(--accent-green); }
.ss-overlay .ss-desc { font-size: .78rem; color: var(--text-secondary); margin-top: .15rem; }
.screenshot-placeholder {
  width: 100%; height: 100%;
  display: flex; align-items: center; justify-content: center;
  flex-direction: column; gap: .5rem;
  color: var(--text-secondary); font-size: .85rem;
}
.screenshot-placeholder .ss-icon { font-size: 2.5rem; opacity: .4; }

/* Admin controls on screenshots */
.ss-admin-bar {
  position: absolute; top: .5rem; right: .5rem;
  display: flex; gap: .3rem; z-index: 5;
}
.ss-admin-bar .abtn { font-size: .7rem; padding: .25rem .5rem; }
.ss-admin-bar .sort-btns { display: flex; flex-direction: column; gap: 2px; }
.ss-admin-bar .sort-btns .abtn { padding: .15rem .4rem; font-size: .65rem; }

/* Add screenshot card */
.ss-add-card {
  border: 2px dashed var(--accent-green);
  border-radius: var(--radius);
  aspect-ratio: 16/9;
  display: flex;
  align-items: center;
  justify-content: center;
  flex-direction: column;
  gap: .5rem;
  color: var(--accent-green);
  font-size: .9rem;
  cursor: pointer;
  transition: background .2s;
  background: rgba(57,255,20,.03);
}
.ss-add-card:hover { background: rgba(57,255,20,.08); }

/* Screenshot edit modal */
.ss-modal {
  display: none;
  position: fixed;
  inset: 0;
  z-index: 2000;
  background: rgba(0,0,0,.8);
  align-items: center;
  justify-content: center;
  padding: 1rem;
  backdrop-filter: blur(3px);
}
.ss-modal.is-open { display: flex; }
.ss-modal-inner {
  background: var(--bg-card);
  border: 1px solid var(--border-color);
  border-radius: var(--radius);
  padding: 1.5rem;
  width: 100%;
  max-width: 500px;
  box-shadow: var(--shadow);
  max-height: 90vh;
  overflow-y: auto;
}
.ss-modal-inner h3 { color: var(--accent-green); margin-bottom: 1rem; }

/* Lightbox */
.lightbox {
  display: none; position: fixed; inset: 0;
  background: rgba(0,0,0,.88); z-index: 2500;
  align-items: center; justify-content: center; padding: 1rem;
  cursor: pointer;
}
.lightbox.is-open { display: flex; }
.lightbox img {
  max-width: 95%; max-height: 90vh;
  border-radius: var(--radius);
  border: 2px solid var(--accent-green);
  box-shadow: 0 0 40px rgba(57,255,20,.15);
}
.lightbox-close {
  position: absolute; top: 1rem; right: 1.5rem;
  color: var(--text-primary); font-size: 2rem; cursor: pointer;
  background: var(--bg-secondary); width: 40px; height: 40px;
  border-radius: 50%; display: flex; align-items: center;
  justify-content: center; border: 1px solid var(--border-color);
}

/* Status toast */
.toast {
  position: fixed; bottom: 2rem; right: 2rem;
  background: var(--bg-card); border: 1px solid var(--accent-green);
  color: var(--accent-green); padding: .75rem 1.25rem;
  border-radius: var(--radius); font-size: .88rem;
  box-shadow: var(--shadow); z-index: 3000;
  transform: translateY(100px); opacity: 0;
  transition: transform .3s, opacity .3s;
}
.toast.is-visible { transform: translateY(0); opacity: 1; }
.toast.error { border-color: var(--accent-red); color: var(--accent-red); }

@media (max-width: 680px) {
  .hero-bg { padding: 3rem 1rem 2rem; }
  .hero-bg h1 { font-size: 1.7rem; }
  .hero-bg p { font-size: .95rem; }
  .screenshot-grid { grid-template-columns: 1fr; }
  .admin-overlay { position: relative; top: auto; right: auto; margin-bottom: .5rem; justify-content: center; }
  .hero-editor { padding: 1rem; }
}
</style>

<!-- ============================================================
     HERO SECTION (with admin-editable background)
     ============================================================ -->
<section class="hero-bg <?= $heroBg ? 'has-bg' : '' ?>"
         <?php if ($heroBg): ?>
           style="--hero-bg-url: url('/<?= e($heroBg) ?>');"
         <?php endif; ?>>

  <?php if ($isAdmin): ?>
    <!-- Admin overlay controls -->
    <div class="admin-overlay">
      <label class="abtn" style="cursor:pointer;">
        📷 Change Background
        <input type="file" id="heroBgUpload" accept="image/*"
               style="display:none;" onchange="uploadHeroBg(this)">
      </label>
      <?php if ($heroBg): ?>
        <button class="abtn red" onclick="removeHeroBg()">✕ Remove BG</button>
      <?php endif; ?>
      <button class="abtn" onclick="openHeroEditor()">✏️ Edit Text</button>
    </div>

    <!-- Hero text editor (overlay) -->
    <div class="hero-editor" id="heroEditor">
      <div class="hero-editor-inner">
        <h3>✏️ Edit Hero Text</h3>
        <div class="form-group">
          <label>Heading (HTML tags allowed: &lt;span class="green"&gt; etc.)</label>
          <input type="text" id="heroHeadingInput"
                 value="<?= e($heroHeading) ?>">
        </div>
        <div class="form-group">
          <label>Subtext</label>
          <textarea id="heroSubtextInput" rows="3"><?= e($heroSubtext) ?></textarea>
        </div>
        <div style="display:flex;gap:.5rem;">
          <button class="btn btn-primary" onclick="saveHeroText()">Save</button>
          <button class="btn btn-secondary" onclick="closeHeroEditor()">Cancel</button>
        </div>
      </div>
    </div>
  <?php endif; ?>

  <h1 id="heroHeading"><?= $heroHeading /* admin-controlled, sanitized at save */ ?></h1>
  <p id="heroSubtext"><?= e($heroSubtext) ?></p>
  <div class="hero-buttons">
    <a href="/register.php" class="btn btn-primary">Get Started</a>
    <a href="#pricing" class="btn btn-secondary">View Pricing</a>
  </div>
</section>

<!-- ============================================================
     SCREENSHOT GALLERY (admin: add/edit/delete/reorder inline)
     ============================================================ -->
<?php if ($showScreenshots): ?>
<section class="mb-2" id="gallerySection">
  <h2 class="text-center mb-1" style="color:var(--accent-green);">📸 See It In Action</h2>
  <p class="text-center" style="color:var(--text-secondary);margin-bottom:1.5rem;font-size:.95rem;">
    Real screenshots from the client. Click to enlarge.
  </p>

  <div class="screenshot-grid" id="screenshotGrid">
    <?php foreach ($screenshots as $ss): ?>
      <div class="screenshot-card" data-id="<?= $ss['screenshot_id'] ?>"
           data-sort="<?= $ss['sort_order'] ?>"
           onclick="openLightbox(this)">

        <?php if ($isAdmin): ?>
          <div class="ss-admin-bar" onclick="event.stopPropagation();">
            <div class="sort-btns">
              <button class="abtn" onclick="moveScreenshot(<?= $ss['screenshot_id'] ?>,'up')" title="Move up">▲</button>
              <button class="abtn" onclick="moveScreenshot(<?= $ss['screenshot_id'] ?>,'down')" title="Move down">▼</button>
            </div>
            <button class="abtn" onclick="openEditModal(<?= $ss['screenshot_id'] ?>, '<?= e(addslashes($ss['title'])) ?>', '<?= e(addslashes($ss['description'] ?? '')) ?>')">✏️</button>
            <button class="abtn red" onclick="deleteScreenshot(<?= $ss['screenshot_id'] ?>)">🗑️</button>
          </div>
        <?php endif; ?>

        <img src="/<?= e($ss['image_path']) ?>" alt="<?= e($ss['title']) ?>" loading="lazy">
        <div class="ss-overlay">
          <div class="ss-title"><?= e($ss['title']) ?></div>
          <?php if ($ss['description']): ?>
            <div class="ss-desc"><?= e($ss['description']) ?></div>
          <?php endif; ?>
        </div>
      </div>
    <?php endforeach; ?>

    <?php if ($isAdmin): ?>
      <!-- Add screenshot card -->
      <div class="ss-add-card" onclick="openAddModal()">
        <span style="font-size:2.5rem;">➕</span>
        <span>Add Screenshot</span>
      </div>
    <?php endif; ?>

    <?php if (empty($screenshots) && !$isAdmin): ?>
      <div style="grid-column:1/-1;text-align:center;padding:2rem;color:var(--text-secondary);">
        Screenshots coming soon.
      </div>
    <?php endif; ?>
  </div>
</section>

<!-- Lightbox -->
<div class="lightbox" id="lightboxOverlay" onclick="closeLightbox()">
  <div class="lightbox-close" onclick="closeLightbox()">&times;</div>
  <img id="lightboxImg" src="" alt="Screenshot">
</div>

<!-- Screenshot Add/Edit Modal -->
<div class="ss-modal" id="ssModal">
  <div class="ss-modal-inner">
    <h3 id="ssModalTitle">Add Screenshot</h3>
    <form id="ssForm" enctype="multipart/form-data">
      <input type="hidden" id="ssId" value="">
      <input type="hidden" id="ssAction" value="add_screenshot">

      <div class="form-group">
        <label for="ssTitle">Title *</label>
        <input type="text" id="ssTitle" required maxlength="120" placeholder="e.g. Combat Script">
      </div>
      <div class="form-group">
        <label for="ssDesc">Description</label>
        <input type="text" id="ssDesc" maxlength="255" placeholder="Brief description…">
      </div>
      <div class="form-group">
        <label for="ssFile">Image (JPG, PNG, GIF, WebP — max 5MB)</label>
        <input type="file" id="ssFile" accept="image/*" style="font-size:.85rem;">
        <div id="ssImagePreview" style="margin-top:.5rem;display:none;">
          <img id="ssPreviewImg" src="" alt="Preview"
               style="max-width:100%;max-height:150px;border-radius:var(--radius);border:1px solid var(--border-color);">
        </div>
      </div>

      <div style="display:flex;gap:.5rem;flex-wrap:wrap;">
        <button type="submit" class="btn btn-primary" id="ssSubmitBtn">Upload</button>
        <button type="button" class="btn btn-secondary" onclick="closeModal()">Cancel</button>
      </div>
    </form>
  </div>
</div>

<!-- Toast notification -->
<div class="toast" id="toast"></div>
<?php endif; ?>

<!-- ============================================================
     FEATURES
     ============================================================ -->
<?php if ($showFeatures): ?>
<section class="mb-2">
  <div class="grid-3">
    <div class="card"><h3>🛡️ Undetectable</h3><p style="color:var(--text-secondary);">Advanced injection avoidance, randomised human-like input patterns, and dynamic sleep intervals keep your account safe.</p></div>
    <div class="card"><h3>⚔️ Combat Scripts</h3><p style="color:var(--text-secondary);">From Slayer tasks to Nightmare Zone, fully-configurable combat routines handle prayer flicking, gear switching, and loot tracking.</p></div>
    <div class="card"><h3>⛏️ Skilling Suite</h3><p style="color:var(--text-secondary);">Mining, Woodcutting, Fishing, Agility — every skill covered with optimised pathing and world-hopping support.</p></div>
    <div class="card"><h3>🎮 Minigames</h3><p style="color:var(--text-secondary);">Automate Tempoross, Guardians of the Rift, Wintertodt, and more. Built-in fail-safes handle random events automatically.</p></div>
    <div class="card"><h3>🔒 HWID Locked</h3><p style="color:var(--text-secondary);">Each license is bound to your hardware ID, preventing unauthorised sharing while still allowing periodic resets.</p></div>
    <div class="card"><h3>📊 Dashboard</h3><p style="color:var(--text-secondary);">Monitor your subscription, browse scripts, and manage your account from a sleek web portal — right here.</p></div>
  </div>
</section>
<?php endif; ?>

<!-- ============================================================
     PRICING
     ============================================================ -->
<?php if ($showPricing): ?>
<section id="pricing" class="mb-2">
  <h2 class="text-center mb-1" style="color:var(--accent-green);">Choose Your Plan</h2>
  <div class="grid-3">
    <div class="card pricing-card">
      <h3>Starter</h3><div class="price">£5<span>/mo</span></div>
      <ul><li>5 Free Scripts</li><li>1 HWID Bind</li><li>Community Support</li></ul>
      <a href="/register.php" class="btn btn-secondary w-full">Select</a>
    </div>
    <div class="card pricing-card featured">
      <h3>Pro</h3><div class="price">£10<span>/mo</span></div>
      <ul><li>All Scripts</li><li>Priority Anti-Ban</li><li>Discord Support</li></ul>
      <a href="/register.php" class="btn btn-primary w-full">Select</a>
    </div>
    <div class="card pricing-card">
      <h3>Enterprise</h3><div class="price">£30<span>/mo</span></div>
      <ul><li>Unlimited Accounts</li><li>Custom Script Dev</li><li>Dedicated Manager</li></ul>
      <a href="/register.php" class="btn btn-purple w-full">Contact Us</a>
    </div>
  </div>
</section>
<?php endif; ?>

<!-- ============================================================
     JAVASCRIPT — Admin inline editing
     ============================================================ -->
<?php if ($isAdmin): ?>
<script>
var CSRF = '<?= e($csrfToken) ?>';
var API  = '/api/hero_upload.php';

/* ===== TOAST ===== */
function showToast(msg, isError) {
    var t = document.getElementById('toast');
    t.textContent = msg;
    t.className = 'toast' + (isError ? ' error' : '');
    t.classList.add('is-visible');
    setTimeout(function(){ t.classList.remove('is-visible'); }, 3000);
}

/* ===== HERO BACKGROUND ===== */
function uploadHeroBg(input) {
    if (!input.files[0]) return;
    var fd = new FormData();
    fd.append('action', 'upload_hero_bg');
    fd.append('csrf_token', CSRF);
    fd.append('hero_bg', input.files[0]);
    fetch(API, { method: 'POST', body: fd })
        .then(function(r){ return r.json(); })
        .then(function(d){
            if (d.status === 'success') {
                showToast('Background updated!');
                setTimeout(function(){ location.reload(); }, 800);
            } else {
                showToast(d.message || 'Error', true);
            }
        })
        .catch(function(){ showToast('Upload failed', true); });
}

function removeHeroBg() {
    if (!confirm('Remove the hero background image?')) return;
    var fd = new FormData();
    fd.append('action', 'remove_hero_bg');
    fd.append('csrf_token', CSRF);
    fetch(API, { method: 'POST', body: fd })
        .then(function(r){ return r.json(); })
        .then(function(d){
            if (d.status === 'success') {
                showToast('Background removed.');
                setTimeout(function(){ location.reload(); }, 800);
            } else {
                showToast(d.message || 'Error', true);
            }
        });
}

/* ===== HERO TEXT EDITOR ===== */
function openHeroEditor() { document.getElementById('heroEditor').classList.add('is-open'); }
function closeHeroEditor() { document.getElementById('heroEditor').classList.remove('is-open'); }

function saveHeroText() {
    var heading = document.getElementById('heroHeadingInput').value;
    var subtext = document.getElementById('heroSubtextInput').value;
    var fd = new FormData();
    fd.append('action', 'update_hero_text');
    fd.append('csrf_token', CSRF);
    fd.append('hero_heading', heading);
    fd.append('hero_subtext', subtext);
    fetch(API, { method: 'POST', body: fd })
        .then(function(r){ return r.json(); })
        .then(function(d){
            if (d.status === 'success') {
                showToast('Hero text updated!');
                document.getElementById('heroHeading').innerHTML = heading;
                document.getElementById('heroSubtext').textContent = subtext;
                closeHeroEditor();
            } else {
                showToast(d.message || 'Error', true);
            }
        });
}

/* ===== LIGHTBOX ===== */
function openLightbox(card) {
    var img = card.querySelector('img');
    if (!img) return;
    document.getElementById('lightboxImg').src = img.src;
    document.getElementById('lightboxOverlay').classList.add('is-open');
}
function closeLightbox() {
    document.getElementById('lightboxOverlay').classList.remove('is-open');
}
document.addEventListener('keydown', function(e){ if(e.key==='Escape') closeLightbox(); });

/* ===== SCREENSHOT MODALS ===== */
function openAddModal() {
    document.getElementById('ssModalTitle').textContent = 'Add Screenshot';
    document.getElementById('ssId').value = '';
    document.getElementById('ssAction').value = 'add_screenshot';
    document.getElementById('ssTitle').value = '';
    document.getElementById('ssDesc').value = '';
    document.getElementById('ssFile').value = '';
    document.getElementById('ssFile').required = true;
    document.getElementById('ssImagePreview').style.display = 'none';
    document.getElementById('ssSubmitBtn').textContent = 'Upload';
    document.getElementById('ssModal').classList.add('is-open');
}

function openEditModal(id, title, desc) {
    document.getElementById('ssModalTitle').textContent = 'Edit Screenshot';
    document.getElementById('ssId').value = id;
    document.getElementById('ssAction').value = 'edit_screenshot';
    document.getElementById('ssTitle').value = title;
    document.getElementById('ssDesc').value = desc;
    document.getElementById('ssFile').value = '';
    document.getElementById('ssFile').required = false;
    document.getElementById('ssImagePreview').style.display = 'none';
    document.getElementById('ssSubmitBtn').textContent = 'Save Changes';
    document.getElementById('ssModal').classList.add('is-open');
}

function closeModal() {
    document.getElementById('ssModal').classList.remove('is-open');
}

/* Image preview on file select */
document.getElementById('ssFile').addEventListener('change', function(){
    var preview = document.getElementById('ssImagePreview');
    var img = document.getElementById('ssPreviewImg');
    if (this.files && this.files[0]) {
        var reader = new FileReader();
        reader.onload = function(e){ img.src = e.target.result; preview.style.display = 'block'; };
        reader.readAsDataURL(this.files[0]);
    } else {
        preview.style.display = 'none';
    }
});

/* Form submission */
document.getElementById('ssForm').addEventListener('submit', function(e){
    e.preventDefault();
    var fd = new FormData();
    fd.append('csrf_token', CSRF);
    fd.append('action', document.getElementById('ssAction').value);
    fd.append('title', document.getElementById('ssTitle').value);
    fd.append('description', document.getElementById('ssDesc').value);

    var ssId = document.getElementById('ssId').value;
    if (ssId) fd.append('screenshot_id', ssId);

    var fileInput = document.getElementById('ssFile');
    if (fileInput.files[0]) {
        fd.append('screenshot', fileInput.files[0]);
    }

    var btn = document.getElementById('ssSubmitBtn');
    btn.disabled = true;
    btn.textContent = 'Uploading…';

    fetch(API, { method: 'POST', body: fd })
        .then(function(r){ return r.json(); })
        .then(function(d){
            btn.disabled = false;
            if (d.status === 'success') {
                showToast(ssId ? 'Screenshot updated!' : 'Screenshot added!');
                closeModal();
                setTimeout(function(){ location.reload(); }, 800);
            } else {
                showToast(d.message || 'Error', true);
                btn.textContent = ssId ? 'Save Changes' : 'Upload';
            }
        })
        .catch(function(){
            btn.disabled = false;
            btn.textContent = ssId ? 'Save Changes' : 'Upload';
            showToast('Network error', true);
        });
});

/* ===== DELETE SCREENSHOT ===== */
function deleteScreenshot(id) {
    if (!confirm('Delete this screenshot permanently?')) return;
    var fd = new FormData();
    fd.append('action', 'delete_screenshot');
    fd.append('csrf_token', CSRF);
    fd.append('screenshot_id', id);
    fetch(API, { method: 'POST', body: fd })
        .then(function(r){ return r.json(); })
        .then(function(d){
            if (d.status === 'success') {
                showToast('Screenshot deleted.');
                var card = document.querySelector('[data-id="'+id+'"]');
                if (card) card.remove();
            } else {
                showToast(d.message || 'Error', true);
            }
        });
}

/* ===== REORDER SCREENSHOTS ===== */
function moveScreenshot(id, direction) {
    var grid = document.getElementById('screenshotGrid');
    var cards = Array.from(grid.querySelectorAll('.screenshot-card'));
    var idx = cards.findIndex(function(c){ return c.dataset.id == id; });
    if (idx === -1) return;

    if (direction === 'up' && idx > 0) {
        grid.insertBefore(cards[idx], cards[idx - 1]);
    } else if (direction === 'down' && idx < cards.length - 1) {
        grid.insertBefore(cards[idx + 1], cards[idx]);
    } else {
        return;
    }

    /* Collect new order and send to server */
    var newCards = Array.from(grid.querySelectorAll('.screenshot-card'));
    var order = newCards.map(function(c){ return c.dataset.id; });

    var fd = new FormData();
    fd.append('action', 'reorder_screenshots');
    fd.append('csrf_token', CSRF);
    for (var i = 0; i < order.length; i++) {
        fd.append('order[' + i + ']', order[i]);
    }

    fetch(API, { method: 'POST', body: fd })
        .then(function(r){ return r.json(); })
        .then(function(d){
            if (d.status === 'success') {
                showToast('Order saved.');
            } else {
                showToast('Reorder failed', true);
            }
        });
}
</script>
<?php else: ?>
<!-- Non-admin: simple lightbox only -->
<script>
function openLightbox(card) {
    var img = card.querySelector('img');
    if (!img) return;
    document.getElementById('lightboxImg').src = img.src;
    document.getElementById('lightboxOverlay').classList.add('is-open');
}
function closeLightbox() {
    document.getElementById('lightboxOverlay').classList.remove('is-open');
}
document.addEventListener('keydown', function(e){ if(e.key==='Escape') closeLightbox(); });
</script>
<?php endif; ?>

<?php require_once __DIR__ . '/includes/footer.php'; ?>