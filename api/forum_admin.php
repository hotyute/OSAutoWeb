<?php
/**
 * Forum Admin API — AJAX Endpoint
 * --------------------------------------------------------
 * Actions:
 *   reorder_categories  — Drag-drop category order
 *   reorder_boards      — Drag-drop board order + cross-category moves
 *   inline_edit_cat     — Edit category name/desc inline
 *   inline_edit_board   — Edit board name/desc inline
 *   quick_add_cat       — Quick-add category
 *   quick_add_board     — Quick-add board
 *   delete_cat          — Delete empty category
 *   delete_board        — Delete empty board
 *   issue_punishment    — Mute/ban/warn a user (forum_mod+)
 *   revoke_punishment   — Lift an active punishment (forum_mod+)
 */

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/forum_helpers.php';
initSession();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['status' => 'error', 'message' => 'POST only']);
    exit;
}

$csrfToken = $_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
if (!validateCSRF($csrfToken)) {
    http_response_code(403);
    echo json_encode(['status' => 'error', 'message' => 'CSRF failed']);
    exit;
}

$action = $_POST['action'] ?? '';

/* ===== PUNISHMENT ACTIONS (forum_mod+) ===== */
if (in_array($action, ['issue_punishment', 'revoke_punishment'])) {

    if (!canIssuePunishments()) {
        http_response_code(403);
        echo json_encode(['status' => 'error', 'message' => 'Insufficient permissions']);
        exit;
    }

    if ($action === 'issue_punishment') {
        $targetId = (int)($_POST['target_user_id'] ?? 0);
        $type     = $_POST['type'] ?? '';
        $reason   = trim($_POST['reason'] ?? '');
        $duration = trim($_POST['duration'] ?? '');

        if ($targetId < 1 || !in_array($type, ['warn', 'mute', 'forum_ban'])) {
            echo json_encode(['status' => 'error', 'message' => 'Invalid parameters.']);
            exit;
        }

        /* Check target role — can't punish equal or higher */
        $tgt = $pdo->prepare('SELECT role FROM users WHERE user_id=?');
        $tgt->execute([$targetId]);
        $tgtRow = $tgt->fetch();
        if (!$tgtRow || !canModerate($tgtRow['role'])) {
            echo json_encode(['status' => 'error', 'message' => 'Cannot punish this user.']);
            exit;
        }

        /* Calculate expiry */
        $expiresAt = null;
        if ($duration !== '' && $duration !== 'permanent') {
            $validDurations = [
                '1h' => '+1 hour', '6h' => '+6 hours', '12h' => '+12 hours',
                '1d' => '+1 day', '3d' => '+3 days', '7d' => '+7 days',
                '14d' => '+14 days', '30d' => '+30 days', '90d' => '+90 days',
            ];
            if (isset($validDurations[$duration])) {
                $expiresAt = date('Y-m-d H:i:s', strtotime($validDurations[$duration]));
            } else {
                echo json_encode(['status' => 'error', 'message' => 'Invalid duration.']);
                exit;
            }
        }

        $id = issuePunishment($pdo, $targetId, $type, $reason ?: null, $_SESSION['user_id'], $expiresAt);

        echo json_encode([
            'status'  => 'success',
            'message' => ucfirst($type) . ' issued.',
            'id'      => $id,
        ]);
        exit;
    }

    if ($action === 'revoke_punishment') {
        $pid = (int)($_POST['punishment_id'] ?? 0);
        if ($pid < 1) {
            echo json_encode(['status' => 'error', 'message' => 'Invalid ID.']);
            exit;
        }
        revokePunishment($pdo, $pid, $_SESSION['user_id']);
        echo json_encode(['status' => 'success', 'message' => 'Punishment revoked.']);
        exit;
    }
}

/* ===== STRUCTURE ACTIONS (admin only) ===== */
if (!hasRole('admin')) {
    http_response_code(403);
    echo json_encode(['status' => 'error', 'message' => 'Admin only']);
    exit;
}

