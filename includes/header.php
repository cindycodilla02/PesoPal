<?php
$pageTitle = $pageTitle ?? 'PesoPal';
$notice = take_flash();
$currentPage = basename($_SERVER['SCRIPT_NAME'] ?? '');
$isDashboardPage = in_array($currentPage, ['index.php', 'transaction.php', 'editExpense.php', 'deleteExpense.php'], true);
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($pageTitle) ?> | PesoPal</title>
    <link rel="stylesheet" href="assets/style.css">
</head>
<body>
<header class="topbar">
    <a class="brand" href="<?= isset($_SESSION['user_id']) ? 'index.php' : 'login.php' ?>">
        <span class="brand-mark">₱</span>
        <span>PesoPal<small>Expense &amp; allowance management</small></span>
    </a>
    <?php if (isset($_SESSION['user_id'])): ?>
        <nav class="nav-links" aria-label="Main navigation">
            <a href="index.php" <?= $isDashboardPage ? 'aria-current="page"' : '' ?>>Dashboard</a>
            <a href="budget.php" <?= $currentPage === 'budget.php' ? 'aria-current="page"' : '' ?>>Budgets</a>
            <a href="reports.php" <?= $currentPage === 'reports.php' ? 'aria-current="page"' : '' ?>>Reports</a>
            <a href="profile.php" <?= $currentPage === 'profile.php' ? 'aria-current="page"' : '' ?>>Profile</a>
        </nav>
        <div class="user-menu">
            <span><?= e($_SESSION['user_name'] ?? 'Student') ?></span>
            <form method="post" action="logout.php">
                <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                <button class="button button-quiet" type="submit">Sign out</button>
            </form>
        </div>
    <?php endif; ?>
</header>
<main class="container">
    <?php if ($notice): ?>
        <div class="notice <?= e($notice['type']) ?>" role="status"><?= e($notice['message']) ?></div>
    <?php endif; ?>
