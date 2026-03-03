<?php
/**
 * Helper Functions — V5 (New Roles & Punishments)
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

/**
 * Role hierarchy:
 *   user(1) < scripter(2) < forum_mod(3) < moderator(4) < admin(5)
 */
function getRoleLevel(string $role): int
{
    $h = [
        'user'      => 1,
        'scripter'  => 2,
        'forum_mod' => 3,
        'moderator' => 4,
        'admin'     => 5,
    ];
    return $h[$role] ?? 0;
}

function hasRole(string $role): bool
{
    if (!isLoggedIn()) return false;
    return getRoleLevel($_SESSION['role'] ?? 'user') >= getRoleLevel($role);
}

function hasExactRole(string $role): bool
{
    if (!isLoggedIn()) return false;
    return ($_SESSION['role'] ?? 'user') === $role;
}

/**
 * Can the current user moderate the target user?
 * Rule: You can only moderate users whose role level is below yours.
 * Admins can moderate everyone. Mods cannot moderate admins.
 */
function canModerate(string $targetRole): bool
{
    if (!isLoggedIn()) return false;
    $myLevel     = getRoleLevel($_SESSION['role'] ?? 'user');
    $targetLevel = getRoleLevel($targetRole);
    return $myLevel > $targetLevel;
}

/**
 * Can the current user issue forum punishments?
 * Forum Mods, Mods, and Admins can.
 */