switch ($action) {

    case 'reorder_categories':
        $order = $_POST['order'] ?? [];
        if (is_string($order)) $order = json_decode($order, true);
        if (!is_array($order)) { echo json_encode(['status'=>'error','message'=>'Bad data']); exit; }
        $stmt = $pdo->prepare('UPDATE forum_categories SET sort_order=? WHERE category_id=?');
        foreach ($order as $pos => $id) $stmt->execute([(int)$pos, (int)$id]);
        echo json_encode(['status' => 'success']);
        break;

    case 'reorder_boards':
        $order = $_POST['order'] ?? [];
        if (is_string($order)) $order = json_decode($order, true);
        if (!is_array($order)) { echo json_encode(['status'=>'error','message'=>'Bad data']); exit; }
        /* Each item: {board_id, category_id, sort_order} */
        $stmt = $pdo->prepare('UPDATE forum_boards SET sort_order=?, category_id=? WHERE board_id=?');
        foreach ($order as $item) {
            $stmt->execute([(int)$item['sort'], (int)$item['cat'], (int)$item['id']]);
        }
        echo json_encode(['status' => 'success']);
        break;

    case 'inline_edit_cat':
        $id = (int)($_POST['category_id'] ?? 0);
        $name = trim($_POST['name'] ?? '');
        $desc = trim($_POST['description'] ?? '');
        if ($id < 1 || $name === '') { echo json_encode(['status'=>'error','message'=>'Invalid']); exit; }
        $pdo->prepare('UPDATE forum_categories SET name=?, description=? WHERE category_id=?')
            ->execute([$name, $desc ?: null, $id]);
        echo json_encode(['status' => 'success']);
        break;

    case 'inline_edit_board':
        $id = (int)($_POST['board_id'] ?? 0);
        $name = trim($_POST['name'] ?? '');
        $desc = trim($_POST['description'] ?? '');
        if ($id < 1 || $name === '') { echo json_encode(['status'=>'error','message'=>'Invalid']); exit; }
        $pdo->prepare('UPDATE forum_boards SET name=?, description=? WHERE board_id=?')
            ->execute([$name, $desc ?: null, $id]);
        echo json_encode(['status' => 'success']);
        break;

    case 'quick_add_cat':
        $name = trim($_POST['name'] ?? '');
        if ($name === '') { echo json_encode(['status'=>'error','message'=>'Name required']); exit; }
        $maxSort = (int)$pdo->query('SELECT COALESCE(MAX(sort_order),0) FROM forum_categories')->fetchColumn();
        $pdo->prepare('INSERT INTO forum_categories (name, sort_order) VALUES (?, ?)')->execute([$name, $maxSort+1]);
        $newId = (int)$pdo->lastInsertId();
        echo json_encode(['status'=>'success', 'id'=>$newId, 'name'=>$name, 'sort'=>$maxSort+1]);
        break;

    case 'quick_add_board':
        $catId = (int)($_POST['category_id'] ?? 0);
        $name  = trim($_POST['name'] ?? '');
        if ($catId < 1 || $name === '') { echo json_encode(['status'=>'error','message'=>'Invalid']); exit; }
        $maxSort = (int)$pdo->prepare('SELECT COALESCE(MAX(sort_order),0) FROM forum_boards WHERE category_id=?');
        $maxSort->execute([$catId]);
        $ms = (int)$maxSort->fetchColumn();
        $pdo->prepare('INSERT INTO forum_boards (category_id, name, sort_order) VALUES (?,?,?)')->execute([$catId, $name, $ms+1]);
        $newId = (int)$pdo->lastInsertId();
        echo json_encode(['status'=>'success', 'id'=>$newId, 'name'=>$name]);
        break;

    case 'delete_cat':
        $id = (int)($_POST['category_id'] ?? 0);
        $bc = $pdo->prepare('SELECT COUNT(*) FROM forum_boards WHERE category_id=?');
        $bc->execute([$id]);
        if ($bc->fetchColumn() > 0) {
            echo json_encode(['status'=>'error','message'=>'Remove boards first.']);
        } else {
            $pdo->prepare('DELETE FROM forum_categories WHERE category_id=?')->execute([$id]);
            echo json_encode(['status'=>'success']);
        }
        break;

    case 'delete_board':
        $id = (int)($_POST['board_id'] ?? 0);
        $tc = $pdo->prepare('SELECT COUNT(*) FROM forum_threads WHERE board_id=? AND is_deleted=0');
        $tc->execute([$id]);
        if ($tc->fetchColumn() > 0) {
            echo json_encode(['status'=>'error','message'=>'Delete/move threads first.']);
        } else {
            $pdo->prepare('DELETE FROM forum_boards WHERE board_id=?')->execute([$id]);
            echo json_encode(['status'=>'success']);
        }
        break;

    default:
        echo json_encode(['status'=>'error','message'=>'Unknown action']);
}