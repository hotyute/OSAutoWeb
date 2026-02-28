<?php
/**
 * Global Header
 * --------------------------------------------------------
 * Includes the <head>, inline CSS (dark gamer aesthetic),
 * and the responsive navigation bar.
 * Nav items are conditionally rendered based on user role.
 */

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
    /* ===== CSS RESET & VARIABLES ===== */
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
    }

    body {
      font-family: var(--font-main);
      background: var(--bg-primary);
      color: var(--text-primary);
      line-height: 1.6;
      min-height: 100vh;
      display: flex;
      flex-direction: column;
    }

    a { color: var(--accent-green); text-decoration: none; transition: color .2s; }
    a:hover { color: #6fff50; }

    /* ===== NAVBAR ===== */
    .navbar {
      background: var(--bg-secondary);
      border-bottom: 1px solid var(--border-color);
      padding: .75rem 2rem;
      display: flex;
      align-items: center;
      justify-content: space-between;
      position: sticky;
      top: 0;
      z-index: 1000;
    }
    .navbar .brand {
      font-size: 1.4rem;
      font-weight: 700;
      color: var(--accent-green);
      font-family: var(--font-mono);
      letter-spacing: 1px;
    }
    .navbar .brand span { color: var(--accent-purple); }
    .navbar nav { display: flex; gap: 1.25rem; align-items: center; }
    .navbar nav a {
      color: var(--text-secondary);
      font-size: .9rem;
      padding: .4rem .75rem;
      border-radius: var(--radius);
      transition: background .2s, color .2s;
    }
    .navbar nav a:hover,
    .navbar nav a.active {
      background: rgba(57,255,20,.08);
      color: var(--accent-green);
    }
    .navbar nav a.role-mod  { color: var(--accent-amber); }
    .navbar nav a.role-mod:hover  { background: rgba(245,158,11,.1); }
    .navbar nav a.role-admin { color: var(--accent-red); }
    .navbar nav a.role-admin:hover { background: rgba(239,68,68,.1); }
    .navbar nav a.btn-logout {
      border: 1px solid var(--accent-red);
      color: var(--accent-red);
    }
    .navbar nav a.btn-logout:hover { background: rgba(239,68,68,.15); }

    /* ===== LAYOUT ===== */
    .container { max-width: 1100px; margin: 0 auto; padding: 2rem 1.5rem; flex: 1; }

    /* ===== CARDS ===== */
    .card {
      background: var(--bg-card);
      border: 1px solid var(--border-color);
      border-radius: var(--radius);
      padding: 1.5rem;
      margin-bottom: 1.5rem;
      box-shadow: var(--shadow);
    }
    .card h2, .card h3 {
      margin-bottom: .75rem;
      color: var(--accent-green);
    }

    /* ===== FORMS ===== */
    .form-group { margin-bottom: 1rem; }
    .form-group label {
      display: block;
      margin-bottom: .35rem;
      font-size: .85rem;
      color: var(--text-secondary);
    }
    .form-group input,
    .form-group select,
    .form-group textarea {
      width: 100%;
      padding: .6rem .85rem;
      border-radius: var(--radius);
      border: 1px solid var(--border-color);
      background: var(--bg-secondary);
      color: var(--text-primary);
      font-size: .95rem;
      outline: none;
      transition: border-color .2s;
    }
    .form-group input:focus,
    .form-group select:focus,
    .form-group textarea:focus {
      border-color: var(--accent-green);
    }
    .form-group textarea { resize: vertical; min-height: 80px; }

    /* ===== BUTTONS ===== */
    .btn {
      display: inline-block;
      padding: .6rem 1.4rem;
      border: none;
      border-radius: var(--radius);
      font-size: .9rem;
      font-weight: 600;
      cursor: pointer;
      transition: opacity .2s, transform .1s;
      text-align: center;
    }
    .btn:active { transform: scale(.97); }
    .btn-primary {
      background: var(--accent-green);
      color: #0f1117;
    }
    .btn-primary:hover { opacity: .85; color: #0f1117; }
    .btn-danger {
      background: var(--accent-red);
      color: #fff;
    }
    .btn-danger:hover { opacity: .85; color: #fff; }
    .btn-secondary {
      background: var(--bg-secondary);
      color: var(--text-primary);
      border: 1px solid var(--border-color);
    }
    .btn-secondary:hover { border-color: var(--accent-green); color: var(--accent-green); }
    .btn-purple {
      background: var(--accent-purple);
      color: #fff;
    }
    .btn-purple:hover { opacity: .85; color: #fff; }
    .btn-sm { padding: .35rem .8rem; font-size: .8rem; }

    /* ===== TABLES ===== */
    table { width: 100%; border-collapse: collapse; }
    th, td {
      text-align: left;
      padding: .65rem .85rem;
      border-bottom: 1px solid var(--border-color);
      font-size: .88rem;
    }
    th {
      background: var(--bg-secondary);
      color: var(--accent-green);
      font-weight: 600;
      text-transform: uppercase;
      font-size: .78rem;
      letter-spacing: .5px;
    }
    tr:hover td { background: rgba(57,255,20,.03); }

    /* ===== ALERTS ===== */
    .alert {
      padding: .85rem 1.1rem;
      border-radius: var(--radius);
      margin-bottom: 1rem;
      font-size: .9rem;
      border: 1px solid;
    }
    .alert-success {
      background: rgba(57,255,20,.08);
      border-color: var(--accent-green);
      color: var(--accent-green);
    }
    .alert-error {
      background: rgba(239,68,68,.08);
      border-color: var(--accent-red);
      color: var(--accent-red);
    }
    .alert-warn {
      background: rgba(245,158,11,.08);
      border-color: var(--accent-amber);
      color: var(--accent-amber);
    }

    /* ===== HERO ===== */
    .hero {
      text-align: center;
      padding: 5rem 1rem 3rem;
    }
    .hero h1 {
      font-size: 3rem;
      font-family: var(--font-mono);
      margin-bottom: 1rem;
    }
    .hero h1 .green { color: var(--accent-green); }
    .hero h1 .purple { color: var(--accent-purple); }
    .hero p { font-size: 1.15rem; color: var(--text-secondary); max-width: 620px; margin: 0 auto 2rem; }

    /* ===== FEATURES GRID ===== */
    .grid-3 {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
      gap: 1.5rem;
    }

    /* ===== BADGES ===== */
    .badge {
      display: inline-block;
      padding: .2rem .6rem;
      border-radius: 20px;
      font-size: .72rem;
      font-weight: 700;
      text-transform: uppercase;
      letter-spacing: .5px;
    }
    .badge-green  { background: rgba(57,255,20,.15); color: var(--accent-green); }
    .badge-red    { background: rgba(239,68,68,.15); color: var(--accent-red); }
    .badge-amber  { background: rgba(245,158,11,.15); color: var(--accent-amber); }
    .badge-purple { background: rgba(168,85,247,.15); color: var(--accent-purple); }
    .badge-blue   { background: rgba(59,130,246,.15); color: var(--accent-blue); }

    /* ===== STATUS DOT ===== */
    .dot { display: inline-block; width: 8px; height: 8px; border-radius: 50%; margin-right: 6px; }
    .dot-green  { background: var(--accent-green); box-shadow: 0 0 6px var(--accent-green); }
    .dot-red    { background: var(--accent-red); box-shadow: 0 0 6px var(--accent-red); }
    .dot-amber  { background: var(--accent-amber); }

    /* ===== FOOTER ===== */
    .site-footer {
      background: var(--bg-secondary);
      border-top: 1px solid var(--border-color);
      text-align: center;
      padding: 1.25rem;
      font-size: .8rem;
      color: var(--text-secondary);
      margin-top: auto;
    }

    /* ===== PRICING ===== */
    .pricing-card { text-align: center; position: relative; overflow: hidden; }
    .pricing-card.featured { border-color: var(--accent-green); }
    .pricing-card.featured::before {
      content: 'POPULAR';
      position: absolute;
      top: 12px; right: -30px;
      background: var(--accent-green);
      color: #000;
      font-size: .65rem;
      font-weight: 800;
      padding: 3px 36px;
      transform: rotate(45deg);
    }
    .pricing-card .price { font-size: 2.5rem; font-weight: 800; color: var(--accent-green); }
    .pricing-card .price span { font-size: .9rem; color: var(--text-secondary); }
    .pricing-card ul { list-style: none; margin: 1rem 0; }
    .pricing-card ul li { padding: .35rem 0; font-size: .9rem; color: var(--text-secondary); }
    .pricing-card ul li::before { content: '✓ '; color: var(--accent-green); font-weight: 700; }

    /* ===== MISC ===== */
    .text-center { text-align: center; }
    .mt-1 { margin-top: 1rem; }
    .mt-2 { margin-top: 2rem; }
    .mb-1 { margin-bottom: 1rem; }
    .mb-2 { margin-bottom: 2rem; }
    .flex-between { display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: .5rem; }

    @media (max-width: 700px) {
      .navbar { flex-direction: column; gap: .75rem; }
      .hero h1 { font-size: 2rem; }
    }
  </style>
</head>
<body>

<!-- ===== NAVIGATION BAR ===== -->
<header class="navbar">
  <a href="/index.php" class="brand">&gt;<span>OS</span>Auto</a>
  <nav>
    <?php if (isLoggedIn()): ?>
      <a href="/dashboard.php">Dashboard</a>

      <?php /* Moderator+ link */ ?>
      <?php if (hasRole('moderator')): ?>
        <a href="/mod/index.php" class="role-mod">Mod Panel</a>
      <?php endif; ?>

      <?php /* Admin-only link */ ?>
      <?php if (hasRole('admin')): ?>
        <a href="/admin/index.php" class="role-admin">Admin Panel</a>
      <?php endif; ?>

      <a href="/logout.php" class="btn-logout">Logout</a>
    <?php else: ?>
      <a href="/login.php">Login</a>
      <a href="/register.php" class="btn btn-primary btn-sm">Register</a>
    <?php endif; ?>
  </nav>
</header>

<main class="container">