<?php
require_once __DIR__ . '/includes/app.php';
require_login();

$userId = (int) $_SESSION['user_id'];
$today = date('Y-m-d');
$monthStart = date('Y-m-01');
$monthEnd = date('Y-m-t');

$summaryStatement = $pdo->prepare(
    'SELECT
        (SELECT COALESCE(SUM(amount), 0) FROM allowances WHERE user_id = :allowance_user) AS total_income,
        (SELECT COALESCE(SUM(amount), 0) FROM expenses WHERE user_id = :expense_user) AS total_expense,
        (SELECT COALESCE(SUM(amount), 0) FROM allowances WHERE user_id = :month_allowance_user AND allowance_date BETWEEN :month_start AND :month_income_end) AS month_income,
        (SELECT COALESCE(SUM(amount), 0) FROM expenses WHERE user_id = :month_expense_user AND expense_date BETWEEN :month_expense_start AND :month_expense_end) AS month_expense'
);
$summaryStatement->execute([
    ':allowance_user' => $userId,
    ':expense_user' => $userId,
    ':month_allowance_user' => $userId,
    ':month_start' => $monthStart,
    ':month_income_end' => $monthEnd,
    ':month_expense_user' => $userId,
    ':month_expense_start' => $monthStart,
    ':month_expense_end' => $monthEnd,
]);
$summary = $summaryStatement->fetch();
$balance = (float) $summary['total_income'] - (float) $summary['total_expense'];
$monthBalance = (float) $summary['month_income'] - (float) $summary['month_expense'];

$budgetStatement = $pdo->prepare(
    'SELECT budget_amount, start_date, end_date FROM budgets
     WHERE user_id = :user_id AND start_date <= :today_start AND end_date >= :today_end
     ORDER BY created_at DESC LIMIT 1'
);
$budgetStatement->execute([':user_id' => $userId, ':today_start' => $today, ':today_end' => $today]);
$currentBudget = $budgetStatement->fetch();
$budgetSpent = 0.0;
if ($currentBudget) {
    $budgetSpendQuery = $pdo->prepare(
        'SELECT COALESCE(SUM(amount), 0) FROM expenses
         WHERE user_id = :user_id AND expense_date BETWEEN :start_date AND :end_date'
    );
    $budgetSpendQuery->execute([
        ':user_id' => $userId,
        ':start_date' => $currentBudget['start_date'],
        ':end_date' => $currentBudget['end_date'],
    ]);
    $budgetSpent = (float) $budgetSpendQuery->fetchColumn();
}

$categories = $pdo->query('SELECT category_id, category_name FROM categories ORDER BY category_name')->fetchAll();
$categoryStatement = $pdo->prepare(
    'SELECT c.category_name, COALESCE(SUM(e.amount), 0) AS total
     FROM categories c
     LEFT JOIN expenses e ON e.category_id = c.category_id AND e.user_id = :user_id
     GROUP BY c.category_id, c.category_name
     ORDER BY total DESC, c.category_name'
);
$categoryStatement->execute([':user_id' => $userId]);
$categoryTotals = $categoryStatement->fetchAll();
$maxCategoryTotal = $categoryTotals ? (float) $categoryTotals[0]['total'] : 0;

$search = trim((string) ($_GET['search'] ?? ''));
$search = function_exists('mb_substr') ? mb_substr($search, 0, 120) : substr($search, 0, 120);
$categoryFilter = trim((string) ($_GET['category_id'] ?? ''));
$from = trim((string) ($_GET['from'] ?? ''));
$to = trim((string) ($_GET['to'] ?? ''));
$filterError = '';
if (($from !== '' && !valid_date($from)) || ($to !== '' && !valid_date($to)) ||
    ($from !== '' && $to !== '' && $from > $to)) {
    $filterError = 'Choose a valid date range.';
    $from = $to = '';
}
$selectedCategory = $categoryFilter !== '' ? filter_var($categoryFilter, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) : null;
if ($categoryFilter !== '' && $selectedCategory === false) {
    $filterError = 'Choose a valid category.';
    $selectedCategory = null;
    $categoryFilter = '';
}

