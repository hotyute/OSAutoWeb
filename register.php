<?php
/**
 * Registration Page
 * --------------------------------------------------------
 * Security:
 *   • CSRF validated.
 *   • Password hashed with PASSWORD_DEFAULT (bcrypt/argon2).
 *   • Username & email uniqueness enforced at DB level + pre-check.
 */
$pageTitle = 'Register';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';
initSession();

if (isLoggedIn()) redirect('/dashboard.php');

$error   = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateCSRF($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid session. Please refresh and try again.';
    } else {
        $username = trim($_POST['username'] ?? '');
        $email    = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';
        $confirm  = $_POST['password_confirm'] ?? '';

        /* Validation */
        if ($username === '' || $email === '' || $password === '') {
            $error = 'All fields are required.';
        } elseif (!preg_match('/^[A-Za-z0-9_]{3,32}$/', $username)) {
            $error = 'Username must be 3-32 chars (letters, numbers, underscore).';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = 'Invalid email address.';
        } elseif (strlen($password) < 8) {
            $error = 'Password must be at least 8 characters.';
        } elseif ($password !== $confirm) {
            $error = 'Passwords do not match.';
        } else {
            /* Check uniqueness */
            $chk = $pdo->prepare('SELECT `user_id` FROM `users` WHERE `username` = ? OR `email` = ?');
            $chk->execute([$username, $email]);
            if ($chk->fetch()) {
                $error = 'Username or email already taken.';
            } else {
                $hash = password_hash($password, PASSWORD_DEFAULT);

                $ins = $pdo->prepare(
                    'INSERT INTO `users` (`username`, `email`, `password_hash`) VALUES (?, ?, ?)'
                );
                $ins->execute([$username, $email, $hash]);

                $newId = (int)$pdo->lastInsertId();
                logAction($pdo, $newId, 'register');
                $success = 'Account created! You may now <a href="/login.php">log in</a>.';
            }
        }
    }
}

require_once __DIR__ . '/includes/header.php';
?>

<div style="max-width:420px;margin:2rem auto;">
  <div class="card">
    <h2 style="text-align:center;">📝 Create Account</h2>

    <?php if ($error): ?>
      <div class="alert alert-error"><?= e($error) ?></div>
    <?php endif; ?>
    <?php if ($success): ?>
      <div class="alert alert-success"><?= $success /* contains safe HTML link */ ?></div>
    <?php endif; ?>

    <form method="POST" autocomplete="off">
      <input type="hidden" name="csrf_token" value="<?= e(generateCSRF()) ?>">

      <div class="form-group">
        <label for="username">Username</label>
        <input type="text" id="username" name="username" required
               value="<?= e($_POST['username'] ?? '') ?>">
      </div>
      <div class="form-group">
        <label for="email">Email</label>
        <input type="email" id="email" name="email" required
               value="<?= e($_POST['email'] ?? '') ?>">
      </div>
      <div class="form-group">
        <label for="password">Password (min 8 chars)</label>
        <input type="password" id="password" name="password" required minlength="8">
      </div>
      <div class="form-group">
        <label for="password_confirm">Confirm Password</label>
        <input type="password" id="password_confirm" name="password_confirm" required>
      </div>

      <button type="submit" class="btn btn-primary" style="width:100%;">Register</button>
    </form>

    <p class="text-center mt-1" style="font-size:.85rem;color:var(--text-secondary);">
      Already registered? <a href="/login.php">Sign in</a>
    </p>
  </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>