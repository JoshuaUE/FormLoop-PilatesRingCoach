<?php
declare(strict_types=1);

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'secure' => isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

function require_login(): void
{
    if (!isset($_SESSION['user_id'], $_SESSION['username'])
        || !is_int($_SESSION['user_id'])
        || !is_string($_SESSION['username'])
        || $_SESSION['username'] === '') {
        header('Location: login.php');
        exit;
    }
}
