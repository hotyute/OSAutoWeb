<?php
/**
 * Client Authentication API
 * ============================================================
 * Method : POST  (application/x-www-form-urlencoded or JSON body)
 * Params : username, password, hwid
 *
 * Flow:
 *   1. Validate input presence.
 *   2. Look up user by username.
 *   3. Verify password hash.
 *   4. Check for an active (non-expired, non-banned) subscription.
 *   5. HWID logic:
 *      a) If user's stored HWID is NULL → bind incoming HWID.
 *      b) If stored HWID matches → allow.
 *      c) If stored HWID differs → reject (HWID mismatch).
 *   6. Log the authentication attempt.
 *   7. Return JSON response.
 *
 * Security:
 *   • All DB queries use PDO prepared statements.
 *   • No session needed (stateless API).
 *   • Rate limiting should be handled at the web-server / WAF level.
 * ============================================================
 */

header('Content-Type: application/json; charset=utf-8');

/* Block anything that isn't POST */
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['status' => 'error', 'message' => 'Method not allowed.']);
    exit;
}

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';

/* ---- Parse input (support both form-encoded and JSON bodies) ---- */
$contentType = $_SERVER['CONTENT_TYPE'] ?? '';
if (str_contains($contentType, 'application/json')) {
    $input = json_decode(file_get_contents('php://input'), true) ?? [];
} else {
    $input = $_POST;
}

$username = trim($input['username'] ?? '');
$password = $input['password'] ?? '';
$hwid     = trim($input['hwid'] ?? '');

/* ---- Step 1: Validate presence ---- */
if ($username === '' || $password === '' || $hwid === '') {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Missing required fields: username, password, hwid.']);
    exit;
}

/* ---- Step 2: Look up user ---- */
$stmt = $pdo->prepare('SELECT * FROM `users` WHERE `username` = ?');
$stmt->execute([$username]);
$user = $stmt->fetch();

if (!$user) {
    logAction($pdo, null, 'client_auth_fail:unknown_user:' . $username);
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'Invalid credentials.']);
    exit;
}

/* ---- Step 3: Verify password ---- */
if (!password_verify($password, $user['password_hash'])) {
    logAction($pdo, $user['user_id'], 'client_auth_fail:bad_password');
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'Invalid credentials.']);
    exit;
}

/* ---- Step 4: Check subscription ---- */
$subStmt = $pdo->prepare(
    "SELECT * FROM `subscriptions`
     WHERE `user_id` = ?
     ORDER BY `expires_at` DESC
     LIMIT 1"
);
$subStmt->execute([$user['user_id']]);
$sub = $subStmt->fetch();

if (!$sub) {
    logAction($pdo, $user['user_id'], 'client_auth_fail:no_subscription');
    http_response_code(403);
    echo json_encode(['status' => 'error', 'message' => 'No active subscription found.']);
    exit;
}

if ($sub['status'] === 'banned') {
    logAction($pdo, $user['user_id'], 'client_auth_fail:banned');
    http_response_code(403);
    echo json_encode(['status' => 'error', 'message' => 'Account is banned.']);
    exit;
}

if ($sub['status'] !== 'active' || strtotime($sub['expires_at']) <= time()) {
    logAction($pdo, $user['user_id'], 'client_auth_fail:expired');
    http_response_code(403);
    echo json_encode(['status' => 'error', 'message' => 'Subscription expired.', 'expiry' => $sub['expires_at']]);
    exit;
}

/* ---- Step 5: HWID verification ---- */
if ($user['hwid'] === null || $user['hwid'] === '') {
    /*
     * 5a — First launch: bind the incoming HWID to this account.
     *       Also stamp hwid_updated_at so the 7-day cooldown starts.
     */
    $pdo->prepare('UPDATE `users` SET `hwid` = ?, `hwid_updated_at` = NOW() WHERE `user_id` = ?')
        ->execute([$hwid, $user['user_id']]);
    logAction($pdo, $user['user_id'], 'hwid_bound');
} elseif ($user['hwid'] !== $hwid) {
    /*
     * 5c — HWID mismatch: someone is trying to use the account
     *       on a different machine. Reject immediately.
     */
    logAction($pdo, $user['user_id'], 'client_auth_fail:hwid_mismatch');
    http_response_code(403);
    echo json_encode([
        'status'  => 'error',
        'message' => 'Hardware ID mismatch. Reset your HWID via the portal or contact support.'
    ]);
    exit;
}
/* 5b — HWID matches: fall through to success. */

/* ---- Step 6: Log successful authentication ---- */
logAction($pdo, $user['user_id'], 'client_auth');

/* ---- Step 7: Return success ---- */
echo json_encode([
    'status'  => 'success',
    'message' => 'Authentication successful.',
    'expiry'  => $sub['expires_at'],
    'user'    => [
        'id'       => $user['user_id'],
        'username' => $user['username'],
        'role'     => $user['role'],
    ],
]);
exit;