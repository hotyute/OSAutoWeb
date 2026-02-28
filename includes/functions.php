<?php
/**
 * Helper / Utility Functions
 * --------------------------------------------------------
 * Centralised security helpers used across the entire app:
 *   • Session bootstrap
 *   • CSRF token generation & validation
 *   • Role-based access checks
 *   • XSS-safe output helper
 *   • Audit logging
 */

/* ---------- SESSION BOOTSTRAP ---------- */
function initSession(): void
{
    if (session_status() === PHP_SESSION_NONE) {
        session_start([
            'cookie_httponly' => true,
            'cookie_samesite' => 'Strict',
            'use_strict_mode' => true,
        ]);
    }
}

/* ---------- AUTH CHECKS ---------- */

/** Is the visitor logged in? */
function isLoggedIn(): bool
{
    initSession();
    return isset($_SESSION['user_id']);
}

/** Does the logged-in user hold a specific role (or higher)? */
function hasRole(string $role): bool
{
    if (!isLoggedIn()) return false;

    $hierarchy = ['user' => 1, 'moderator' => 2, 'admin' => 3];
    $userLevel = $hierarchy[$_SESSION['role'] ?? 'user'] ?? 0;
    $required  = $hierarchy[$role] ?? 99;

    return $userLevel >= $required;
}

/**
 * Enforce authentication — redirect to login if not logged in.
 */
function requireLogin(): void
{
    if (!isLoggedIn()) {
        header('Location: /login.php');
        exit;
    }
}

/**
 * Enforce minimum role — returns 403 if the user lacks permission.
 * Call AFTER requireLogin().
 */
function requireRole(string $role): void
{
    requireLogin();
    if (!hasRole($role)) {
        http_response_code(403);
        exit('<h1>403 — Forbidden</h1><p>You do not have access to this area.</p>');
    }
}

/* ---------- CSRF PROTECTION ---------- */

/** Generate (or return existing) CSRF token for the current session. */
function generateCSRF(): string
{
    initSession();
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/** Validate a submitted CSRF token. */
function validateCSRF(string $token): bool
{
    initSession();
    return isset($_SESSION['csrf_token'])
        && hash_equals($_SESSION['csrf_token'], $token);
}

/* ---------- OUTPUT HELPERS ---------- */

/** XSS-safe echo wrapper. */
function e(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

/* ---------- AUDIT LOGGING ---------- */

/**
 * Write an entry to the `logs` table.
 * $userId may be null for anonymous events (failed logins, etc.).
 */
function logAction(PDO $pdo, ?int $userId, string $action): void
{
    $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    $stmt = $pdo->prepare(
        'INSERT INTO `logs` (`user_id`, `action`, `ip_address`) VALUES (?, ?, ?)'
    );
    $stmt->execute([$userId, $action, $ip]);
}

/* ---------- MISC ---------- */

/** Fetch current user row from DB (cached in session on first call). */
function currentUser(PDO $pdo): ?array
{
    if (!isLoggedIn()) return null;

    $stmt = $pdo->prepare('SELECT * FROM `users` WHERE `user_id` = ?');
    $stmt->execute([$_SESSION['user_id']]);
    return $stmt->fetch() ?: null;
}

/** Redirect shortcut */
function redirect(string $url): never
{
    header("Location: $url");
    exit;
}