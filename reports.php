<?php
require_once __DIR__ . '/includes/app.php';
require_login();

$userId = (int) $_SESSION['user_id'];
$period = (string) ($_GET['period'] ?? $_POST['period'] ?? 'weekly');
$period = in_array($period, ['weekly', 'monthly'], true) ? $period : 'weekly';
$anchor = trim((string) ($_GET['date'] ?? $_POST['date'] ?? date('Y-m-d')));
if (!valid_date($anchor)) {
    $anchor = date('Y-m-d');
}
$anchorDate = new DateTimeImmutable($anchor);
if ($period === 'weekly') {
    $startDate = $anchorDate->modify('monday this week')->format('Y-m-d');
    $endDate = $anchorDate->modify('sunday this week')->format('Y-m-d');
    $reportType = 'Weekly';
} else {
    $startDate = $anchorDate->modify('first day of this month')->format('Y-m-d');
    $endDate = $anchorDate->modify('last day of this month')->format('Y-m-d');
    $reportType = 'Monthly';
}

$incomeQuery = $pdo->prepare('SELECT COALESCE(SUM(amount), 0) FROM allowances WHERE user_id = :user_id AND allowance_date BETWEEN :start_date AND :end_date');
$incomeQuery->execute([':user_id' => $userId, ':start_date' => $startDate, ':end_date' => $endDate]);
$income = (float) $incomeQuery->fetchColumn();
$expenseQuery = $pdo->prepare('SELECT COALESCE(SUM(amount), 0) FROM expenses WHERE user_id = :user_id AND expense_date BETWEEN :start_date AND :end_date');
$expenseQuery->execute([':user_id' => $userId, ':start_date' => $startDate, ':end_date' => $endDate]);
$expenseTotal = (float) $expenseQuery->fetchColumn();
$remaining = $income - $expenseTotal;

$categoryQuery = $pdo->prepare(
    'SELECT c.category_name, COALESCE(SUM(e.amount), 0) AS total
     FROM categories c
     LEFT JOIN expenses e ON e.category_id = c.category_id AND e.user_id = :user_id
        AND e.expense_date BETWEEN :expense_start AND :expense_end
     GROUP BY c.category_id, c.category_name
     HAVING total > 0
     ORDER BY total DESC'
);
$categoryQuery->execute([':user_id' => $userId, ':expense_start' => $startDate, ':expense_end' => $endDate]);
$categoryTotals = $categoryQuery->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'save_report') {
    verify_csrf();
    db_transaction($pdo, function (PDO $pdo) use ($userId, $reportType, $income, $expenseTotal, $remaining): void {
        $statement = $pdo->prepare(
            'INSERT INTO reports (user_id, report_type, total_income, total_expense, remaining_balance)
             VALUES (:user_id, :report_type, :total_income, :total_expense, :remaining_balance)'
        );
        $statement->execute([
            ':user_id' => $userId,
            ':report_type' => $reportType,
            ':total_income' => number_format($income, 2, '.', ''),
            ':total_expense' => number_format($expenseTotal, 2, '.', ''),
            ':remaining_balance' => number_format($remaining, 2, '.', ''),
        ]);
        log_activity($pdo, $userId, 'Saved ' . strtolower($reportType) . ' report');
    });
    flash('Report snapshot saved.');
    redirect('reports.php?period=' . $period . '&date=' . $anchor);
}

if (($_GET['export'] ?? '') === 'csv') {
    $filename = strtolower($reportType) . '-report-' . $startDate . '.csv';
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    $output = fopen('php://output', 'w');
    fwrite($output, "\xEF\xBB\xBF");
    fputcsv($output, ['Student Expense and Allowance Management System']);
    fputcsv($output, [$reportType . ' report', $startDate . ' to ' . $endDate]);
    fputcsv($output, ['Total allowance', number_format($income, 2, '.', '')]);
    fputcsv($output, ['Total expenses', number_format($expenseTotal, 2, '.', '')]);
    fputcsv($output, ['Remaining balance', number_format($remaining, 2, '.', '')]);
    fputcsv($output, []);
    fputcsv($output, ['Expense category', 'Amount']);
    foreach ($categoryTotals as $row) {
        fputcsv($output, [$row['category_name'], number_format((float) $row['total'], 2, '.', '')]);
    }
    fclose($output);
    exit;
}

