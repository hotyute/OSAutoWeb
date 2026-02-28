<?php
/**
 * Forum Moderation Actions (POST-only endpoint)
 * ============================================================
 * Access: role >= moderator.
 *
 * Supported actions:
 *   • sticky        — Toggle is_sticky on a thread.
 *   • lock          — Toggle is_locked on a thread.
 *   • delete_thread — Soft-delete a thread; decrement board
 *                     counters (thread_count & post_count).
 *   • delete_post   — Soft-delete a single post; decrement
 *                     thread.reply_count and board.post_count.
 *                     If the target is the opening post, the
 *                     entire thread is soft-deleted instead.
 *
 * Counter integrity: GREATEST(0, col - n) prevents negative
 *   cached counts if a race condition or double-submit occurs.
 *
 * Security:
 *   • CSRF validated.
 *   • Role enforced via requireRole('moderator').
 *   • return_url validated as a local path (prevents open redirect).
 *   • Every action is written to the audit log.
 * ============================================================
 */
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
requireRole('moderator');

/* ---- Only POST allowed ---- */
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('/forum/index.php');
}

/* ---- CSRF gate ---- */
if (!validateCSRF($_POST['csrf_token'] ?? '')) {
    redirect('/forum/index.php');
}

$action   = $_POST['action']     ?? '';
$threadId = (int)($_POST['thread_id'] ?? 0);
$postId   = (int)($_POST['post_id']   ?? 0);

/* Sanitise return URL — must start with / to prevent open redirect */
$returnUrl = $_POST['return_url'] ?? '/forum/index.php';
if (!str_starts_with($returnUrl, '/')) {
    $returnUrl = '/forum/index.php';
}

