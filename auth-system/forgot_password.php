<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/session.php';
require_once __DIR__ . '/includes/helpers.php';
require_once __DIR__ . '/config/db.php';

$errors = [];
$message = null;
$username = '';
$genericMessage = 'If that username is registered, a reset code has been sent to the simulated inbox.';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim((string) ($_POST['username'] ?? ''));

    if (!verify_csrf_token($_POST['csrf_token'] ?? null)) {
        $errors[] = 'Your session token is invalid. Please try again.';
    } elseif ($username === '') {
        $errors[] = 'Username is required.';
    } else {
        $pdo = null;
        try {
            $pdo = db();
            $findUser = $pdo->prepare('SELECT id FROM users WHERE username = ?');
            $findUser->execute([$username]);
            $user = $findUser->fetch();

            if ($user) {
                $code = strtoupper(bin2hex(random_bytes(4)));
                $pdo->beginTransaction();

                $invalidate = $pdo->prepare('UPDATE password_resets SET used = 1 WHERE user_id = ? AND used = 0');
                $invalidate->execute([(int) $user['id']]);

                $insert = $pdo->prepare('INSERT INTO password_resets (user_id, token_hash, expires_at) VALUES (?, ?, DATE_ADD(NOW(), INTERVAL 10 MINUTE))');
                $insert->execute([(int) $user['id'], password_hash($code, PASSWORD_DEFAULT)]);

                $delivery = json_encode([
                    'username' => $username,
                    'code' => $code,
                    'created_at' => date(DATE_ATOM),
                ], JSON_UNESCAPED_SLASHES);
                $written = $delivery !== false
                    && file_put_contents(__DIR__ . '/storage/simulated_inbox.log', $delivery . PHP_EOL, FILE_APPEND | LOCK_EX) !== false;

                if (!$written) {
                    throw new RuntimeException('Could not write to the simulated inbox.');
                }

                $pdo->commit();
            }

            $message = $genericMessage;
        } catch (Throwable $exception) {
            if ($pdo instanceof PDO && $pdo->inTransaction()) {
                $pdo->rollBack();
            }
            error_log('Password reset request error: ' . $exception->getMessage());
            $message = $genericMessage;
        }
    }
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Forgot Password | Auth System</title>
    <link rel="stylesheet" href="assets/style.css">
</head>
<body>
<main class="auth-card">
    <div class="brand">AUTH SYSTEM</div>
    <h1>Reset your password</h1>
    <p class="intro">Enter your username to request a one-time code.</p>

    <?php if ($message !== null): ?>
        <div class="message success" role="status"><?= e($message) ?></div>
    <?php endif; ?>
    <?php foreach ($errors as $error): ?>
        <div class="message error" role="alert"><?= e($error) ?></div>
    <?php endforeach; ?>

    <form method="post" action="forgot_password.php">
        <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
        <label for="username">Username</label>
        <input id="username" name="username" type="text" value="<?= e($username) ?>" maxlength="50" autocomplete="username" required>
        <button type="submit">Send reset code</button>
    </form>

    <div class="link-stack">
        <a href="simulated_inbox.php">Open simulated inbox</a>
        <a href="reset_password.php">I have a reset code</a>
        <a href="login.php">Back to login</a>
    </div>
</main>
</body>
</html>
