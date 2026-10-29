<?php
declare(strict_types=1);

require_once __DIR__ . '/connection.php';

if (session_status() !== PHP_SESSION_ACTIVE) {
    $isSecure = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
    session_set_cookie_params([
        'httponly' => true,
        'secure' => $isSecure,
        'samesite' => 'Lax',
    ]);
    session_start();
}

function e($value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function redirect(string $location): void
{
    header('Location: ' . $location);
    exit;
}

function require_login(): void
{
    if (empty($_SESSION['user_id'])) {
        redirect('login.php');
    }
}

function csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function verify_csrf(): void
{
    $submitted = $_POST['csrf_token'] ?? '';
    if (!is_string($submitted) || !hash_equals(csrf_token(), $submitted)) {
        http_response_code(400);
        exit('The form expired or could not be verified. Go back and try again.');
    }
}

function flash(string $message, string $type = 'success'): void
{
    $_SESSION['flash'] = ['message' => $message, 'type' => $type];
}

function take_flash(): ?array
{
    $message = $_SESSION['flash'] ?? null;
    unset($_SESSION['flash']);
    return $message;
}

function log_activity(PDO $pdo, int $userId, string $activity): void
{
    $statement = $pdo->prepare(
        'INSERT INTO activity_logs (user_id, activity, ip_address) VALUES (:user_id, :activity, :ip_address)'
    );
    $statement->execute([
        ':user_id' => $userId,
        ':activity' => function_exists('mb_substr') ? mb_substr($activity, 0, 255) : substr($activity, 0, 255),
        ':ip_address' => substr((string) ($_SERVER['REMOTE_ADDR'] ?? ''), 0, 45),
    ]);
}

function valid_amount($value): ?string
{
    if (!is_string($value) && !is_numeric($value)) {
        return null;
    }
    $amount = filter_var($value, FILTER_VALIDATE_FLOAT);
    if ($amount === false || !is_finite((float) $amount) || $amount <= 0 || $amount > 99999999.99) {
        return null;
    }
    return number_format((float) $amount, 2, '.', '');
}

function valid_date(string $date): bool
{
    $parsed = DateTimeImmutable::createFromFormat('!Y-m-d', $date);
    return $parsed !== false && $parsed->format('Y-m-d') === $date;
}
