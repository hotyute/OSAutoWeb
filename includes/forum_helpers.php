<?php
/**
 * Forum Helpers
 * --------------------------------------------------------
 * Shared functions used across all forum pages:
 *   • CSS injection (forum-specific styles)
 *   • Post body rendering (quotes, auto-links, XSS-safe)
 *   • Pagination HTML builder
 *   • Relative time helper
 *   • Board stat recalculation
 */

/**
 * Outputs forum-specific CSS once per request.
 * Call immediately after including header.php.
 */
function forumCSS(): void
{
    static $done = false;
    if ($done) return;
    $done = true;
    ?>
    <style>
    .post-quote{border-left:3px solid var(--accent-purple);background:rgba(168,85,247,.06);padding:.6rem 1rem;margin:.5rem 0;border-radius:0 var(--radius) var(--radius) 0;color:var(--text-secondary);font-size:.9rem;}
    .post-quote .quote-author{font-weight:600;color:var(--accent-purple);font-size:.8rem;margin-bottom:.3rem;}
    .post-content a{color:var(--accent-green);word-break:break-all;}
    .profile-header{display:flex;gap:1.5rem;align-items:center;}
    .profile-avatar-lg{width:80px;height:80px;border-radius:50%;background:var(--bg-primary);border:3px solid var(--accent-green);display:flex;align-items:center;justify-content:center;font-size:2rem;color:var(--accent-green);flex-shrink:0;}
    .thread-preview{font-size:.78rem;color:var(--text-secondary);margin-top:.15rem;display:-webkit-box;-webkit-line-clamp:1;-webkit-box-orient:vertical;overflow:hidden;}
    .forum-stat-bar{display:flex;gap:1.5rem;flex-wrap:wrap;padding:.75rem 0;}
    .forum-stat-item{text-align:center;}
    .forum-stat-num{font-size:1.3rem;font-weight:800;font-family:var(--font-mono);}
    .forum-stat-label{font-size:.75rem;color:var(--text-secondary);text-transform:uppercase;}
    .move-form{display:inline-flex;gap:.3rem;align-items:center;flex-wrap:wrap;}
    .move-form select{padding:.3rem .5rem;font-size:.82rem;border-radius:var(--radius);border:1px solid var(--border-color);background:var(--bg-secondary);color:var(--text-primary);}
    .search-result{border-bottom:1px solid var(--border-color);padding:.75rem 0;}
    .search-result:last-child{border-bottom:none;}
    .sr-snippet{font-size:.85rem;color:var(--text-secondary);margin-top:.3rem;line-height:1.5;}
    .sr-snippet mark{background:rgba(57,255,20,.2);color:var(--accent-green);padding:0 2px;border-radius:2px;}
    .cat-section{margin-bottom:2rem;}
    .cat-section-header{background:var(--bg-secondary);padding:.75rem 1rem;border:1px solid var(--border-color);border-radius:var(--radius) var(--radius) 0 0;display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:.5rem;}
    .board-row{display:flex;justify-content:space-between;align-items:center;padding:.6rem 1rem;border:1px solid var(--border-color);border-top:none;gap:.5rem;flex-wrap:wrap;}
    .board-row:last-child{border-radius:0 0 var(--radius) var(--radius);}
    @media(max-width:680px){
      .profile-header{flex-direction:column;text-align:center;}
      .profile-avatar-lg{width:64px;height:64px;font-size:1.5rem;}
      .move-form{flex-direction:column;align-items:stretch;width:100%;}
      .move-form select{width:100%;}
      .cat-section-header{flex-direction:column;align-items:flex-start;}
      .board-row{flex-direction:column;align-items:flex-start;}
    }
    </style>
    <?php
}

/**
 * Render post body safely.
 * Lines prefixed with > become styled blockquotes.
 * URLs become clickable links.
 * All content is XSS-escaped FIRST.
 */
