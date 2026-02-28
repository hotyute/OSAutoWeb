<?php
/**
 * Login Page
 * --------------------------------------------------------
 * Security:
 *   • CSRF token validated on POST.
 *   • password_verify() against stored hash.
 *   • Session regenerated on successful login (fixation protection).
 *   • Login attempt logged regardless of outcome.
 */
$pageTitle = 'Login';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';
initSession();

/* Already logged in → go to dashboard */
if (isLoggedIn()) redirect('/dashboard.php');

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    /* CSRF check */
    if (!validateCSRF($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid session. Please try again.';
    } else {
        $username = trim($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';

        if ($username === '' || $password === '') {
            $error = 'Both fields are required.';
        } else {
            $stmt = $pdo->prepare('SELECT * FROM `users` WHERE `username` = ?');
            $stmt->execute([$username]);
            $user = $stmt->fetch();

            if ($user && password_verify($password, $user['password_hash'])) {
                /* Check if user is banned via subscription status */
                $subStmt = $pdo->prepare(
                    'SELECT `status` FROM `subscriptions` WHERE `user_id` = ? ORDER BY `sub_id` DESC LIMIT 1'
                );
                $subStmt->execute([$user['user_id']]);
                $sub = $subStmt->fetch();

                if ($sub && $sub['status'] === 'banned') {
                    $error = 'Your account has been banned.';
                    logAction($pdo, $user['user_id'], 'login_banned');
                } else {
                    /* Regenerate session ID to prevent fixation */
                    session_regenerate_id(true);

                    $_SESSION['user_id']  = $user['user_id'];
                    $_SESSION['username'] = $user['username'];
                    $_SESSION['role']     = $user['role'];

                    logAction($pdo, $user['user_id'], 'login');

                    redirect('/dashboard.php');
                }
            } else {
                $error = 'Invalid username or password.';
                logAction($pdo, null, 'login_failed:' . $username);
            }
        }
    }
}

require_once __DIR__ . '/includes/header.php';
?>

<div style="max-width:420px;margin:2rem auto;">
  <div class="card">
    <h2 style="text-align:center;">🔐 Sign In</h2>

    <?php if ($error): ?>
      <div class="alert alert-error"><?= e($error) ?></div>
    <?php endif; ?>

    <form method="POST" autocomplete="off">
      <input type="hidden" name="csrf_token" value="<?= e(generateCSRF()) ?>">

      <div class="form-group">
        <label for="username">Username</label>
        <input type="text" id="username" name="username" required
               value="<?= e($_POST['username'] ?? '') ?>">
      </div>
      <div class="form-group">
        <label for="password">Password</label>
        <input type="password" id="password" name="password" required>
      </div>

      <button type="submit" class="btn btn-primary" style="width:100%;">Login</button>
    </form>

    <p class="text-center mt-1" style="font-size:.85rem;color:var(--text-secondary);">
      Don't have an account? <a href="/register.php">Register</a>
    </p>
  </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>