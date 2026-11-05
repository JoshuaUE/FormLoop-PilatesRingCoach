<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/session.php';
require_once __DIR__ . '/includes/helpers.php';

$username = trim((string) ($_GET['username'] ?? ''));
$latestMessage = null;

if ($username !== '') {
    $inboxPath = __DIR__ . '/storage/simulated_inbox.log';
    if (is_readable($inboxPath)) {
        $lines = file($inboxPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if ($lines !== false) {
            foreach (array_reverse($lines) as $line) {
                $message = json_decode($line, true);
                if (is_array($message) && isset($message['username']) && hash_equals((string) $message['username'], $username)) {
                    $latestMessage = $message;
                    break;
                }
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
    <title>Simulated Inbox | Auth System</title>
    <link rel="stylesheet" href="assets/style.css">
</head>
<body>
<main class="auth-card">
    <div class="brand">SIMULATED INBOX</div>
    <h1>Reset-code delivery</h1>
    <p class="intro">This lab page stands in for the account owner's email inbox.</p>

    <form method="get" action="simulated_inbox.php">
        <label for="username">Username</label>
        <input id="username" name="username" type="text" value="<?= e($username) ?>" maxlength="50" required>
        <button type="submit">Check inbox</button>
    </form>

    <?php if ($username !== '' && $latestMessage === null): ?>
        <div class="message neutral" role="status">No reset message was found.</div>
    <?php elseif ($latestMessage !== null): ?>
        <section class="inbox-message" aria-label="Latest reset message">
            <p><strong>To:</strong> <?= e($latestMessage['username'] ?? '') ?></p>
            <p>Your password reset code is:</p>
            <div class="reset-code"><?= e($latestMessage['code'] ?? '') ?></div>
            <p class="fine-print">This code expires 10 minutes after it was requested and can be used once.</p>
            <a class="button-link" href="reset_password.php?username=<?= e(rawurlencode($username)) ?>">Use this code</a>
        </section>
    <?php endif; ?>

    <p class="form-link"><a href="forgot_password.php">Back to reset request</a></p>
</main>
</body>
</html>
