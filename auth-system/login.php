<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/session.php';
require_once __DIR__ . '/includes/helpers.php';
require_once __DIR__ . '/config/db.php';

if (isset($_SESSION['user_id'], $_SESSION['username'])) {
    redirect('dashboard.php');
}

$errors = [];
$username = '';
$flash = get_flash();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim((string) ($_POST['username'] ?? ''));
    $password = (string) ($_POST['password'] ?? '');

    if (!verify_csrf_token($_POST['csrf_token'] ?? null)) {
        $errors[] = 'Your session token is invalid. Please try again.';
    } elseif ($username === '' || $password === '') {
        $errors[] = 'Username and password are required.';
    } else {
        $pdo = null;
        try {
            $pdo = db();
            $pdo->beginTransaction();

            $statement = $pdo->prepare('SELECT id, username, password_hash, failed_attempts, locked_until, TIMESTAMPDIFF(SECOND, NOW(), locked_until) AS lock_seconds FROM users WHERE username = ? FOR UPDATE');
            $statement->execute([$username]);
            $user = $statement->fetch();

            if (!$user) {
                $pdo->commit();
                $errors[] = 'Invalid username or password (attempt 1 of 3).';
            } elseif ($user['locked_until'] !== null && (int) $user['lock_seconds'] > 0) {
                $remainingSeconds = (int) $user['lock_seconds'];
                $minutes = intdiv($remainingSeconds, 60);
                $seconds = $remainingSeconds % 60;
                $pdo->commit();
                $errors[] = sprintf(
                    'Account is locked. Try again in %d minute%s and %d second%s.',
                    $minutes,
                    $minutes === 1 ? '' : 's',
                    $seconds,
                    $seconds === 1 ? '' : 's'
                );
            } else {
                $failedAttempts = $user['locked_until'] !== null ? 0 : (int) $user['failed_attempts'];

                if (password_verify($password, $user['password_hash'])) {
                    $update = $pdo->prepare('UPDATE users SET failed_attempts = 0, locked_until = NULL WHERE id = ?');
                    $update->execute([(int) $user['id']]);
                    $pdo->commit();

                    session_regenerate_id(true);
                    $_SESSION['user_id'] = (int) $user['id'];
                    $_SESSION['username'] = (string) $user['username'];
                    redirect('dashboard.php');
                }

                $failedAttempts++;
                if ($failedAttempts >= 3) {
                    $update = $pdo->prepare(
                        'UPDATE users SET failed_attempts = 3, locked_until = DATE_ADD(NOW(), INTERVAL 5 MINUTE) WHERE id = ?'
                    );
                    $update->execute([(int) $user['id']]);
                    $errors[] = 'Invalid username or password (attempt 3 of 3). Account locked for 5 minutes.';
                } else {
                    $update = $pdo->prepare('UPDATE users SET failed_attempts = ?, locked_until = NULL WHERE id = ?');
                    $update->execute([$failedAttempts, (int) $user['id']]);
                    $errors[] = "Invalid username or password (attempt {$failedAttempts} of 3).";
                }
                $pdo->commit();
            }
        } catch (Throwable $exception) {
            if ($pdo instanceof PDO && $pdo->inTransaction()) {
                $pdo->rollBack();
            }
            error_log('Login error: ' . $exception->getMessage());
            $errors[] = 'Login is temporarily unavailable. Please try again.';
        }
    }
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Log In | Auth System</title>
    <link rel="stylesheet" href="assets/style.css">
</head>
<body>
<main class="auth-card">
    <div class="brand">AUTH SYSTEM</div>
    <h1>Welcome back</h1>
    <p class="intro">Enter your account details to continue.</p>

    <?php if ($flash !== null): ?>
        <div class="message <?= e($flash['type'] ?? 'success') ?>" role="status"><?= e($flash['message'] ?? '') ?></div>
    <?php endif; ?>
    <?php foreach ($errors as $error): ?>
        <div class="message error" role="alert"><?= e($error) ?></div>
    <?php endforeach; ?>

    <form method="post" action="login.php">
        <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
        <label for="username">Username</label>
        <input id="username" name="username" type="text" value="<?= e($username) ?>" maxlength="50" autocomplete="username" required>

        <label for="password">Password</label>
        <input id="password" name="password" type="password" autocomplete="current-password" required>

        <div class="form-options"><a href="forgot_password.php">Forgot Password?</a></div>
        <button type="submit">Log in</button>
    </form>

    <p class="form-link">Need an account? <a href="register.php">Register</a></p>
</main>
</body>
</html>
