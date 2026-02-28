<?php
/**
 * Public Landing Page — Responsive
 * Hero buttons wrap on mobile. Pricing & features auto-stack.
 */
$pageTitle = 'OSRS Client — Home';
require_once __DIR__ . '/includes/header.php';
?>

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

<!-- FEATURES -->
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

<!-- PRICING -->
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

<?php require_once __DIR__ . '/includes/footer.php'; ?>