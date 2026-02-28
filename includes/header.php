<?php
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/functions.php';
initSession();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= e($pageTitle ?? 'OSRS Portal') ?></title>
  <style>
    *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

    :root {
      --bg-primary:    #0f1117;
      --bg-secondary:  #1a1d27;
      --bg-card:       #22252f;
      --border-color:  #2e3140;
      --text-primary:  #e4e4e7;
      --text-secondary:#9ca3af;
      --accent-green:  #39ff14;
      --accent-purple: #a855f7;
      --accent-red:    #ef4444;
      --accent-amber:  #f59e0b;
      --accent-blue:   #3b82f6;
      --font-mono:     'Courier New', monospace;
      --font-main:     'Segoe UI', system-ui, -apple-system, sans-serif;
      --radius:        8px;
      --shadow:        0 4px 24px rgba(0,0,0,.4);
      --nav-height:    60px;
    }

    /*
     * CRITICAL: Lock the viewport so nothing can cause
     * horizontal page scroll on mobile.
     */
    html {
      overflow-x: hidden;
      -webkit-text-size-adjust: 100%;
    }
    body {
      font-family: var(--font-main);
      background: var(--bg-primary);
      color: var(--text-primary);
      line-height: 1.6;
      min-height: 100vh;
      display: flex;
      flex-direction: column;
      overflow-x: hidden;
      max-width: 100vw;
    }

    a { color: var(--accent-green); text-decoration: none; transition: color .2s; }
    a:hover { color: #6fff50; }

    /* ===== NAVBAR ===== */
    .navbar {
      background: var(--bg-secondary);
      border-bottom: 1px solid var(--border-color);
      position: sticky; top: 0; z-index: 1000;
    }
    .navbar-inner {
      max-width: 1320px; margin: 0 auto;
      padding: 0 1.5rem; height: var(--nav-height);
      display: flex; align-items: center; justify-content: space-between;
    }
    .brand {
      font-size: 1.3rem; font-weight: 700;
      color: var(--accent-green);
      font-family: var(--font-mono);
      letter-spacing: 1px; white-space: nowrap;
    }
    .brand span { color: var(--accent-purple); }
    .nav-links {
      display: flex; gap: .25rem; align-items: center; list-style: none;
    }
    .nav-links a {
      color: var(--text-secondary); font-size: .88rem;
      padding: .5rem .85rem; border-radius: var(--radius);
      transition: background .2s, color .2s; white-space: nowrap;
    }
    .nav-links a:hover, .nav-links a.active {
      background: rgba(57,255,20,.08); color: var(--accent-green);
    }
    .nav-links a.role-mod  { color: var(--accent-amber); }
    .nav-links a.role-mod:hover  { background: rgba(245,158,11,.1); color: var(--accent-amber); }
    .nav-links a.role-admin { color: var(--accent-red); }
    .nav-links a.role-admin:hover { background: rgba(239,68,68,.1); color: var(--accent-red); }
    .nav-links a.btn-logout {
      border: 1px solid var(--accent-red); color: var(--accent-red); margin-left: .35rem;
    }
    .nav-links a.btn-logout:hover { background: rgba(239,68,68,.15); }
    .nav-links a.btn-register {
      background: var(--accent-green); color: #0f1117; font-weight: 600; padding: .45rem 1rem;
    }
    .nav-links a.btn-register:hover { opacity: .85; color: #0f1117; }

    /* ===== HAMBURGER ===== */
    .hamburger {
      display: none; flex-direction: column; justify-content: center;
      gap: 5px; width: 36px; height: 36px;
      background: none; border: 1px solid var(--border-color);
      border-radius: var(--radius); cursor: pointer; padding: 6px;
    }
    .hamburger:hover { border-color: var(--accent-green); }
    .hamburger .bar {
      display: block; width: 100%; height: 2px;
      background: var(--text-primary); border-radius: 2px;
      transition: transform .3s, opacity .3s;
    }
    .hamburger.is-open .bar:nth-child(1) { transform: translateY(7px) rotate(45deg); }
    .hamburger.is-open .bar:nth-child(2) { opacity: 0; }
    .hamburger.is-open .bar:nth-child(3) { transform: translateY(-7px) rotate(-45deg); }

    /* ===== MOBILE MENU ===== */
    .mobile-menu {
      display: none; background: var(--bg-secondary);
      border-bottom: 1px solid var(--border-color);
      padding: .5rem 1rem 1rem;
    }
    .mobile-menu.is-open { display: block; }
    .mobile-menu a {
      display: block; padding: .7rem .85rem;
      color: var(--text-secondary); font-size: .95rem;
      border-radius: var(--radius); transition: background .2s, color .2s;
    }
    .mobile-menu a:hover { background: rgba(57,255,20,.08); color: var(--accent-green); }
    .mobile-menu a.role-mod  { color: var(--accent-amber); }
    .mobile-menu a.role-admin { color: var(--accent-red); }
    .mobile-menu a.btn-logout {
      border: 1px solid var(--accent-red); color: var(--accent-red);
      text-align: center; margin-top: .5rem;
    }
    .mobile-menu a.btn-register {
      background: var(--accent-green); color: #0f1117;
      font-weight: 600; text-align: center; margin-top: .5rem;
    }
    .mobile-menu .divider { height: 1px; background: var(--border-color); margin: .5rem 0; }

    /* ===== LAYOUT ===== */
    .container {
      max-width: 1280px; margin: 0 auto;
      padding: 2rem 2rem; flex: 1;
      width: 100%;
    }

    /* ===== CARDS ===== */
    .card {
      background: var(--bg-card); border: 1px solid var(--border-color);
      border-radius: var(--radius); padding: 1.5rem;
      margin-bottom: 1.5rem; box-shadow: var(--shadow);
      overflow: hidden; /* contain children */
    }
    .card h2, .card h3 { margin-bottom: .75rem; color: var(--accent-green); }

    /* ===== FORM CARD ===== */
    .form-card { width: 100%; max-width: 440px; margin: 2rem auto; }

    /* ===== FORMS ===== */
    .form-group { margin-bottom: 1rem; }
    .form-group label {
      display: block; margin-bottom: .35rem; font-size: .85rem; color: var(--text-secondary);
    }
    .form-group input,
    .form-group select,
    .form-group textarea {
      width: 100%; padding: .6rem .85rem;
      border-radius: var(--radius); border: 1px solid var(--border-color);
      background: var(--bg-secondary); color: var(--text-primary);
      font-size: .95rem; outline: none; transition: border-color .2s;
    }
    .form-group input:focus,
    .form-group select:focus,
    .form-group textarea:focus { border-color: var(--accent-green); }
    .form-group textarea { resize: vertical; min-height: 80px; }

    /* ===== BUTTONS ===== */
    .btn {
      display: inline-block; padding: .6rem 1.4rem;
      border: none; border-radius: var(--radius);
      font-size: .9rem; font-weight: 600; cursor: pointer;
      transition: opacity .2s, transform .1s; text-align: center;
    }
    .btn:active { transform: scale(.97); }
    .btn-primary   { background: var(--accent-green); color: #0f1117; }
    .btn-primary:hover { opacity: .85; color: #0f1117; }
    .btn-danger    { background: var(--accent-red); color: #fff; }
    .btn-danger:hover { opacity: .85; color: #fff; }
    .btn-secondary {
      background: var(--bg-secondary); color: var(--text-primary);
      border: 1px solid var(--border-color);
    }
    .btn-secondary:hover { border-color: var(--accent-green); color: var(--accent-green); }
    .btn-purple    { background: var(--accent-purple); color: #fff; }
    .btn-purple:hover { opacity: .85; color: #fff; }
    .btn-sm        { padding: .35rem .8rem; font-size: .8rem; }

    /* ===== TABLES ===== */
    .table-wrap {
      width: 100%;
      overflow-x: auto;
      -webkit-overflow-scrolling: touch;
      max-width: 100%;
    }
    table { width: 100%; border-collapse: collapse; }
    th, td {
      text-align: left; padding: .65rem .85rem;
      border-bottom: 1px solid var(--border-color); font-size: .88rem;
    }
    th {
      background: var(--bg-secondary); color: var(--accent-green);
      font-weight: 600; text-transform: uppercase;
      font-size: .78rem; letter-spacing: .5px; white-space: nowrap;
    }
    tr:hover td { background: rgba(57,255,20,.03); }

    /* ===== ALERTS ===== */
    .alert {
      padding: .85rem 1.1rem; border-radius: var(--radius);
      margin-bottom: 1rem; font-size: .9rem; border: 1px solid;
      word-wrap: break-word;
    }
    .alert-success { background: rgba(57,255,20,.08); border-color: var(--accent-green); color: var(--accent-green); }
    .alert-error   { background: rgba(239,68,68,.08); border-color: var(--accent-red); color: var(--accent-red); }
    .alert-warn    { background: rgba(245,158,11,.08); border-color: var(--accent-amber); color: var(--accent-amber); }

    /* ===== HERO ===== */
    .hero { text-align: center; padding: 5rem 1rem 3rem; }
    .hero h1 { font-size: 3rem; font-family: var(--font-mono); margin-bottom: 1rem; }
    .hero h1 .green { color: var(--accent-green); }
    .hero h1 .purple { color: var(--accent-purple); }
    .hero p { font-size: 1.15rem; color: var(--text-secondary); max-width: 620px; margin: 0 auto 2rem; }
    .hero-buttons { display: flex; gap: .5rem; justify-content: center; flex-wrap: wrap; }

    /* ===== GRIDS ===== */
    .grid-3 { display: grid; grid-template-columns: repeat(3, 1fr); gap: 1.5rem; }
    .grid-2 { display: grid; grid-template-columns: repeat(2, 1fr); gap: 1.5rem; }

    /* ===== BADGES ===== */
    .badge {
      display: inline-block; padding: .2rem .6rem; border-radius: 20px;
      font-size: .72rem; font-weight: 700; text-transform: uppercase; letter-spacing: .5px;
    }
    .badge-green  { background: rgba(57,255,20,.15); color: var(--accent-green); }
    .badge-red    { background: rgba(239,68,68,.15); color: var(--accent-red); }
    .badge-amber  { background: rgba(245,158,11,.15); color: var(--accent-amber); }
    .badge-purple { background: rgba(168,85,247,.15); color: var(--accent-purple); }
    .badge-blue   { background: rgba(59,130,246,.15); color: var(--accent-blue); }

    .dot { display: inline-block; width: 8px; height: 8px; border-radius: 50%; margin-right: 6px; }
    .dot-green { background: var(--accent-green); box-shadow: 0 0 6px var(--accent-green); }
    .dot-red   { background: var(--accent-red); box-shadow: 0 0 6px var(--accent-red); }
    .dot-amber { background: var(--accent-amber); }

    /* ===== FOOTER ===== */
    .site-footer {
      background: var(--bg-secondary); border-top: 1px solid var(--border-color);
      text-align: center; padding: 1.25rem;
      font-size: .8rem; color: var(--text-secondary); margin-top: auto;
    }

    /* ===== PRICING ===== */
    .pricing-card { text-align: center; position: relative; overflow: hidden; }
    .pricing-card.featured { border-color: var(--accent-green); }
    .pricing-card.featured::before {
      content: 'POPULAR'; position: absolute; top: 12px; right: -30px;
      background: var(--accent-green); color: #000;
      font-size: .65rem; font-weight: 800; padding: 3px 36px; transform: rotate(45deg);
    }
    .pricing-card .price { font-size: 2.5rem; font-weight: 800; color: var(--accent-green); }
    .pricing-card .price span { font-size: .9rem; color: var(--text-secondary); }
    .pricing-card ul { list-style: none; margin: 1rem 0; }
    .pricing-card ul li { padding: .35rem 0; font-size: .9rem; color: var(--text-secondary); }
    .pricing-card ul li::before { content: '✓ '; color: var(--accent-green); font-weight: 700; }

    /* ===== FORUM POST CARD ===== */
    .post-card {
      display: flex; background: var(--bg-card);
      border: 1px solid var(--border-color); border-radius: var(--radius);
      overflow: hidden; margin-bottom: 1rem; box-shadow: var(--shadow);
    }
    .post-sidebar {
      min-width: 170px; max-width: 170px;
      background: var(--bg-secondary); padding: 1rem;
      border-right: 1px solid var(--border-color);
      text-align: center; flex-shrink: 0;
    }
    .post-sidebar .avatar {
      width: 56px; height: 56px; border-radius: 50%;
      background: var(--bg-primary); border: 2px solid var(--border-color);
      margin: 0 auto .5rem;
      display: flex; align-items: center; justify-content: center;
      font-size: 1.3rem; color: var(--accent-green);
    }
    .post-body-wrap { flex: 1; display: flex; flex-direction: column; min-width: 0; }
    .post-meta {
      padding: .5rem 1rem; border-bottom: 1px solid var(--border-color);
      font-size: .78rem; color: var(--text-secondary);
      display: flex; justify-content: space-between; align-items: center;
      flex-wrap: wrap; gap: .3rem;
    }
    .post-content { padding: 1rem; flex: 1; line-height: 1.7; word-wrap: break-word; }
    .post-signature {
      padding: .6rem 1rem; border-top: 1px solid var(--border-color);
      font-size: .78rem; color: var(--text-secondary); font-style: italic;
    }

    .breadcrumb {
      font-size: .85rem; color: var(--text-secondary);
      margin-bottom: 1rem; overflow-x: auto; white-space: nowrap;
    }
    .breadcrumb .sep { margin: 0 .4rem; }

    .pagination { display: flex; gap: .3rem; flex-wrap: wrap; margin: 1rem 0; align-items: center; }

    /* ===== UTILITIES ===== */
    .text-center { text-align: center; }
    .mt-1 { margin-top: 1rem; }
    .mt-2 { margin-top: 2rem; }
    .mb-1 { margin-bottom: 1rem; }
    .mb-2 { margin-bottom: 2rem; }
    .w-full { width: 100%; }
    .flex-between {
      display: flex; justify-content: space-between;
      align-items: center; flex-wrap: wrap; gap: .5rem;
    }
    .mod-actions { display: flex; gap: .25rem; flex-wrap: wrap; }
    .mod-actions form { display: inline; }
    .filter-bar { display: flex; gap: .5rem; align-items: center; flex-wrap: wrap; }
    .filter-bar input[type="text"] {
      padding: .35rem .6rem; border-radius: var(--radius);
      border: 1px solid var(--border-color);
      background: var(--bg-secondary); color: var(--text-primary); font-size: .85rem;
    }
    .log-scroll { max-height: 500px; overflow-y: auto; }

    /* ===== VISIBILITY TOGGLES ===== */
    .desktop-only { display: block; }
    .desktop-only-flex { display: flex; }
    .desktop-only-table { display: table; }
    .mobile-only { display: none; }

    /* ===== MOBILE USER CARD (admin/mod panels) ===== */
    .user-mgmt-card {
      background: var(--bg-card);
      border: 1px solid var(--border-color);
      border-radius: var(--radius);
      margin-bottom: 1rem;
      overflow: hidden;
    }
    .umc-header {
      background: var(--bg-secondary);
      padding: .75rem 1rem;
      display: flex; justify-content: space-between;
      align-items: center; flex-wrap: wrap; gap: .5rem;
      border-bottom: 1px solid var(--border-color);
    }
    .umc-header .umc-name {
      font-weight: 700; font-size: 1rem;
    }
    .umc-body { padding: 1rem; }
    .umc-row {
      display: flex; justify-content: space-between;
      padding: .4rem 0;
      border-bottom: 1px solid rgba(46,49,64,.5);
      font-size: .88rem;
      gap: .5rem;
    }
    .umc-row:last-child { border-bottom: none; }
    .umc-label {
      color: var(--text-secondary);
      font-size: .78rem; text-transform: uppercase;
      letter-spacing: .3px; flex-shrink: 0;
      min-width: 80px;
    }
    .umc-value {
      text-align: right; word-break: break-all;
      min-width: 0; flex: 1;
    }
    .umc-actions {
      padding: .75rem 1rem;
      border-top: 1px solid var(--border-color);
      display: flex; flex-direction: column; gap: .5rem;
    }
    .umc-actions form {
      display: flex; gap: .4rem; flex-wrap: wrap;
      align-items: center;
    }
    .umc-actions select,
    .umc-actions input[type="number"] {
      flex: 1; min-width: 0;
      padding: .4rem .5rem; font-size: .85rem;
      border-radius: var(--radius);
      border: 1px solid var(--border-color);
      background: var(--bg-secondary); color: var(--text-primary);
    }
    .umc-actions .btn { flex-shrink: 0; }
    .umc-btn-row {
      display: flex; gap: .4rem; flex-wrap: wrap;
    }
    .umc-btn-row form { flex: 1; }
    .umc-btn-row .btn { width: 100%; }

    /* ===== HWID CARD (mod panel mobile) ===== */
    .hwid-card {
      background: var(--bg-secondary);
      border: 1px solid var(--border-color);
      border-radius: var(--radius);
      padding: 1rem;
      margin-bottom: .75rem;
    }
    .hwid-card-top {
      display: flex; justify-content: space-between;
      align-items: flex-start; gap: .5rem; margin-bottom: .5rem;
    }

    /* ============================================================
       RESPONSIVE BREAKPOINTS
       ============================================================ */

    @media (max-width: 1024px) {
      .container { padding: 1.5rem 1.25rem; }
    }

    @media (max-width: 900px) {
      .grid-3 { grid-template-columns: repeat(2, 1fr); }
      .hero h1 { font-size: 2.4rem; }
      .hero { padding: 3rem 1rem 2rem; }
    }

    @media (max-width: 680px) {
      .hamburger { display: flex; }
      .nav-links { display: none; }
      .navbar-inner { padding: 0 1rem; }

      .grid-3, .grid-2 { grid-template-columns: 1fr; }
      .container { padding: 1rem .75rem; }
      .hero h1 { font-size: 1.7rem; }
      .hero p  { font-size: .95rem; }
      .hero    { padding: 2.5rem .75rem 1.5rem; }
      .pricing-card .price { font-size: 2rem; }
      .card { padding: 1rem; }
      .btn { font-size: .85rem; padding: .55rem 1rem; }
      .form-card { margin: 1rem auto; }

      /* Toggle visibility: hide tables, show cards */
      .desktop-only,
      .desktop-only-flex { display: none !important; }
      .desktop-only-table { display: none !important; }
      .mobile-only { display: block !important; }

      /* Forum posts stack */
      .post-card { flex-direction: column; }
      .post-sidebar {
        max-width: none; min-width: 0;
        flex-direction: row; display: flex;
        align-items: center; gap: .75rem;
        padding: .75rem 1rem;
        border-right: none;
        border-bottom: 1px solid var(--border-color);
        text-align: left;
      }
      .post-sidebar .avatar {
        width: 40px; height: 40px; font-size: 1rem; margin: 0; flex-shrink: 0;
      }
      .post-sidebar .sidebar-stats { display: none; }

      .flex-between-mobile {
        flex-direction: column; align-items: flex-start;
      }

      .filter-bar {
        flex-direction: column; align-items: stretch; width: 100%;
      }
      .filter-bar input[type="text"] { width: 100%; }

      .site-footer { padding: 1rem .75rem; font-size: .72rem; }
    }

    @media (max-width: 400px) {
      .brand { font-size: 1.1rem; }
      .hero h1 { font-size: 1.4rem; }
      .container { padding: .75rem .5rem; }
      .card { padding: .75rem; }
    }
  </style>
</head>
<body>

<header class="navbar">
  <div class="navbar-inner">
    <a href="/index.php" class="brand">&gt;<span>OSRS</span>_Client</a>
    <ul class="nav-links">
      <?php if (isLoggedIn()): ?>
        <li><a href="/dashboard.php">Dashboard</a></li>
        <li><a href="/forum/index.php">Forum</a></li>
        <li><a href="/settings.php">Settings</a></li>
        <?php if (hasRole('moderator')): ?>
          <li><a href="/mod/index.php" class="role-mod">Mod Panel</a></li>
        <?php endif; ?>
        <?php if (hasRole('admin')): ?>
          <li><a href="/admin/index.php" class="role-admin">Admin Panel</a></li>
        <?php endif; ?>
        <li><a href="/logout.php" class="btn-logout">Logout</a></li>
      <?php else: ?>
        <li><a href="/login.php">Login</a></li>
        <li><a href="/register.php" class="btn-register">Register</a></li>
      <?php endif; ?>
    </ul>
    <button class="hamburger" id="hamburgerBtn" aria-label="Toggle navigation">
      <span class="bar"></span><span class="bar"></span><span class="bar"></span>
    </button>
  </div>
  <nav class="mobile-menu" id="mobileMenu">
    <?php if (isLoggedIn()): ?>
      <a href="/dashboard.php">📊 Dashboard</a>
      <a href="/forum/index.php">💬 Forum</a>
      <a href="/settings.php">⚙️ Settings</a>
      <div class="divider"></div>
      <?php if (hasRole('moderator')): ?>
        <a href="/mod/index.php" class="role-mod">🛡️ Mod Panel</a>
      <?php endif; ?>
      <?php if (hasRole('admin')): ?>
        <a href="/admin/index.php" class="role-admin">⚙️ Admin Panel</a>
      <?php endif; ?>
      <?php if (hasRole('moderator')): ?><div class="divider"></div><?php endif; ?>
      <a href="/logout.php" class="btn-logout">Logout</a>
    <?php else: ?>
      <a href="/login.php">🔐 Login</a>
      <a href="/register.php" class="btn-register">📝 Register</a>
    <?php endif; ?>
  </nav>
</header>

<main class="container">

<script>
(function(){
  var b=document.getElementById('hamburgerBtn'),m=document.getElementById('mobileMenu');
  b.addEventListener('click',function(){b.classList.toggle('is-open');m.classList.toggle('is-open');});
  m.querySelectorAll('a').forEach(function(a){a.addEventListener('click',function(){
    b.classList.remove('is-open');m.classList.remove('is-open');
  });});
})();
</script>