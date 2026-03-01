<?php
/**
 * Helper / Utility Functions — V3
 * --------------------------------------------------------
 * All original functions preserved + new:
 *   • getSetting()      — read from site_settings table
 *   • getAllSettings()   — bulk load for admin page
 *   • updateLastSeen()  — touch user's online timestamp
 *   • getOnlineUsers()  — users seen within threshold
 *   • getAvatarUrl()    — resolve avatar path or fallback
 */

/* ========== SESSION ========== */

function initSession(): void
{
    if (session_status() === PHP_SESSION_NONE) {
        session_start([
            'cookie_httponly'  => true,
            'cookie_samesite'  => 'Strict',
            'use_strict_mode'  => true,
        ]);
    }
}

/* ========== AUTH ========== */

function isLoggedIn(): bool
{
    initSession();
    return isset($_SESSION['user_id']);
}

function hasRole(string $role): bool
{
    if (!isLoggedIn()) return false;
    $hierarchy = ['user' => 1, 'moderator' => 2, 'admin' => 3];
    $userLevel = $hierarchy[$_SESSION['role'] ?? 'user'] ?? 0;
    $required  = $hierarchy[$role] ?? 99;
    return $userLevel >= $required;
}

function requireLogin(): void
{
    if (!isLoggedIn()) {
        header('Location: /login.php');
        exit;
    }
}

function requireRole(string $role): void
{
    requireLogin();
    if (!hasRole($role)) {
        http_response_code(403);
        exit('<h1>403 — Forbidden</h1><p>You do not have access.</p>');
    }
}

/* ========== CSRF ========== */

