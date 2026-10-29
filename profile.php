<?php
require_once __DIR__ . '/includes/app.php';
require_login();

$userId = (int) $_SESSION['user_id'];
$statement = $pdo->prepare('SELECT first_name, last_name, email, student_number, course, year_level, password FROM users WHERE user_id = :user_id');
$statement->execute([':user_id' => $userId]);
$user = $statement->fetch();
if (!$user) {
    $_SESSION = [];
    redirect('login.php');
}

$error = '';
$profile = [
    'first_name' => $user['first_name'],
    'last_name' => $user['last_name'],
    'email' => $user['email'],
    'student_number' => $user['student_number'] ?? '',
    'course' => $user['course'] ?? '',
    'year_level' => $user['year_level'] ?? '',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = (string) ($_POST['action'] ?? '');
    if ($action === 'profile') {
        foreach ($profile as $field => $oldValue) {
            $profile[$field] = trim((string) ($_POST[$field] ?? ''));
        }
        if ($profile['first_name'] === '' || $profile['last_name'] === '' ||
            !filter_var($profile['email'], FILTER_VALIDATE_EMAIL) ||
            strlen($profile['first_name']) > 100 || strlen($profile['last_name']) > 100 ||
            strlen($profile['email']) > 100 || strlen($profile['student_number']) > 20 ||
            strlen($profile['course']) > 100 || strlen($profile['year_level']) > 10) {
            $error = 'Enter valid profile details. Required fields must not be blank.';
        } else {
            try {
                db_transaction($pdo, function (PDO $pdo) use ($profile, $userId): void {
                    $update = $pdo->prepare(
                        'UPDATE users SET first_name = :first_name, last_name = :last_name, email = :email,
                            student_number = :student_number, course = :course, year_level = :year_level
                         WHERE user_id = :user_id'
                    );
                    $update->execute([
                        ':first_name' => $profile['first_name'],
                        ':last_name' => $profile['last_name'],
                        ':email' => strtolower($profile['email']),
                        ':student_number' => $profile['student_number'] !== '' ? $profile['student_number'] : null,
                        ':course' => $profile['course'] !== '' ? $profile['course'] : null,
                        ':year_level' => $profile['year_level'] !== '' ? $profile['year_level'] : null,
                        ':user_id' => $userId,
                    ]);
                    log_activity($pdo, $userId, 'Updated profile');
                });
                $_SESSION['user_name'] = $profile['first_name'] . ' ' . $profile['last_name'];
                flash('Profile updated.');
                redirect('profile.php');
            } catch (PDOException $exception) {
                if ($exception->getCode() === '23000') {
                    $error = 'That email or student number is already in use.';
                } else {
                    throw $exception;
                }
            }
        }
    } elseif ($action === 'password') {
        $currentPassword = (string) ($_POST['current_password'] ?? '');
        $newPassword = (string) ($_POST['new_password'] ?? '');
        $confirmation = (string) ($_POST['new_password_confirmation'] ?? '');
        if (!password_verify($currentPassword, $user['password'])) {
            $error = 'Your current password is incorrect.';
        } elseif (strlen($newPassword) < 8) {
            $error = 'Your new password must contain at least 8 characters.';
        } elseif ($newPassword !== $confirmation) {
            $error = 'The new password confirmation does not match.';
        } else {
            db_transaction($pdo, function (PDO $pdo) use ($newPassword, $userId): void {
                $update = $pdo->prepare('UPDATE users SET password = :password WHERE user_id = :user_id');
                $update->execute([':password' => password_hash($newPassword, PASSWORD_DEFAULT), ':user_id' => $userId]);
                log_activity($pdo, $userId, 'Changed password');
            });
            flash('Password changed.');
            redirect('profile.php');
        }
    } else {
        http_response_code(400);
        exit('Invalid profile action.');
    }
}

$pageTitle = 'Profile';
require __DIR__ . '/includes/header.php';
?>
<section class="page-heading"><div><p class="eyebrow">Your account</p><h1>Profile settings</h1><p class="muted">Update student details and keep your account credentials current.</p></div></section>
<?php if ($error !== ''): ?><div class="notice error" role="alert"><?= e($error) ?></div><?php endif; ?>
<section class="content-grid profile-layout">
    <article class="panel">
        <p class="eyebrow">Personal information</p><h2>Student profile</h2>
        <form method="post" class="form-grid">
            <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><input type="hidden" name="action" value="profile">
            <label>First name<input name="first_name" value="<?= e($profile['first_name']) ?>" maxlength="100" required></label>
            <label>Last name<input name="last_name" value="<?= e($profile['last_name']) ?>" maxlength="100" required></label>
            <label class="span-two">Email<input type="email" name="email" value="<?= e($profile['email']) ?>" maxlength="100" required></label>
            <label>Student number<input name="student_number" value="<?= e($profile['student_number']) ?>" maxlength="20"></label>
            <label>Course<input name="course" value="<?= e($profile['course']) ?>" maxlength="100"></label>
            <label>Year level<input name="year_level" value="<?= e($profile['year_level']) ?>" maxlength="10"></label>
            <div class="span-two"><button class="button button-primary" type="submit">Save profile</button></div>
        </form>
    </article>
    <article class="panel">
        <p class="eyebrow">Account security</p><h2>Change password</h2>
        <form method="post" class="stack">
            <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><input type="hidden" name="action" value="password">
            <label>Current password
                <span class="password-field">
                    <input type="password" name="current_password" autocomplete="current-password" required>
                    <button class="password-toggle" type="button" aria-label="Show password" aria-pressed="false">
                        <svg class="eye-icon" viewBox="0 0 24 24" aria-hidden="true"><path d="M2 12s3.6-7 10-7 10 7 10 7-3.6 7-10 7S2 12 2 12Z"/><circle cx="12" cy="12" r="3"/></svg>
                    </button>
                </span>
            </label>
            <label>New password
                <span class="password-field">
                    <input type="password" name="new_password" minlength="8" autocomplete="new-password" required>
                    <button class="password-toggle" type="button" aria-label="Show password" aria-pressed="false">
                        <svg class="eye-icon" viewBox="0 0 24 24" aria-hidden="true"><path d="M2 12s3.6-7 10-7 10 7 10 7-3.6 7-10 7S2 12 2 12Z"/><circle cx="12" cy="12" r="3"/></svg>
                    </button>
                </span>
            </label>
            <label>Confirm new password
                <span class="password-field">
                    <input type="password" name="new_password_confirmation" minlength="8" autocomplete="new-password" required>
                    <button class="password-toggle" type="button" aria-label="Show password" aria-pressed="false">
                        <svg class="eye-icon" viewBox="0 0 24 24" aria-hidden="true"><path d="M2 12s3.6-7 10-7 10 7 10 7-3.6 7-10 7S2 12 2 12Z"/><circle cx="12" cy="12" r="3"/></svg>
                    </button>
                </span>
            </label>
            <button class="button button-primary" type="submit">Change password</button>
        </form>
    </article>
</section>
<?php require __DIR__ . '/includes/footer.php'; ?>
