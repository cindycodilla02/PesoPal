<?php
require_once __DIR__ . '/includes/app.php';

if (!empty($_SESSION['user_id'])) {
    redirect('index.php');
}

$error = '';
$values = [
    'first_name' => '',
    'last_name' => '',
    'email' => '',
    'student_number' => '',
    'course' => '',
    'year_level' => '',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    foreach ($values as $field => $unused) {
        $values[$field] = trim((string) ($_POST[$field] ?? ''));
    }
    $password = (string) ($_POST['password'] ?? '');
    $confirmation = (string) ($_POST['password_confirmation'] ?? '');
    if ($values['first_name'] === '' || $values['last_name'] === '' ||
        !filter_var($values['email'], FILTER_VALIDATE_EMAIL) ||
        strlen($values['first_name']) > 100 || strlen($values['last_name']) > 100 ||
        strlen($values['email']) > 100 || strlen($values['student_number']) > 20 ||
        strlen($values['course']) > 100 || strlen($values['year_level']) > 10) {
        $error = 'Complete the required fields using valid values.';
    } elseif (strlen($password) < 8) {
        $error = 'Your password must contain at least 8 characters.';
    } elseif ($password !== $confirmation) {
        $error = 'The password confirmation does not match.';
    } else {
        try {
            $userId = db_transaction($pdo, function (PDO $pdo) use ($values, $password): int {
                $statement = $pdo->prepare(
                    'INSERT INTO users (first_name, last_name, email, password, student_number, course, year_level)
                     VALUES (:first_name, :last_name, :email, :password, :student_number, :course, :year_level)'
                );
                $statement->execute([
                    ':first_name' => $values['first_name'],
                    ':last_name' => $values['last_name'],
                    ':email' => strtolower($values['email']),
                    ':password' => password_hash($password, PASSWORD_DEFAULT),
                    ':student_number' => $values['student_number'] !== '' ? $values['student_number'] : null,
                    ':course' => $values['course'] !== '' ? $values['course'] : null,
                    ':year_level' => $values['year_level'] !== '' ? $values['year_level'] : null,
                ]);
                $userId = (int) $pdo->lastInsertId();
                log_activity($pdo, $userId, 'Created account');
                return $userId;
            });
            session_regenerate_id(true);
            $_SESSION['user_id'] = $userId;
            $_SESSION['user_name'] = $values['first_name'] . ' ' . $values['last_name'];
            flash('Your account is ready. Start by recording an allowance or expense.');
            redirect('index.php');
        } catch (PDOException $exception) {
            if ($exception->getCode() === '23000') {
                $error = 'That email or student number is already registered.';
            } else {
                throw $exception;
            }
        }
    }
}

$pageTitle = 'Create account';
require __DIR__ . '/includes/header.php';
?>
<section class="auth-card auth-card-wide">
    <p class="eyebrow">Student account</p>
    <h1>Create your ledger</h1>
    <p class="muted">Your records are private to your account.</p>
    <?php if ($error !== ''): ?><div class="notice error" role="alert"><?= e($error) ?></div><?php endif; ?>
    <form method="post" class="form-grid">
        <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
        <label>First name<input name="first_name" value="<?= e($values['first_name']) ?>" required maxlength="100" autocomplete="given-name"></label>
        <label>Last name<input name="last_name" value="<?= e($values['last_name']) ?>" required maxlength="100" autocomplete="family-name"></label>
        <label class="span-two">Email<input type="email" name="email" value="<?= e($values['email']) ?>" required maxlength="100" autocomplete="email"></label>
        <label>Student number <span class="optional">(optional)</span><input name="student_number" value="<?= e($values['student_number']) ?>" maxlength="20"></label>
        <label>Course <span class="optional">(optional)</span><input name="course" value="<?= e($values['course']) ?>" maxlength="100"></label>
        <label>Year level <span class="optional">(optional)</span><input name="year_level" value="<?= e($values['year_level']) ?>" maxlength="10" placeholder="e.g. 2"></label>
        <label>Password
            <span class="password-field">
                <input type="password" name="password" required minlength="8" autocomplete="new-password">
                <button class="password-toggle" type="button" aria-label="Show password" aria-pressed="false">
                    <svg class="eye-icon" viewBox="0 0 24 24" aria-hidden="true"><path d="M2 12s3.6-7 10-7 10 7 10 7-3.6 7-10 7S2 12 2 12Z"/><circle cx="12" cy="12" r="3"/></svg>
                </button>
            </span>
        </label>
        <label>Confirm password
            <span class="password-field">
                <input type="password" name="password_confirmation" required minlength="8" autocomplete="new-password">
                <button class="password-toggle" type="button" aria-label="Show password" aria-pressed="false">
                    <svg class="eye-icon" viewBox="0 0 24 24" aria-hidden="true"><path d="M2 12s3.6-7 10-7 10 7 10 7-3.6 7-10 7S2 12 2 12Z"/><circle cx="12" cy="12" r="3"/></svg>
                </button>
            </span>
        </label>
        <div class="span-two"><button class="button button-primary" type="submit">Create account</button></div>
    </form>
    <p class="auth-switch">Already registered? <a href="login.php">Sign in</a></p>
</section>
<?php require __DIR__ . '/includes/footer.php'; ?>
