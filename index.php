<?php
$pageTitle = 'OS Auto — Home';
require_once __DIR__ . '/includes/header.php';

$showScreenshots = settingEnabled($pdo, 'hero_screenshots');
$showFeatures    = settingEnabled($pdo, 'hero_features');
$showPricing     = settingEnabled($pdo, 'hero_pricing');
?>

<style>
.screenshot-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(300px,1fr));gap:1rem;}
.screenshot-card{position:relative;border-radius:var(--radius);overflow:hidden;border:1px solid var(--border-color);aspect-ratio:16/9;background:var(--bg-secondary);cursor:pointer;transition:transform .2s,border-color .2s;}
.screenshot-card:hover{transform:translateY(-3px);border-color:var(--accent-green);}
.screenshot-card img{width:100%;height:100%;object-fit:cover;display:block;}
.screenshot-card .ss-overlay{position:absolute;bottom:0;left:0;right:0;background:linear-gradient(transparent,rgba(15,17,23,.9));padding:.75rem 1rem;transition:opacity .2s;}
.screenshot-card .ss-title{font-weight:600;font-size:.9rem;color:var(--accent-green);}
.screenshot-card .ss-desc{font-size:.78rem;color:var(--text-secondary);margin-top:.15rem;}
.screenshot-placeholder{width:100%;height:100%;display:flex;align-items:center;justify-content:center;flex-direction:column;gap:.5rem;color:var(--text-secondary);font-size:.85rem;}
.screenshot-placeholder .ss-icon{font-size:2.5rem;opacity:.4;}

/* Lightbox */
.lightbox{display:none;position:fixed;inset:0;background:rgba(0,0,0,.85);z-index:2000;align-items:center;justify-content:center;padding:1rem;cursor:pointer;}
.lightbox.is-open{display:flex;}
.lightbox img{max-width:95%;max-height:90vh;border-radius:var(--radius);border:2px solid var(--accent-green);box-shadow:0 0 40px rgba(57,255,20,.15);}
.lightbox-close{position:absolute;top:1rem;right:1.5rem;color:var(--text-primary);font-size:2rem;cursor:pointer;background:var(--bg-secondary);width:40px;height:40px;border-radius:50%;display:flex;align-items:center;justify-content:center;border:1px solid var(--border-color);}

@media(max-width:680px){
  .screenshot-grid{grid-template-columns:1fr;}
}
</style>

<!-- HERO -->
<section class="hero">
  <h1><span class="green">&gt;</span> Dominate <span class="purple">Gielinor</span></h1>
  <p>The most advanced Old School RuneScape automation client.
     Undetectable anti-ban, pixel-perfect input, and a growing library of premium scripts.</p>
  <div class="hero-buttons">
    <a href="/register.php" class="btn btn-primary">Get Started</a>
    <a href="#pricing" class="btn btn-secondary">View Pricing</a>
  </div>
</section>

<!-- SCREENSHOT GALLERY -->
<?php if ($showScreenshots): ?>
<section class="mb-2">
  <h2 class="text-center mb-1" style="color:var(--accent-green);">📸 See It In Action</h2>
  <p class="text-center" style="color:var(--text-secondary);margin-bottom:1.5rem;font-size:.95rem;">
    Real screenshots from the client. Click to enlarge.
  </p>

  <div class="screenshot-grid">
    <!--
      Replace placeholder divs with real <img> tags:
      <div class="screenshot-card" onclick="openLightbox(this)">
        <img src="/screenshots/combat.jpg" alt="Combat Script">
        <div class="ss-overlay">
          <div class="ss-title">Combat Script</div>
          <div class="ss-desc">Automated Slayer task at Nieve's cave</div>
        </div>
      </div>
    -->

    <div class="screenshot-card" onclick="openLightbox(this)">
      <div class="screenshot-placeholder">
        <div class="ss-icon">⚔️</div>
        <div>Combat Script Preview</div>
      </div>
      <div class="ss-overlay">
        <div class="ss-title">Combat Automation</div>
        <div class="ss-desc">Slayer tasks with prayer flicking & loot tracking</div>
      </div>
    </div>

    <div class="screenshot-card" onclick="openLightbox(this)">
      <div class="screenshot-placeholder">
        <div class="ss-icon">⛏️</div>
        <div>Mining Script Preview</div>
      </div>
      <div class="ss-overlay">
        <div class="ss-title">3-Tick Mining</div>
        <div class="ss-desc">Optimised granite mining with world-hop support</div>
      </div>
    </div>

    <div class="screenshot-card" onclick="openLightbox(this)">
      <div class="screenshot-placeholder">
        <div class="ss-icon">🎮</div>
        <div>Minigame Preview</div>
      </div>
      <div class="ss-overlay">
        <div class="ss-title">Wintertodt</div>
        <div class="ss-desc">Full AFK Wintertodt with crate management</div>
      </div>
    </div>

    <div class="screenshot-card" onclick="openLightbox(this)">
      <div class="screenshot-placeholder">
        <div class="ss-icon">📊</div>
        <div>Dashboard Preview</div>
      </div>
      <div class="ss-overlay">
        <div class="ss-title">Client Dashboard</div>
        <div class="ss-desc">Real-time stats, XP tracking, and session logs</div>
      </div>
    </div>

    <div class="screenshot-card" onclick="openLightbox(this)">
      <div class="screenshot-placeholder">
        <div class="ss-icon">🏃</div>
        <div>Agility Script Preview</div>
      </div>
      <div class="ss-overlay">
        <div class="ss-title">Rooftop Agility</div>
        <div class="ss-desc">All courses supported with mark collection</div>
      </div>
    </div>

    <div class="screenshot-card" onclick="openLightbox(this)">
      <div class="screenshot-placeholder">
        <div class="ss-icon">🛡️</div>
        <div>Anti-Ban Preview</div>
      </div>
      <div class="ss-overlay">
        <div class="ss-title">Anti-Ban System</div>
        <div class="ss-desc">Human-like patterns with dynamic sleep intervals</div>
      </div>
    </div>
  </div>
