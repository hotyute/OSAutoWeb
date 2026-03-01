<?php
/**
 * Hero & Screenshot Management API
 * --------------------------------------------------------
 * POST-only AJAX endpoint. Returns JSON.
 * Access: Admin only.
 *
 * Actions:
 *   upload_hero_bg       — Upload/replace hero background image
 *   remove_hero_bg       — Remove hero background
 *   update_hero_text     — Update heading & subtext
 *   add_screenshot       — Upload new gallery screenshot
 *   edit_screenshot      — Update title/description
 *   delete_screenshot    — Remove a screenshot
 *   reorder_screenshots  — Bulk update sort_order
 */

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
initSession();

/* Admin-only gate */
if (!hasRole('admin')) {
    http_response_code(403);
    echo json_encode(['status' => 'error', 'message' => 'Forbidden']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['status' => 'error', 'message' => 'POST only']);
    exit;
}

/* CSRF check */
$csrfToken = $_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
if (!validateCSRF($csrfToken)) {
    http_response_code(403);
    echo json_encode(['status' => 'error', 'message' => 'CSRF validation failed']);
    exit;
}

$action = $_POST['action'] ?? '';

/* Helper: process image upload */
function handleImageUpload(string $fieldName, string $subDir, string $prefix = ''): array
{
    if (!isset($_FILES[$fieldName]) || $_FILES[$fieldName]['error'] === UPLOAD_ERR_NO_FILE) {
        return ['ok' => false, 'error' => 'No file uploaded.'];
    }

    $file     = $_FILES[$fieldName];
    $maxBytes = 5 * 1024 * 1024; /* 5MB */
    $allowed  = ['image/jpeg','image/png','image/gif','image/webp'];
    $extMap   = ['image/jpeg'=>'jpg','image/png'=>'png','image/gif'=>'gif','image/webp'=>'webp'];

    if ($file['error'] !== UPLOAD_ERR_OK) {
        return ['ok' => false, 'error' => 'Upload error code: ' . $file['error']];
    }
    if ($file['size'] > $maxBytes) {
        return ['ok' => false, 'error' => 'File too large. Max 5MB.'];
    }

    $mime = mime_content_type($file['tmp_name']);
    if (!in_array($mime, $allowed)) {
        return ['ok' => false, 'error' => 'Invalid type. JPG, PNG, GIF, WebP only.'];
    }

    $imgInfo = @getimagesize($file['tmp_name']);
    if (!$imgInfo) {
        return ['ok' => false, 'error' => 'Not a valid image.'];
    }

    $ext = $extMap[$mime] ?? 'jpg';
    $dir = __DIR__ . '/../uploads/' . $subDir;
    if (!is_dir($dir)) mkdir($dir, 0755, true);

    $filename = ($prefix ?: uniqid('img_', true)) . '.' . $ext;
    $destPath = $dir . '/' . $filename;

    if (!move_uploaded_file($file['tmp_name'], $destPath)) {
        return ['ok' => false, 'error' => 'Failed to save file.'];
    }

    return ['ok' => true, 'path' => 'uploads/' . $subDir . '/' . $filename];
}