function canIssuePunishments(): bool
{
    return hasRole('forum_mod');
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
    $pdo->prepare('INSERT INTO `logs` (`user_id`,`action`,`ip_address`) VALUES (?,?,?)')
        ->execute([$userId, $action, $ip]);
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

function settingEnabled(PDO $pdo, string $key): bool
{
    return getSetting($pdo, $key, '0') === '1';
}

function getAllSettings(PDO $pdo): array
{
    return $pdo->query('SELECT * FROM `site_settings` ORDER BY `category` ASC, `sort_order` ASC')->fetchAll();
}

/* ========== ONLINE TRACKING ========== */

function updateLastSeen(PDO $pdo): void
{
    if (!isLoggedIn()) return;
    $pdo->prepare('UPDATE `users` SET `last_seen_at` = NOW() WHERE `user_id` = ?')
        ->execute([$_SESSION['user_id']]);
}

function getOnlineUsers(PDO $pdo): array
{
    $mins = (int)getSetting($pdo, 'online_threshold_min', '15');
    $stmt = $pdo->prepare(
        'SELECT `user_id`,`username`,`role`,`avatar_path`
         FROM `users`
         WHERE `last_seen_at` >= DATE_SUB(NOW(), INTERVAL ? MINUTE)
         ORDER BY `last_seen_at` DESC'
    );
    $stmt->execute([$mins]);
    return $stmt->fetchAll();
}

/* ========== AVATARS ========== */

function getAvatarUrl(PDO $pdo, ?string $avatarPath, string $username): string
{
    if (settingEnabled($pdo, 'forum_avatars') && $avatarPath && file_exists(__DIR__ . '/../' . $avatarPath)) {
        return '/' . $avatarPath;
    }
    return '';
}

function processAvatarUpload(PDO $pdo, array $file, int $userId, string &$error): ?string
{
    $maxKB    = (int)getSetting($pdo, 'avatar_max_size_kb', '2048');
    $maxBytes = $maxKB * 1024;
    $allowed  = ['image/jpeg','image/png','image/gif','image/webp'];
    $extMap   = ['image/jpeg'=>'jpg','image/png'=>'png','image/gif'=>'gif','image/webp'=>'webp'];

    if ($file['error'] !== UPLOAD_ERR_OK) { $error = 'Upload failed.'; return null; }
    if ($file['size'] > $maxBytes)        { $error = "Max {$maxKB}KB."; return null; }

    $mime = mime_content_type($file['tmp_name']);
    if (!in_array($mime, $allowed))       { $error = 'JPG/PNG/GIF/WebP only.'; return null; }
    if (!@getimagesize($file['tmp_name'])){ $error = 'Invalid image.'; return null; }

    $ext = $extMap[$mime] ?? 'jpg';
    $dir = __DIR__ . '/../uploads/avatars';
    if (!is_dir($dir)) mkdir($dir, 0755, true);
    foreach (glob("$dir/{$userId}.*") as $old) @unlink($old);

    $filename = $userId . '.' . $ext;
    if (!move_uploaded_file($file['tmp_name'], "$dir/$filename")) { $error = 'Save failed.'; return null; }

    $rel = 'uploads/avatars/' . $filename;
    $pdo->prepare('UPDATE `users` SET `avatar_path`=? WHERE `user_id`=?')->execute([$rel, $userId]);
    return $rel;
}

/* ========== MAINTENANCE ========== */

function checkMaintenance(PDO $pdo): void
{
    if (settingEnabled($pdo, 'maintenance_mode') && !hasRole('admin')) {
        http_response_code(503);
        exit('<html><head><title>Maintenance</title><style>body{background:#0f1117;color:#e4e4e7;font-family:sans-serif;display:flex;align-items:center;justify-content:center;min-height:100vh;text-align:center;}h1{color:#39ff14;font-family:monospace;}</style></head><body><div><h1>>OS_Auto</h1><p>Maintenance in progress.</p></div></body></html>');
    }
}

/* ========== PUNISHMENT SYSTEM ========== */

/**
 * Check if a user is currently muted.
 * Returns the active punishment row, or false.
 */
function isUserMuted(PDO $pdo, int $userId): array|false
{
    /* Expire old punishments first */
    $pdo->prepare(
        "UPDATE `forum_punishments`
         SET `is_active` = 0
         WHERE `user_id` = ? AND `type` = 'mute' AND `is_active` = 1
           AND `expires_at` IS NOT NULL AND `expires_at` <= NOW()"
    )->execute([$userId]);

    $stmt = $pdo->prepare(
        "SELECT * FROM `forum_punishments`
         WHERE `user_id` = ? AND `type` = 'mute' AND `is_active` = 1
         ORDER BY `created_at` DESC LIMIT 1"
    );
    $stmt->execute([$userId]);
    return $stmt->fetch() ?: false;
}

/**
 * Check if a user is forum-banned.
 */
function isUserForumBanned(PDO $pdo, int $userId): array|false
{
    $pdo->prepare(
        "UPDATE `forum_punishments`
         SET `is_active` = 0
         WHERE `user_id` = ? AND `type` = 'forum_ban' AND `is_active` = 1
           AND `expires_at` IS NOT NULL AND `expires_at` <= NOW()"
    )->execute([$userId]);

    $stmt = $pdo->prepare(
        "SELECT * FROM `forum_punishments`
         WHERE `user_id` = ? AND `type` = 'forum_ban' AND `is_active` = 1
         ORDER BY `created_at` DESC LIMIT 1"
    );
    $stmt->execute([$userId]);
    return $stmt->fetch() ?: false;
}

/**
 * Get active punishment count for a user.
 */
function getPunishmentCount(PDO $pdo, int $userId): int
{
    $stmt = $pdo->prepare(
        "SELECT COUNT(*) FROM `forum_punishments`
         WHERE `user_id` = ? AND `is_active` = 1
           AND (`expires_at` IS NULL OR `expires_at` > NOW())"
    );
    $stmt->execute([$userId]);
    return (int)$stmt->fetchColumn();
}

/**
 * Get punishment history for a user.
 */
function getPunishmentHistory(PDO $pdo, int $userId): array
{
    $stmt = $pdo->prepare(
        'SELECT fp.*, u.username AS issued_by_name, ru.username AS revoked_by_name
         FROM `forum_punishments` fp
         JOIN `users` u ON u.user_id = fp.issued_by
         LEFT JOIN `users` ru ON ru.user_id = fp.revoked_by
         WHERE fp.user_id = ?
         ORDER BY fp.created_at DESC'
    );
    $stmt->execute([$userId]);
    return $stmt->fetchAll();
}

/**
 * Issue a punishment.
 */
function issuePunishment(
    PDO    $pdo,
    int    $targetUserId,
    string $type,
    ?string $reason,
    int    $issuedBy,
    ?string $expiresAt
): int {
    $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';

    $stmt = $pdo->prepare(
        'INSERT INTO `forum_punishments`
         (`user_id`,`type`,`reason`,`issued_by`,`expires_at`,`ip_address`)
         VALUES (?,?,?,?,?,?)'
    );
    $stmt->execute([$targetUserId, $type, $reason, $issuedBy, $expiresAt, $ip]);
    $id = (int)$pdo->lastInsertId();

    logAction($pdo, $issuedBy, "$type:user:$targetUserId:punishment:$id");
    return $id;
}

/**
 * Revoke a punishment.
 */
function revokePunishment(PDO $pdo, int $punishmentId, int $revokedBy): bool
{
    $stmt = $pdo->prepare(
        'UPDATE `forum_punishments`
         SET `is_active` = 0, `revoked_by` = ?, `revoked_at` = NOW()
         WHERE `punishment_id` = ? AND `is_active` = 1'
    );
    $stmt->execute([$revokedBy, $punishmentId]);
    logAction($pdo, $revokedBy, "revoke_punishment:$punishmentId");
    return $stmt->rowCount() > 0;
}

/**
 * Enforce forum access — call at top of every forum page.
 * Redirects if user is forum-banned.
 */
function requireForumAccess(PDO $pdo): void
{
    requireLogin();
    if (!settingEnabled($pdo, 'forum_enabled')) redirect('/dashboard.php');

    $ban = isUserForumBanned($pdo, $_SESSION['user_id']);
    if ($ban) {
        $pageTitle = 'Forum Banned';
        require_once __DIR__ . '/header.php';
        echo '<div class="card" style="max-width:600px;margin:2rem auto;text-align:center;">';
        echo '<h2 style="color:var(--accent-red);">🚫 Forum Access Denied</h2>';
        echo '<p style="margin:.75rem 0;">You have been banned from the forum.</p>';
        echo '<p style="color:var(--text-secondary);font-size:.88rem;">Reason: <strong>' . e($ban['reason'] ?? 'No reason provided') . '</strong></p>';
        if ($ban['expires_at']) {
            echo '<p style="color:var(--text-secondary);font-size:.88rem;">Expires: <strong>' . e($ban['expires_at']) . '</strong></p>';
        } else {
            echo '<p style="color:var(--accent-red);font-size:.88rem;">This ban is <strong>permanent</strong>.</p>';
        }
        echo '<a href="/dashboard.php" class="btn btn-secondary mt-1">&larr; Dashboard</a>';
        echo '</div>';
        require_once __DIR__ . '/footer.php';
        exit;
    }
}

/* ========== ROLE DISPLAY HELPERS ========== */

function roleBadgeClass(string $role): string
{
    return match($role) {
        'admin'     => 'badge-red',
        'moderator' => 'badge-amber',
        'forum_mod' => 'badge-purple',
        'scripter'  => 'badge-green',
        default     => 'badge-blue',
    };
}

function roleDisplayName(string $role): string
{
    return match($role) {
        'forum_mod' => 'Forum Mod',
        default     => ucfirst($role),
    };
}

/** All valid roles for dropdowns */
function getAllRoles(): array
{
    return ['user', 'scripter', 'forum_mod', 'moderator', 'admin'];
}

/**
 * Roles that the current user is allowed to assign.
 * Mods can't promote to admin. Admins can assign anything.
 */
function getAssignableRoles(): array
{
    if (hasRole('admin')) return getAllRoles();
    if (hasRole('moderator')) return ['user', 'scripter', 'forum_mod', 'moderator'];
    return [];
}