$sql = 'SELECT * FROM (
            SELECT e.expense_id AS id, e.user_id, e.category_id, c.category_name,
                   e.amount, e.expense_date AS transaction_date, e.description,
                   \'Expense\' AS transaction_type, NULL AS allowance_type
            FROM expenses e
            JOIN categories c ON c.category_id = e.category_id
            WHERE e.user_id = :expense_user
            UNION ALL
            SELECT a.allowance_id AS id, a.user_id, NULL AS category_id, NULL AS category_name,
                   a.amount, a.allowance_date AS transaction_date, a.notes AS description,
                   \'Allowance\' AS transaction_type, a.allowance_type
            FROM allowances a
            WHERE a.user_id = :allowance_user
        ) transactions WHERE 1 = 1';
$params = [':expense_user' => $userId, ':allowance_user' => $userId];
if ($search !== '') {
    $sql .= ' AND (description LIKE :search_text OR CAST(amount AS CHAR) LIKE :search_amount OR category_name LIKE :search_category OR transaction_type LIKE :search_type)';
    $pattern = '%' . $search . '%';
    $params[':search_text'] = $pattern;
    $params[':search_amount'] = $pattern;
    $params[':search_category'] = $pattern;
    $params[':search_type'] = $pattern;
}
if ($from !== '') {
    $sql .= ' AND transaction_date >= :date_from';
    $params[':date_from'] = $from;
}
if ($to !== '') {
    $sql .= ' AND transaction_date <= :date_to';
    $params[':date_to'] = $to;
}
if ($selectedCategory !== null) {
    $sql .= ' AND category_id = :category_id';
    $params[':category_id'] = (int) $selectedCategory;
}
$sql .= ' ORDER BY transaction_date DESC, id DESC LIMIT 300';
$transactionStatement = $pdo->prepare($sql);
$transactionStatement->execute($params);
$transactions = $transactionStatement->fetchAll();

$pageTitle = 'Dashboard';
require __DIR__ . '/includes/header.php';
?>
<section class="page-heading">
    <div>
        <p class="eyebrow">Your money, made clearer</p>
        <h1>Hello, <?= e(explode(' ', $_SESSION['user_name'] ?? 'Student')[0]) ?>.</h1>
        <p class="muted">Track each allowance and expense, then use the summaries to plan what comes next.</p>
    </div>
    <div class="button-row">
        <a class="button button-primary" href="transaction.php?type=allowance">＋ Add allowance</a>
        <a class="button button-secondary" href="transaction.php?type=expense">− Add expense</a>
    </div>
</section>

<section class="stats-grid" aria-label="Financial summary">
    <article class="stat-card"><span>All-time allowance</span><strong>₱<?= number_format((float) $summary['total_income'], 2) ?></strong><small>All allowance records</small></article>
    <article class="stat-card"><span>All-time expenses</span><strong>₱<?= number_format((float) $summary['total_expense'], 2) ?></strong><small>All expense records</small></article>
    <article class="stat-card <?= $balance < 0 ? 'stat-negative' : '' ?>"><span>Remaining balance</span><strong>₱<?= number_format($balance, 2) ?></strong><small>Allowance minus expenses</small></article>
    <article class="stat-card"><span>This month</span><strong>₱<?= number_format($monthBalance, 2) ?></strong><small>Income less spending in <?= e(date('F')) ?></small></article>
</section>

