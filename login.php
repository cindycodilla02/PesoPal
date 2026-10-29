<?php
require_once __DIR__ . '/includes/app.php';

if (!empty($_SESSION['user_id'])) {
    redirect('index.php');
}

$error = '';
$email = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $email = strtolower(trim((string) ($_POST['login_email'] ?? '')));
    $password = (string) ($_POST['password'] ?? '');
    if (!filter_var($email, FILTER_VALIDATE_EMAIL) || $password === '') {
        $error = 'Enter a valid email address and password.';
    } else {
        $statement = $pdo->prepare(
            'SELECT user_id, first_name, last_name, email, password FROM users WHERE email = :email LIMIT 1'
        );
        $statement->execute([':email' => $email]);
        $user = $statement->fetch();
        $passwordMatches = $user && password_verify($password, $user['password']);
        $legacyPassword = $user && password_get_info($user['password'])['algo'] === null
            && hash_equals((string) $user['password'], $password);
        if (!$user || (!$passwordMatches && !$legacyPassword)) {
            $error = 'The email or password is incorrect.';
        } else {
            db_transaction($pdo, function (PDO $pdo) use ($legacyPassword, $password, $user): void {
                if ($legacyPassword) {
                    $upgrade = $pdo->prepare('UPDATE users SET password = :password WHERE user_id = :user_id');
                    $upgrade->execute([
                        ':password' => password_hash($password, PASSWORD_DEFAULT),
                        ':user_id' => (int) $user['user_id'],
                    ]);
                }
                log_activity($pdo, (int) $user['user_id'], 'Signed in');
            });
            session_regenerate_id(true);
            $_SESSION['user_id'] = (int) $user['user_id'];
            $_SESSION['user_name'] = trim($user['first_name'] . ' ' . $user['last_name']);
            redirect('index.php');
        }
    }
}

$pageTitle = 'Sign in';
require __DIR__ . '/includes/header.php';
?>
<section class="auth-card">
    <p class="eyebrow">Welcome back</p>
    <h1>Sign in to your ledger</h1>
    <p class="muted">Keep your allowance and spending organized in one place.</p>
    <?php if ($error !== ''): ?><div class="notice error" role="alert"><?= e($error) ?></div><?php endif; ?>
    <form method="post" class="stack">
        <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
        <label>Email address
            <input type="email" name="login_email" value="<?= e($email) ?>" autocomplete="off" autocapitalize="none" spellcheck="false" required maxlength="100" readonly onfocus="this.removeAttribute('readonly')">
        </label>
        <label>Password
            <span class="password-field">
                <input type="password" name="password" autocomplete="current-password" required>
                <button class="password-toggle" type="button" aria-label="Show password" aria-pressed="false">
                    <svg class="eye-icon" viewBox="0 0 24 24" aria-hidden="true"><path d="M2 12s3.6-7 10-7 10 7 10 7-3.6 7-10 7S2 12 2 12Z"/><circle cx="12" cy="12" r="3"/></svg>
                </button>
            </span>
        </label>
        <button class="button button-primary sign-in-button" type="submit">Sign in</button>
    </form>
    <p class="auth-switch">New here? <a href="register.php">Create a student account</a></p>
</section>
<?php require __DIR__ . '/includes/footer.php'; ?>
