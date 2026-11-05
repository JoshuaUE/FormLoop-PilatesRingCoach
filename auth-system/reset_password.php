<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/session.php';
require_once __DIR__ . '/includes/helpers.php';
require_once __DIR__ . '/config/db.php';

$errors = [];
$username = trim((string) ($_GET['username'] ?? ''));

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim((string) ($_POST['username'] ?? ''));
    $code = strtoupper(trim((string) ($_POST['code'] ?? '')));
    $password = (string) ($_POST['password'] ?? '');
    $confirmation = (string) ($_POST['confirm_password'] ?? '');

    if (!verify_csrf_token($_POST['csrf_token'] ?? null)) {
        $errors[] = 'Your session token is invalid. Please try again.';
    }
    if ($username === '' || $code === '' || $password === '' || $confirmation === '') {
        $errors[] = 'All fields are required.';
    }
    if ($password !== '' && strlen($password) < 8) {
        $errors[] = 'Password must be at least 8 characters.';
    }
    if ($password !== $confirmation) {
        $errors[] = 'Passwords do not match.';
    }

    if ($errors === []) {
        $pdo = null;
        try {
            $pdo = db();
            $pdo->beginTransaction();

            $statement = $pdo->prepare('SELECT pr.id, pr.token_hash, pr.used, (pr.expires_at > NOW()) AS not_expired, u.id AS user_id FROM users u INNER JOIN password_resets pr ON pr.user_id = u.id WHERE u.username = ? ORDER BY pr.created_at DESC, pr.id DESC LIMIT 1 FOR UPDATE');
            $statement->execute([$username]);
            $reset = $statement->fetch();

            $validReset = $reset
                && (int) $reset['used'] === 0
                && (int) $reset['not_expired'] === 1
                && password_verify($code, (string) $reset['token_hash']);

            if (!$validReset) {
                $pdo->commit();
                $errors[] = 'The reset code is invalid, expired, or has already been used.';
            } else {
                $updateUser = $pdo->prepare(
                    'UPDATE users SET password_hash = ?, failed_attempts = 0, locked_until = NULL WHERE id = ?'
                );
                $updateUser->execute([password_hash($password, PASSWORD_DEFAULT), (int) $reset['user_id']]);

                $useCode = $pdo->prepare('UPDATE password_resets SET used = 1 WHERE id = ?');
                $useCode->execute([(int) $reset['id']]);
                $pdo->commit();

                set_flash('success', 'Password reset successful. You can now log in.');
                redirect('login.php');
            }
        } catch (Throwable $exception) {
            if ($pdo instanceof PDO && $pdo->inTransaction()) {
                $pdo->rollBack();
            }
            error_log('Password reset error: ' . $exception->getMessage());
            $errors[] = 'Password reset is temporarily unavailable. Please try again.';
        }
    }
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Enter Reset Code | Auth System</title>
    <link rel="stylesheet" href="assets/style.css">
</head>
<body>
<main class="auth-card">
    <div class="brand">AUTH SYSTEM</div>
    <h1>Choose a new password</h1>
    <p class="intro">Use the latest code from the simulated inbox.</p>

    <?php foreach ($errors as $error): ?>
        <div class="message error" role="alert"><?= e($error) ?></div>
    <?php endforeach; ?>

    <form method="post" action="reset_password.php">
        <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
        <label for="username">Username</label>
        <input id="username" name="username" type="text" value="<?= e($username) ?>" maxlength="50" autocomplete="username" required>

        <label for="code">Reset code</label>
        <input id="code" name="code" type="text" minlength="8" maxlength="8" autocomplete="one-time-code" required>

        <label for="password">New password</label>
        <input id="password" name="password" type="password" minlength="8" autocomplete="new-password" required>

        <label for="confirm_password">Confirm new password</label>
        <input id="confirm_password" name="confirm_password" type="password" minlength="8" autocomplete="new-password" required>

        <button type="submit">Reset password</button>
    </form>

    <p class="form-link"><a href="login.php">Back to login</a></p>
</main>
</body>
</html>