switch ($action) {

    /* ========== TOGGLE STICKY ========== */
    case 'sticky':
        if ($threadId > 0) {
            $pdo->prepare(
                'UPDATE `forum_threads`
                 SET `is_sticky` = NOT `is_sticky`
                 WHERE `thread_id` = ?'
            )->execute([$threadId]);
            logAction($pdo, $_SESSION['user_id'], "mod:sticky_toggle:thread:$threadId");
        }
        break;

    /* ========== TOGGLE LOCK ========== */
    case 'lock':
        if ($threadId > 0) {
            $pdo->prepare(
                'UPDATE `forum_threads`
                 SET `is_locked` = NOT `is_locked`
                 WHERE `thread_id` = ?'
            )->execute([$threadId]);
            logAction($pdo, $_SESSION['user_id'], "mod:lock_toggle:thread:$threadId");
        }
        break;

    /* ========== SOFT-DELETE THREAD ========== */
    case 'delete_thread':
        if ($threadId > 0) {
            /* Fetch thread metadata for counter adjustment */
            $tStmt = $pdo->prepare(
                'SELECT `board_id`, `reply_count`
                 FROM `forum_threads`
                 WHERE `thread_id` = ? AND `is_deleted` = 0'
            );
            $tStmt->execute([$threadId]);
            $t = $tStmt->fetch();

            if ($t) {
                $totalPostsInThread = (int)$t['reply_count'] + 1; /* replies + OP */

                $pdo->beginTransaction();
                try {
                    /* Mark thread as deleted */
                    $pdo->prepare(
                        'UPDATE `forum_threads`
                         SET `is_deleted` = 1
                         WHERE `thread_id` = ?'
                    )->execute([$threadId]);

                    /* Decrement board cached counters */
                    $pdo->prepare(
                        'UPDATE `forum_boards`
                         SET `thread_count` = GREATEST(0, `thread_count` - 1),
                             `post_count`   = GREATEST(0, `post_count` - ?)
                         WHERE `board_id` = ?'
                    )->execute([$totalPostsInThread, $t['board_id']]);

                    $pdo->commit();
                } catch (\Exception $ex) {
                    $pdo->rollBack();
                    error_log('Mod delete_thread error: ' . $ex->getMessage());
                }

                logAction($pdo, $_SESSION['user_id'], "mod:delete_thread:$threadId");

                /* If we were viewing the deleted thread, go back to the board */
                if (str_contains($returnUrl, "thread.php?id=$threadId")) {
                    $returnUrl = "/forum/board.php?id={$t['board_id']}";
                }
            }
        }
        break;

    /* ========== SOFT-DELETE POST ========== */
    case 'delete_post':
        if ($postId > 0) {
            /* Fetch post + parent thread info */
            $pStmt = $pdo->prepare(
                'SELECT p.post_id, p.thread_id, p.user_id,
                        t.board_id, t.reply_count
                 FROM `forum_posts` p
                 JOIN `forum_threads` t ON t.thread_id = p.thread_id
                 WHERE p.post_id = ? AND p.is_deleted = 0'
            );
            $pStmt->execute([$postId]);
            $p = $pStmt->fetch();

            if ($p) {
                /*
                 * Is this the opening post (the very first post in the thread)?
                 * If so, soft-delete the entire thread instead.
                 */
                $firstPostStmt = $pdo->prepare(
                    'SELECT `post_id` FROM `forum_posts`
                     WHERE `thread_id` = ?
                     ORDER BY `created_at` ASC
                     LIMIT 1'
                );
                $firstPostStmt->execute([$p['thread_id']]);
                $firstPost = $firstPostStmt->fetch();

                if ($firstPost && (int)$firstPost['post_id'] === $postId) {
                    /*
                     * Deleting OP → delete the whole thread.
                     * Re-use the delete_thread logic.
                     */
                    $totalPostsInThread = (int)$p['reply_count'] + 1;

                    $pdo->beginTransaction();
                    try {
                        $pdo->prepare(
                            'UPDATE `forum_threads` SET `is_deleted` = 1 WHERE `thread_id` = ?'
                        )->execute([$p['thread_id']]);

                        $pdo->prepare(
                            'UPDATE `forum_boards`
                             SET `thread_count` = GREATEST(0, `thread_count` - 1),
                                 `post_count`   = GREATEST(0, `post_count` - ?)
                             WHERE `board_id` = ?'
                        )->execute([$totalPostsInThread, $p['board_id']]);

                        $pdo->commit();
                    } catch (\Exception $ex) {
                        $pdo->rollBack();
                    }

                    logAction($pdo, $_SESSION['user_id'],
                              "mod:delete_thread_via_op:post:$postId:thread:{$p['thread_id']}");

                    if (str_contains($returnUrl, "thread.php?id={$p['thread_id']}")) {
                        $returnUrl = "/forum/board.php?id={$p['board_id']}";
                    }

                } else {
                    /*
                     * Normal reply deletion — soft-delete only this post.
                     */
                    $pdo->beginTransaction();
                    try {
                        /* Soft-delete the post */
                        $pdo->prepare(
                            'UPDATE `forum_posts` SET `is_deleted` = 1 WHERE `post_id` = ?'
                        )->execute([$postId]);

                        /* Decrement thread reply counter */
                        $pdo->prepare(
                            'UPDATE `forum_threads`
                             SET `reply_count` = GREATEST(0, `reply_count` - 1)
                             WHERE `thread_id` = ?'
                        )->execute([$p['thread_id']]);

                        /* Decrement board post counter */
                        $pdo->prepare(
                            'UPDATE `forum_boards`
                             SET `post_count` = GREATEST(0, `post_count` - 1)
                             WHERE `board_id` = ?'
                        )->execute([$p['board_id']]);

                        /* Decrement author's cached post count */
                        $pdo->prepare(
                            'UPDATE `users`
                             SET `post_count` = GREATEST(0, `post_count` - 1)
                             WHERE `user_id` = ?'
                        )->execute([$p['user_id']]);

                        $pdo->commit();
                    } catch (\Exception $ex) {
                        $pdo->rollBack();
                    }

                    logAction($pdo, $_SESSION['user_id'], "mod:delete_post:$postId");
                }
            }
        }
        break;

    default:
        /* Unknown action — do nothing, just redirect */
        break;
}

redirect($returnUrl);