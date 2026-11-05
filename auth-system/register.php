<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/session.php';
require_once __DIR__ . '/includes/helpers.php';
require_once __DIR__ . '/config/db.php';

$errors = [];
$username = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim((string) ($_POST['username'] ?? ''));
    $password = (string) ($_POST['password'] ?? '');
    $confirmation = (string) ($_POST['confirm_password'] ?? '');

    if (!verify_csrf_token($_POST['csrf_token'] ?? null)) {
        $errors[] = 'Your session token is invalid. Please try again.';
    }
    if ($username === '' || $password === '' || $confirmation === '') {
        $errors[] = 'All fields are required.';
    }
    if ($username !== '' && !preg_match('/^[A-Za-z0-9_]{3,50}$/', $username)) {
        $errors[] = 'Username must be 3-50 characters and contain only letters, numbers, and underscores.';
    }
    if ($password !== '' && strlen($password) < 8) {
        $errors[] = 'Password must be at least 8 characters.';
    }
    if ($password !== $confirmation) {
        $errors[] = 'Passwords do not match.';
    }

    if ($errors === []) {
        try {
            $pdo = db();
            $check = $pdo->prepare('SELECT id FROM users WHERE username = ?');
            $check->execute([$username]);

            if ($check->fetch()) {
                $errors[] = 'That username is already registered.';
            } else {
                $insert = $pdo->prepare('INSERT INTO users (username, password_hash) VALUES (?, ?)');
                $insert->execute([$username, password_hash($password, PASSWORD_DEFAULT)]);
                set_flash('success', 'Registration successful. You can now log in.');
                redirect('login.php');
            }
        } catch (PDOException $exception) {
            error_log('Registration database error: ' . $exception->getMessage());
            if ($exception->getCode() === '23000') {
                $errors[] = 'That username is already registered.';
            } else {
                $errors[] = 'Registration is temporarily unavailable. Please try again.';
            }
        }
    }
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Register | Auth System</title>
    <link rel="stylesheet" href="assets/style.css">
</head>
<body>
<main class="auth-card">
    <div class="brand">AUTH SYSTEM</div>
    <h1>Create account</h1>
    <p class="intro">Choose a username and a secure password.</p>

    <?php foreach ($errors as $error): ?>
        <div class="message error" role="alert"><?= e($error) ?></div>
    <?php endforeach; ?>

    <form method="post" action="register.php">
        <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
        <label for="username">Username</label>
        <input id="username" name="username" type="text" value="<?= e($username) ?>" minlength="3" maxlength="50" pattern="[A-Za-z0-9_]+" autocomplete="username" required>

        <label for="password">Password</label>
        <input id="password" name="password" type="password" minlength="8" autocomplete="new-password" required>

        <label for="confirm_password">Confirm password</label>
        <input id="confirm_password" name="confirm_password" type="password" minlength="8" autocomplete="new-password" required>

        <button type="submit">Register</button>
    </form>

    <p class="form-link">Already registered? <a href="login.php">Log in</a></p>
</main>
</body>
</html>
