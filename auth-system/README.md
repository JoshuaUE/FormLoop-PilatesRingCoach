# PHP Authentication Lab

A small PHP 8 and MySQL/MariaDB authentication system using PDO, server-side sessions, CSRF protection, account lockout, and one-time password reset codes. It has no framework or Composer dependencies.

## XAMPP Setup

1. Start **Apache** and **MySQL** from the XAMPP Control Panel.
2. Import `sql/schema.sql` through phpMyAdmin at `http://localhost/phpmyadmin`, or run:

   ```powershell
   C:\XAMPP\mysql\bin\mysql.exe -u root < C:\XAMPP\htdocs\auth-system\sql\schema.sql
   ```

3. Copy `config/db.example.php` to `config/db.php` and edit the constants if your database host, port, username, or password differs from the XAMPP defaults.
4. Place the `auth-system` folder in `C:\XAMPP\htdocs\`. From this repository, you can instead create a directory junction in an Administrator terminal:

   ```powershell
   New-Item -ItemType Junction -Path C:\XAMPP\htdocs\auth-system -Target C:\Users\Josh\Documents\project-Login-Website\auth-system
   ```

5. Ensure PHP/Apache can write to `storage/`. The application creates `storage/simulated_inbox.log` when a reset code is requested. `.htaccess` prevents that delivery file from being downloaded directly through Apache.
6. Open `http://localhost/auth-system/register.php`.

The default PDO settings are `127.0.0.1:3306`, database `auth_system`, user `root`, and an empty password.

## Manual Test Checklist

Use a fresh username for the registration tests. Database state can be inspected in phpMyAdmin without changing stored hashes.

### Registration

- Submit empty fields and confirm registration is rejected.
- Enter different password and confirmation values and confirm registration is rejected.
- Enter a password shorter than eight characters and confirm registration is rejected.
- Register a valid username containing only letters, numbers, or underscores and confirm redirection to login.
- Try to register the same username again and confirm it is rejected.
- Inspect `users.password_hash`; confirm it is a password hash and not the submitted password.

### Login And Lockout

- Enter one wrong password and confirm the message says `attempt 1 of 3`.
- Enter it again and confirm the message says `attempt 2 of 3`.
- Enter it a third time and confirm the account is locked for five minutes.
- Enter the correct password during lockout and confirm it is rejected with remaining lock time.
- Set `locked_until` to a past value in phpMyAdmin (or wait five minutes), then confirm the correct password logs in.
- After one or two failures, log in successfully and confirm `failed_attempts` is `0` and `locked_until` is `NULL`.

### Password Reset

- Request a reset for an unknown username and note the generic response.
- Request a reset for a known username and confirm the response is identical and contains no code.
- Open `simulated_inbox.php`, enter the username, and note the latest code.
- Try a wrong code and confirm it is rejected.
- Set the reset row's `expires_at` to a past value and confirm the code is rejected.
- Request a new code, reset the password successfully, and confirm the same code cannot be reused.
- Confirm the new password logs in and the old password no longer works.

### Session And Dashboard

- Open `dashboard.php` in a private browser without logging in and confirm it redirects to login.
- Log in and confirm the dashboard displays `Welcome, [Username]!` and `You have successfully logged in to the system.`
- Use the Logout button and confirm `dashboard.php` redirects to login afterward.
- Press the browser Back button after logout and confirm the authenticated dashboard is not restored.

### Security Review

- Confirm every POST form contains a CSRF token and rejects a missing or changed token.
- Confirm SQL statements use PDO prepared statements.
- Confirm displayed dynamic values pass through the `e()` escaping helper.
- Confirm database errors are logged by PHP and are not printed to the browser.

## CLI Syntax Check

From the project directory:

```powershell
Get-ChildItem -Recurse -Filter *.php | ForEach-Object { C:\XAMPP\php\php.exe -l $_.FullName }
```

The simulated inbox is intentionally a lab-only substitute for email. Its delivery log contains the code so the learner can retrieve it, while the `password_resets` table stores only a `password_hash()` result. Do not use this delivery mechanism in production.