$savedQuery = $pdo->prepare('SELECT report_type, generated_at, total_income, total_expense, remaining_balance FROM reports WHERE user_id = :user_id ORDER BY generated_at DESC LIMIT 10');
$savedQuery->execute([':user_id' => $userId]);
$savedReports = $savedQuery->fetchAll();

$pageTitle = 'Financial reports';
require __DIR__ . '/includes/header.php';
?>
<section class="page-heading">
    <div><p class="eyebrow">Understand your habits</p><h1>Financial reports</h1><p class="muted">Compare allowance and expenses for a week or month, with category totals and export options.</p></div>
</section>
<section class="panel report-panel">
    <form method="get" class="filter-form report-filter">
        <label>Report period<select name="period"><option value="weekly" <?= $period === 'weekly' ? 'selected' : '' ?>>Weekly</option><option value="monthly" <?= $period === 'monthly' ? 'selected' : '' ?>>Monthly</option></select></label>
        <label>Choose any date in period<input type="date" name="date" value="<?= e($anchor) ?>"></label>
        <div class="filter-actions"><button class="button button-primary" type="submit">Generate report</button></div>
    </form>
    <div class="report-title"><div><p class="eyebrow"><?= e($reportType) ?> summary</p><h2><?= e($startDate) ?> <span class="muted">to</span> <?= e($endDate) ?></h2></div>
        <div class="button-row no-print"><a class="button button-secondary" href="reports.php?period=<?= e($period) ?>&amp;date=<?= e($anchor) ?>&amp;export=csv">Export CSV / Excel</a><button class="button button-quiet" type="button" onclick="window.print()">Print / Save PDF</button></div>
    </div>
    <div class="stats-grid report-stats">
        <article class="stat-card"><span>Total allowance</span><strong>₱<?= number_format($income, 2) ?></strong></article>
        <article class="stat-card"><span>Total expenses</span><strong>₱<?= number_format($expenseTotal, 2) ?></strong></article>
        <article class="stat-card <?= $remaining < 0 ? 'stat-negative' : '' ?>"><span>Remaining balance</span><strong>₱<?= number_format($remaining, 2) ?></strong></article>
    </div>
    <div class="report-title"><h2>Expense breakdown</h2>
        <form method="post">
            <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><input type="hidden" name="action" value="save_report"><input type="hidden" name="period" value="<?= e($period) ?>"><input type="hidden" name="date" value="<?= e($anchor) ?>">
            <button class="button button-small button-quiet no-print" type="submit">Save report snapshot</button>
        </form>
    </div>
    <div class="table-wrap"><table><thead><tr><th>Category</th><th>Expenses</th></tr></thead><tbody>
        <?php foreach ($categoryTotals as $row): ?><tr><td><?= e($row['category_name']) ?></td><td>₱<?= number_format((float) $row['total'], 2) ?></td></tr><?php endforeach; ?>
        <?php if (!$categoryTotals): ?><tr><td colspan="2" class="empty-state">No expenses recorded during this period.</td></tr><?php endif; ?>
    </tbody></table></div>
</section>
<section class="panel saved-reports">
    <p class="eyebrow">Report history</p><h2>Saved snapshots</h2>
    <div class="table-wrap"><table><thead><tr><th>Type</th><th>Generated</th><th>Allowance</th><th>Expenses</th><th>Balance</th></tr></thead><tbody>
        <?php foreach ($savedReports as $report): ?><tr><td><?= e($report['report_type']) ?></td><td><?= e($report['generated_at']) ?></td><td>₱<?= number_format((float) $report['total_income'], 2) ?></td><td>₱<?= number_format((float) $report['total_expense'], 2) ?></td><td>₱<?= number_format((float) $report['remaining_balance'], 2) ?></td></tr><?php endforeach; ?>
        <?php if (!$savedReports): ?><tr><td colspan="5" class="empty-state">Saved report snapshots will appear here.</td></tr><?php endif; ?>
    </tbody></table></div>
</section>
<?php require __DIR__ . '/includes/footer.php'; ?>