switch ($action) {

    /* ========== HERO BACKGROUND ========== */
    case 'upload_hero_bg':
        /* Remove old file if exists */
        $oldPath = getSetting($pdo, 'hero_bg_image', '');
        if ($oldPath && file_exists(__DIR__ . '/../' . $oldPath)) {
            @unlink(__DIR__ . '/../' . $oldPath);
        }

        $result = handleImageUpload('hero_bg', 'hero', 'hero_bg_' . time());
        if (!$result['ok']) {
            echo json_encode(['status' => 'error', 'message' => $result['error']]);
            exit;
        }

        $pdo->prepare("UPDATE `site_settings` SET `setting_value` = ? WHERE `setting_key` = 'hero_bg_image'")
            ->execute([$result['path']]);

        logAction($pdo, $_SESSION['user_id'], 'admin:hero_bg_updated');
        echo json_encode(['status' => 'success', 'path' => '/' . $result['path']]);
        break;

    case 'remove_hero_bg':
        $oldPath = getSetting($pdo, 'hero_bg_image', '');
        if ($oldPath && file_exists(__DIR__ . '/../' . $oldPath)) {
            @unlink(__DIR__ . '/../' . $oldPath);
        }
        $pdo->prepare("UPDATE `site_settings` SET `setting_value` = '' WHERE `setting_key` = 'hero_bg_image'")
            ->execute();
        logAction($pdo, $_SESSION['user_id'], 'admin:hero_bg_removed');
        echo json_encode(['status' => 'success']);
        break;

    /* ========== HERO TEXT ========== */
    case 'update_hero_text':
        $heading = trim($_POST['hero_heading'] ?? '');
        $subtext = trim($_POST['hero_subtext'] ?? '');

        $pdo->prepare("UPDATE `site_settings` SET `setting_value` = ? WHERE `setting_key` = 'hero_heading'")
            ->execute([$heading]);
        $pdo->prepare("UPDATE `site_settings` SET `setting_value` = ? WHERE `setting_key` = 'hero_subtext'")
            ->execute([$subtext]);

        logAction($pdo, $_SESSION['user_id'], 'admin:hero_text_updated');
        echo json_encode(['status' => 'success']);
        break;

    /* ========== SCREENSHOTS ========== */
    case 'add_screenshot':
        $title = trim($_POST['title'] ?? '');
        $desc  = trim($_POST['description'] ?? '');

        if ($title === '') {
            echo json_encode(['status' => 'error', 'message' => 'Title required.']);
            exit;
        }

        $result = handleImageUpload('screenshot', 'screenshots', 'ss_' . time() . '_' . mt_rand(1000,9999));
        if (!$result['ok']) {
            echo json_encode(['status' => 'error', 'message' => $result['error']]);
            exit;
        }

        /* Get next sort order */
        $maxSort = (int)$pdo->query('SELECT COALESCE(MAX(sort_order),0) FROM hero_screenshots')->fetchColumn();

        $pdo->prepare(
            'INSERT INTO `hero_screenshots` (`title`,`description`,`image_path`,`sort_order`) VALUES (?,?,?,?)'
        )->execute([$title, $desc ?: null, $result['path'], $maxSort + 1]);

        $newId = (int)$pdo->lastInsertId();
        logAction($pdo, $_SESSION['user_id'], "admin:screenshot_added:$newId");

        echo json_encode([
            'status' => 'success',
            'screenshot' => [
                'id'    => $newId,
                'title' => $title,
                'desc'  => $desc,
                'path'  => '/' . $result['path'],
                'sort'  => $maxSort + 1,
            ]
        ]);
        break;

    case 'edit_screenshot':
        $id    = (int)($_POST['screenshot_id'] ?? 0);
        $title = trim($_POST['title'] ?? '');
        $desc  = trim($_POST['description'] ?? '');

        if ($id < 1 || $title === '') {
            echo json_encode(['status' => 'error', 'message' => 'Invalid data.']);
            exit;
        }

        /* Optionally replace image */
        if (isset($_FILES['screenshot']) && $_FILES['screenshot']['error'] !== UPLOAD_ERR_NO_FILE) {
            /* Delete old image */
            $old = $pdo->prepare('SELECT image_path FROM hero_screenshots WHERE screenshot_id=?');
            $old->execute([$id]);
            $oldRow = $old->fetch();
            if ($oldRow && file_exists(__DIR__ . '/../' . $oldRow['image_path'])) {
                @unlink(__DIR__ . '/../' . $oldRow['image_path']);
            }

            $result = handleImageUpload('screenshot', 'screenshots', 'ss_' . time() . '_' . mt_rand(1000,9999));
            if (!$result['ok']) {
                echo json_encode(['status' => 'error', 'message' => $result['error']]);
                exit;
            }
            $pdo->prepare('UPDATE hero_screenshots SET title=?, description=?, image_path=? WHERE screenshot_id=?')
                ->execute([$title, $desc ?: null, $result['path'], $id]);
        } else {
            $pdo->prepare('UPDATE hero_screenshots SET title=?, description=? WHERE screenshot_id=?')
                ->execute([$title, $desc ?: null, $id]);
        }

        logAction($pdo, $_SESSION['user_id'], "admin:screenshot_edited:$id");
        echo json_encode(['status' => 'success']);
        break;

    case 'delete_screenshot':
        $id = (int)($_POST['screenshot_id'] ?? 0);
        if ($id < 1) {
            echo json_encode(['status' => 'error', 'message' => 'Invalid ID.']);
            exit;
        }

        $old = $pdo->prepare('SELECT image_path FROM hero_screenshots WHERE screenshot_id=?');
        $old->execute([$id]);
        $oldRow = $old->fetch();
        if ($oldRow && file_exists(__DIR__ . '/../' . $oldRow['image_path'])) {
            @unlink(__DIR__ . '/../' . $oldRow['image_path']);
        }

        $pdo->prepare('DELETE FROM hero_screenshots WHERE screenshot_id=?')->execute([$id]);
        logAction($pdo, $_SESSION['user_id'], "admin:screenshot_deleted:$id");
        echo json_encode(['status' => 'success']);
        break;

    case 'reorder_screenshots':
        $order = $_POST['order'] ?? [];
        if (is_string($order)) $order = json_decode($order, true);
        if (!is_array($order)) {
            echo json_encode(['status' => 'error', 'message' => 'Invalid order data.']);
            exit;
        }

        $stmt = $pdo->prepare('UPDATE hero_screenshots SET sort_order=? WHERE screenshot_id=?');
        foreach ($order as $pos => $ssId) {
            $stmt->execute([(int)$pos, (int)$ssId]);
        }

        echo json_encode(['status' => 'success']);
        break;

    default:
        echo json_encode(['status' => 'error', 'message' => 'Unknown action.']);
        break;
}