<section class="content-grid">
    <article class="panel">
        <div class="panel-heading">
            <div><p class="eyebrow">Spending overview</p><h2>Expenses by category</h2></div>
            <a href="reports.php">View reports →</a>
        </div>
        <?php if ($maxCategoryTotal > 0): ?>
            <div class="category-bars">
                <?php foreach ($categoryTotals as $category): $percentage = round(((float) $category['total'] / $maxCategoryTotal) * 100); ?>
                    <div class="category-row">
                        <span><?= e($category['category_name']) ?></span>
                        <span class="bar-track"><span class="bar-fill" style="width: <?= $percentage ?>%"></span></span>
                        <strong>₱<?= number_format((float) $category['total'], 2) ?></strong>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <p class="empty-state">No expenses yet. Record an expense to see category summaries.</p>
        <?php endif; ?>
    </article>
    <article class="panel budget-preview">
        <p class="eyebrow">Spending plan</p>
        <h2>Current budget</h2>
        <?php if ($currentBudget): ?>
            <?php $budgetAmount = (float) $currentBudget['budget_amount']; $budgetPercent = $budgetAmount > 0 ? min(100, round(($budgetSpent / $budgetAmount) * 100)) : 0; ?>
            <strong class="budget-amount">₱<?= number_format($budgetSpent, 2) ?> <span>of ₱<?= number_format($budgetAmount, 2) ?></span></strong>
            <div class="bar-track budget-track"><span class="bar-fill <?= $budgetSpent > $budgetAmount ? 'bar-over' : '' ?>" style="width: <?= $budgetPercent ?>%"></span></div>
            <p class="muted">Budget dates: <?= e($currentBudget['start_date']) ?> to <?= e($currentBudget['end_date']) ?></p>
        <?php else: ?>
            <p class="empty-state">Set a budget period to monitor your spending limit.</p>
        <?php endif; ?>
        <a class="button button-quiet" href="budget.php">Manage budgets</a>
    </article>
</section>

<section class="panel transaction-panel">
    <div class="panel-heading">
        <div><p class="eyebrow">Your records</p><h2>Transactions</h2></div>
    </div>
    <?php if ($filterError !== ''): ?><div class="notice error"><?= e($filterError) ?></div><?php endif; ?>
    <form class="filter-form" method="get">
        <label>Search<input name="search" value="<?= e($search) ?>" placeholder="Description, type, category, amount"></label>
        <label>Category
            <select name="category_id">
                <option value="">All categories</option>
                <?php foreach ($categories as $category): ?>
                    <option value="<?= (int) $category['category_id'] ?>" <?= (string) $category['category_id'] === (string) $categoryFilter ? 'selected' : '' ?>><?= e($category['category_name']) ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <label>From<input type="date" name="from" value="<?= e($from) ?>"></label>
        <label>To<input type="date" name="to" value="<?= e($to) ?>"></label>
        <div class="filter-actions"><button class="button button-primary" type="submit">Filter</button><a class="button button-quiet" href="index.php">Clear</a></div>
    </form>
    <div class="table-wrap">
        <table>
            <thead><tr><th>Date</th><th>Type</th><th>Category / frequency</th><th>Description / notes</th><th>Amount</th><th>Actions</th></tr></thead>
            <tbody>
            <?php foreach ($transactions as $transaction): ?>
                <?php $isExpense = $transaction['transaction_type'] === 'Expense'; $type = $isExpense ? 'expense' : 'allowance'; ?>
                <tr>
                    <td><?= e($transaction['transaction_date']) ?></td>
                    <td><span class="type-pill <?= $isExpense ? 'type-expense' : 'type-income' ?>"><?= e($transaction['transaction_type']) ?></span></td>
                    <td><?= e($isExpense ? $transaction['category_name'] : $transaction['allowance_type']) ?></td>
                    <td><?= e($transaction['description'] ?? '—') ?></td>
                    <td class="money <?= $isExpense ? 'money-expense' : 'money-income' ?>"><?= $isExpense ? '−' : '+' ?>₱<?= number_format((float) $transaction['amount'], 2) ?></td>
                    <td>
                        <div class="table-actions">
                            <a class="button button-small button-quiet" href="transaction.php?type=<?= e($type) ?>&amp;id=<?= (int) $transaction['id'] ?>">Edit</a>
                            <form method="post" action="transaction.php?type=<?= e($type) ?>&amp;id=<?= (int) $transaction['id'] ?>" onsubmit="return confirm('Delete this transaction?')">
                                <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                                <input type="hidden" name="type" value="<?= e($type) ?>">
                                <input type="hidden" name="id" value="<?= (int) $transaction['id'] ?>">
                                <input type="hidden" name="action" value="delete">
                                <button class="button button-small button-danger" type="submit">Delete</button>
                            </form>
                        </div>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$transactions): ?><tr><td colspan="6" class="empty-state">No records match these filters. Add an allowance or expense to begin.</td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>
</section>
<?php require __DIR__ . '/includes/footer.php'; ?>