function formatPost(string $body): string
{
    $escaped = e($body);
    $lines   = explode("\n", $escaped);
    $html    = '';
    $inQuote = false;

    foreach ($lines as $line) {
        $trimmed = ltrim($line);

        /* Detect quoted lines: > text or &gt; text (after escaping) */
        if (str_starts_with($trimmed, '&gt; ')) {
            if (!$inQuote) {
                $html .= '<div class="post-quote">';
                $inQuote = true;
            }
            $content = substr($trimmed, 5);
            /* Check for @author attribution on first line */
            if (preg_match('/^@(.+?) wrote:$/', $content, $m)) {
                $html .= '<div class="quote-author">' . $m[1] . ' wrote:</div>';
            } else {
                $html .= $content . '<br>';
            }
        } else {
            if ($inQuote) {
                $html .= '</div>';
                $inQuote = false;
            }
            $html .= $line . '<br>';
        }
    }
    if ($inQuote) $html .= '</div>';

    /* Auto-link URLs (already escaped, so & is &amp; etc.) */
    $html = preg_replace(
        '#(https?://[^\s<&]+(?:&amp;[^\s<&]+)*)#i',
        '<a href="$1" target="_blank" rel="nofollow noopener">$1</a>',
        $html
    );

    return $html;
}

/**
 * Pagination HTML builder.
 */
function pgHtml(int $cur, int $tot, string $base): string
{
    if ($tot <= 1) return '';
    $s = str_contains($base, '?') ? '&' : '?';
    $h = '<div class="pagination">';

    if ($cur > 1)
        $h .= '<a href="' . e($base . $s . 'page=' . ($cur - 1)) . '" class="btn btn-sm btn-secondary">&laquo;</a>';

    $st = max(1, $cur - 3);
    $en = min($tot, $cur + 3);

    if ($st > 1) {
        $h .= '<a href="' . e($base . $s . 'page=1') . '" class="btn btn-sm btn-secondary">1</a>';
        if ($st > 2) $h .= '<span style="padding:0 .3rem;color:var(--text-secondary);">…</span>';
    }
    for ($i = $st; $i <= $en; $i++) {
        $c = $i === $cur ? 'btn-primary' : 'btn-secondary';
        $h .= '<a href="' . e($base . $s . 'page=' . $i) . '" class="btn btn-sm ' . $c . '">' . $i . '</a>';
    }
    if ($en < $tot) {
        if ($en < $tot - 1) $h .= '<span style="padding:0 .3rem;color:var(--text-secondary);">…</span>';
        $h .= '<a href="' . e($base . $s . 'page=' . $tot) . '" class="btn btn-sm btn-secondary">' . $tot . '</a>';
    }

    if ($cur < $tot)
        $h .= '<a href="' . e($base . $s . 'page=' . ($cur + 1)) . '" class="btn btn-sm btn-secondary">&raquo;</a>';

    $h .= '</div>';
    return $h;
}

/**
 * Human-readable relative time.
 */
function timeAgo(string $datetime): string
{
    $diff = time() - strtotime($datetime);
    if ($diff < 60) return 'just now';
    if ($diff < 3600) return (int)($diff / 60) . 'm ago';
    if ($diff < 86400) return (int)($diff / 3600) . 'h ago';
    if ($diff < 604800) return (int)($diff / 86400) . 'd ago';
    return date('M j, Y', strtotime($datetime));
}

/**
 * Recalculate a board's cached stats from scratch.
 * Used after move/delete operations where incremental updates
 * would be error-prone.
 */
function recalcBoardStats(PDO $pdo, int $boardId): void
{
    $pdo->prepare(
        'UPDATE `forum_boards` SET
            `thread_count` = COALESCE((
                SELECT COUNT(*) FROM `forum_threads`
                WHERE `board_id` = ? AND `is_deleted` = 0
            ), 0),
            `post_count` = COALESCE((
                SELECT COUNT(*) FROM `forum_posts` p
                JOIN `forum_threads` t ON t.thread_id = p.thread_id
                WHERE t.board_id = ? AND t.is_deleted = 0 AND p.is_deleted = 0
            ), 0),
            `last_post_id` = (
                SELECT p.post_id FROM `forum_posts` p
                JOIN `forum_threads` t ON t.thread_id = p.thread_id
                WHERE t.board_id = ? AND t.is_deleted = 0 AND p.is_deleted = 0
                ORDER BY p.created_at DESC LIMIT 1
            )
         WHERE `board_id` = ?'
    )->execute([$boardId, $boardId, $boardId, $boardId]);
}

/**
 * Calculate the last page of a thread for "jump to last" links.
 */
function threadLastPage(int $replyCount, int $perPage = 15): int
{
    return max(1, (int)ceil(($replyCount + 1) / $perPage));
}