</section>

<!-- Lightbox overlay -->
<div class="lightbox" id="lightboxOverlay" onclick="closeLightbox()">
  <div class="lightbox-close" onclick="closeLightbox()">&times;</div>
  <img id="lightboxImg" src="" alt="Screenshot">
</div>

<script>
function openLightbox(card) {
    var img = card.querySelector('img');
    var overlay = document.getElementById('lightboxOverlay');
    var lbImg = document.getElementById('lightboxImg');
    if (img) {
        lbImg.src = img.src;
        overlay.classList.add('is-open');
    }
}
function closeLightbox() {
    document.getElementById('lightboxOverlay').classList.remove('is-open');
}
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') closeLightbox();
});
</script>
<?php endif; ?>

<!-- FEATURES -->
<?php if ($showFeatures): ?>
<section class="mb-2">
  <div class="grid-3">
    <div class="card">
      <h3>🛡️ Undetectable</h3>
      <p style="color:var(--text-secondary);">
        Advanced injection avoidance, randomised human-like input patterns,
        and dynamic sleep intervals keep your account safe.
      </p>
    </div>
    <div class="card">
      <h3>⚔️ Combat Scripts</h3>
      <p style="color:var(--text-secondary);">
        From Slayer tasks to Nightmare Zone, fully-configurable combat
        routines handle prayer flicking, gear switching, and loot tracking.
      </p>
    </div>
    <div class="card">
      <h3>⛏️ Skilling Suite</h3>
      <p style="color:var(--text-secondary);">
        Mining, Woodcutting, Fishing, Agility — every skill covered
        with optimised pathing and world-hopping support.
      </p>
    </div>
    <div class="card">
      <h3>🎮 Minigames</h3>
      <p style="color:var(--text-secondary);">
        Automate Tempoross, Guardians of the Rift, Wintertodt, and more.
        Built-in fail-safes handle random events automatically.
      </p>
    </div>
    <div class="card">
      <h3>🔒 HWID Locked</h3>
      <p style="color:var(--text-secondary);">
        Each license is bound to your hardware ID, preventing unauthorised
        sharing while still allowing periodic resets.
      </p>
    </div>
    <div class="card">
      <h3>📊 Dashboard</h3>
      <p style="color:var(--text-secondary);">
        Monitor your subscription, browse scripts, and manage your
        account from a sleek web portal — right here.
      </p>
    </div>
  </div>
</section>
<?php endif; ?>

<!-- PRICING -->
<?php if ($showPricing): ?>
<section id="pricing" class="mb-2">
  <h2 class="text-center mb-1" style="color:var(--accent-green);">Choose Your Plan</h2>
  <div class="grid-3">
    <div class="card pricing-card">
      <h3>Starter</h3>
      <div class="price">£5<span>/mo</span></div>
      <ul>
        <li>5 Free Scripts</li>
        <li>1 HWID Bind</li>
        <li>Community Support</li>
      </ul>
      <a href="/register.php" class="btn btn-secondary w-full">Select</a>
    </div>
    <div class="card pricing-card featured">
      <h3>Pro</h3>
      <div class="price">£10<span>/mo</span></div>
      <ul>
        <li>All Scripts</li>
        <li>Priority Anti-Ban</li>
        <li>Discord Support</li>
      </ul>
      <a href="/register.php" class="btn btn-primary w-full">Select</a>
    </div>
    <div class="card pricing-card">
      <h3>Enterprise</h3>
      <div class="price">£30<span>/mo</span></div>
      <ul>
        <li>Unlimited Accounts</li>
        <li>Custom Script Dev</li>
        <li>Dedicated Manager</li>
      </ul>
      <a href="/register.php" class="btn btn-purple w-full">Contact Us</a>
    </div>
  </div>
</section>
<?php endif; ?>

<?php require_once __DIR__ . '/includes/footer.php'; ?>