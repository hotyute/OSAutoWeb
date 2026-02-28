<?php
/**
 * Forum Moderation — Enhanced
 * --------------------------------------------------------
 * Actions: sticky, lock, delete_thread, delete_post, move_thread
 */
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/forum_helpers.php';
requireRole('moderator');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') redirect('/forum/index.php');
if (!validateCSRF($_POST['csrf_token'] ?? '')) redirect('/forum/index.php');

$action    = $_POST['action'] ?? '';
$threadId  = (int)($_POST['thread_id'] ?? 0);
$postId    = (int)($_POST['post_id'] ?? 0);
$returnUrl = $_POST['return_url'] ?? '/forum/index.php';
if (!str_starts_with($returnUrl, '/')) $returnUrl = '/forum/index.php';

switch ($action) {

    case 'sticky':
        if ($threadId > 0) {
            $pdo->prepare('UPDATE `forum_threads` SET `is_sticky`=NOT `is_sticky` WHERE `thread_id`=?')
                ->execute([$threadId]);
            logAction($pdo, $_SESSION['user_id'], "mod:sticky:$threadId");
        }
        break;

    case 'lock':
        if ($threadId > 0) {
            $pdo->prepare('UPDATE `forum_threads` SET `is_locked`=NOT `is_locked` WHERE `thread_id`=?')
                ->execute([$threadId]);
            logAction($pdo, $_SESSION['user_id'], "mod:lock:$threadId");
        }
        break;

    case 'delete_thread':
        if ($threadId > 0) {
            $t = $pdo->prepare('SELECT `board_id`,`reply_count` FROM `forum_threads` WHERE `thread_id`=? AND `is_deleted`=0');
            $t->execute([$threadId]);
            $t = $t->fetch();
            if ($t) {
                $pdo->beginTransaction();
                try {
                    $pdo->prepare('UPDATE `forum_threads` SET `is_deleted`=1 WHERE `thread_id`=?')
                        ->execute([$threadId]);
                    recalcBoardStats($pdo, $t['board_id']);
                    $pdo->commit();
                } catch (\Exception $ex) { $pdo->rollBack(); }
                logAction($pdo, $_SESSION['user_id'], "mod:del_thread:$threadId");
                if (str_contains($returnUrl, "thread.php?id=$threadId"))
                    $returnUrl = "/forum/board.php?id={$t['board_id']}";
            }
        }
        break;

    case 'delete_post':
        if ($postId > 0) {
            $p = $pdo->prepare(
                'SELECT p.post_id, p.thread_id, p.user_id, t.board_id
                 FROM `forum_posts` p
                 JOIN `forum_threads` t ON t.thread_id = p.thread_id
                 WHERE p.post_id = ? AND p.is_deleted = 0'
            );
            $p->execute([$postId]);
            $p = $p->fetch();
            if ($p) {
                /* Check if OP */
                $first = $pdo->prepare('SELECT post_id FROM forum_posts WHERE thread_id=? ORDER BY created_at ASC LIMIT 1');
                $first->execute([$p['thread_id']]);
                $firstPost = $first->fetch();

                $pdo->beginTransaction();
                try {
                    if ($firstPost && (int)$firstPost['post_id'] === $postId) {
                        /* Delete entire thread */
                        $pdo->prepare('UPDATE forum_threads SET is_deleted=1 WHERE thread_id=?')
                            ->execute([$p['thread_id']]);
                        logAction($pdo, $_SESSION['user_id'], "mod:del_thread_via_op:$postId");
                        if (str_contains($returnUrl, "thread.php?id={$p['thread_id']}"))
                            $returnUrl = "/forum/board.php?id={$p['board_id']}";
                    } else {
                        $pdo->prepare('UPDATE forum_posts SET is_deleted=1 WHERE post_id=?')
                            ->execute([$postId]);
                        $pdo->prepare('UPDATE forum_threads SET reply_count=GREATEST(0,reply_count-1) WHERE thread_id=?')
                            ->execute([$p['thread_id']]);
                        $pdo->prepare('UPDATE users SET post_count=GREATEST(0,post_count-1) WHERE user_id=?')
                            ->execute([$p['user_id']]);
                        logAction($pdo, $_SESSION['user_id'], "mod:del_post:$postId");
                    }
                    recalcBoardStats($pdo, $p['board_id']);
                    $pdo->commit();
                } catch (\Exception $ex) { $pdo->rollBack(); }
            }
        }
        break;

    /* ===== NEW: MOVE THREAD ===== */
    case 'move_thread':
        if ($threadId > 0) {
            $newBoardId = (int)($_POST['new_board_id'] ?? 0);
            if ($newBoardId < 1) break;

            /* Verify thread and new board exist */
            $t = $pdo->prepare('SELECT board_id FROM forum_threads WHERE thread_id=? AND is_deleted=0');
            $t->execute([$threadId]);
            $t = $t->fetch();
            $nb = $pdo->prepare('SELECT board_id FROM forum_boards WHERE board_id=?');
            $nb->execute([$newBoardId]);

            if ($t && $nb->fetch() && (int)$t['board_id'] !== $newBoardId) {
                $oldBoardId = (int)$t['board_id'];

                $pdo->beginTransaction();
                try {
                    /* Move the thread */
                    $pdo->prepare('UPDATE forum_threads SET board_id=? WHERE thread_id=?')
                        ->execute([$newBoardId, $threadId]);

                    /* Recalculate both boards' cached stats */
                    recalcBoardStats($pdo, $oldBoardId);
                    recalcBoardStats($pdo, $newBoardId);

                    $pdo->commit();
                } catch (\Exception $ex) { $pdo->rollBack(); }

                logAction($pdo, $_SESSION['user_id'],
                    "mod:move_thread:$threadId:from:$oldBoardId:to:$newBoardId");

                /* Update return URL if it referenced the old board */
                $returnUrl = "/forum/thread.php?id=$threadId";
            }
        }
        break;
}

redirect($returnUrl);