function generateCSRF(): string
{
    initSession();
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function validateCSRF(string $token): bool
{
    initSession();
    return isset($_SESSION['csrf_token'])
        && hash_equals($_SESSION['csrf_token'], $token);
}

/* ========== OUTPUT ========== */

function e(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

/* ========== AUDIT LOG ========== */

function logAction(PDO $pdo, ?int $userId, string $action): void
{
    $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    $stmt = $pdo->prepare(
        'INSERT INTO `logs` (`user_id`,`action`,`ip_address`) VALUES (?,?,?)'
    );
    $stmt->execute([$userId, $action, $ip]);
}

/* ========== USER ========== */

function currentUser(PDO $pdo): ?array
{
    if (!isLoggedIn()) return null;
    $stmt = $pdo->prepare('SELECT * FROM `users` WHERE `user_id` = ?');
    $stmt->execute([$_SESSION['user_id']]);
    return $stmt->fetch() ?: null;
}

function redirect(string $url): never
{
    header("Location: $url");
    exit;
}

/* ========== SITE SETTINGS ========== */

/**
 * Read a single site setting. Cached in a static array per request.
 * Returns the value as string, or $default if not found.
 */
function getSetting(PDO $pdo, string $key, string $default = ''): string
{
    static $cache = [];

    if (isset($cache[$key])) return $cache[$key];

    $stmt = $pdo->prepare('SELECT `setting_value` FROM `site_settings` WHERE `setting_key` = ?');
    $stmt->execute([$key]);
    $row = $stmt->fetch();
    $cache[$key] = $row ? ($row['setting_value'] ?? $default) : $default;
    return $cache[$key];
}

/** Boolean shorthand — returns true if setting is '1' */
function settingEnabled(PDO $pdo, string $key): bool
{
    return getSetting($pdo, $key, '0') === '1';
}

/** Fetch all settings grouped by category for the admin page. */
function getAllSettings(PDO $pdo): array
{
    return $pdo->query(
        'SELECT * FROM `site_settings` ORDER BY `category` ASC, `sort_order` ASC'
    )->fetchAll();
}

/* ========== ONLINE TRACKING ========== */

/**
 * Touch the user's last_seen_at timestamp.
 * Called on every authenticated page load.
 */
function updateLastSeen(PDO $pdo): void
{
    if (!isLoggedIn()) return;
    $pdo->prepare('UPDATE `users` SET `last_seen_at` = NOW() WHERE `user_id` = ?')
        ->execute([$_SESSION['user_id']]);
}

/**
 * Get list of users seen within the online threshold.
 */
function getOnlineUsers(PDO $pdo): array
{
    $mins = (int)getSetting($pdo, 'online_threshold_min', '15');
    $stmt = $pdo->prepare(
        'SELECT `user_id`, `username`, `role`, `avatar_path`
         FROM `users`
         WHERE `last_seen_at` >= DATE_SUB(NOW(), INTERVAL ? MINUTE)
         ORDER BY `last_seen_at` DESC'
    );
    $stmt->execute([$mins]);
    return $stmt->fetchAll();
}

/* ========== AVATARS ========== */

/**
 * Return the URL to a user's avatar, or a generated fallback.
 * Checks if custom avatars are enabled via site settings.
 */
function getAvatarUrl(PDO $pdo, ?string $avatarPath, string $username): string
{
    if (settingEnabled($pdo, 'forum_avatars') && $avatarPath && file_exists(__DIR__ . '/../' . $avatarPath)) {
        return '/' . $avatarPath;
    }
    /* Return empty — caller renders the letter fallback */
    return '';
}

/**
 * Process an avatar upload. Returns the stored path or null on failure.
 * Errors returned via reference.
 */
function processAvatarUpload(PDO $pdo, array $file, int $userId, string &$error): ?string
{
    $maxKB = (int)getSetting($pdo, 'avatar_max_size_kb', '2048');
    $maxBytes = $maxKB * 1024;

    $allowed = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
    $extMap  = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/gif' => 'gif', 'image/webp' => 'webp'];

    if ($file['error'] !== UPLOAD_ERR_OK) {
        $error = 'Upload failed. Error code: ' . $file['error'];
        return null;
    }
    if ($file['size'] > $maxBytes) {
        $error = "File too large. Maximum is {$maxKB}KB.";
        return null;
    }

    $mime = mime_content_type($file['tmp_name']);
    if (!in_array($mime, $allowed)) {
        $error = 'Invalid file type. Allowed: JPG, PNG, GIF, WebP.';
        return null;
    }

    /* Verify it's actually an image */
    $imgInfo = @getimagesize($file['tmp_name']);
    if (!$imgInfo) {
        $error = 'File is not a valid image.';
        return null;
    }

    $ext = $extMap[$mime] ?? 'jpg';
    $dir = __DIR__ . '/../uploads/avatars';
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }

    /* Remove old avatar files for this user */
    foreach (glob("$dir/{$userId}.*") as $old) {
        @unlink($old);
    }

    $filename = $userId . '.' . $ext;
    $destPath = $dir . '/' . $filename;

    if (!move_uploaded_file($file['tmp_name'], $destPath)) {
        $error = 'Failed to save file.';
        return null;
    }

    $relativePath = 'uploads/avatars/' . $filename;

    /* Update DB */
    $pdo->prepare('UPDATE `users` SET `avatar_path` = ? WHERE `user_id` = ?')
        ->execute([$relativePath, $userId]);

    return $relativePath;
}

/* ========== MAINTENANCE MODE CHECK ========== */

function checkMaintenance(PDO $pdo): void
{
    if (settingEnabled($pdo, 'maintenance_mode') && !hasRole('admin')) {
        http_response_code(503);
        exit('
        <html><head><title>Maintenance</title>
        <style>body{background:#0f1117;color:#e4e4e7;font-family:sans-serif;display:flex;align-items:center;justify-content:center;min-height:100vh;text-align:center;}
        h1{color:#39ff14;font-family:monospace;}</style></head>
        <body><div><h1>>OS_Auto</h1><p>We\'re currently performing maintenance. Please check back shortly.</p></div></body></html>');
    }
}