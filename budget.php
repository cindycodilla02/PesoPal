<?php
require_once __DIR__ . '/includes/app.php';
require_login();

$userId = (int) $_SESSION['user_id'];
$error = '';
$editId = filter_var($_GET['edit'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
$editProvided = isset($_GET['edit']);
if ($editProvided && ($editId === false || $editId === null)) {
    http_response_code(400);
    exit('Invalid budget ID.');
}
$editing = $editId !== false && $editId !== null;
$form = ['amount' => '', 'start_date' => date('Y-m-01'), 'end_date' => date('Y-m-t')];

if ($editing) {
    $statement = $pdo->prepare('SELECT budget_id, budget_amount, start_date, end_date FROM budgets WHERE budget_id = :id AND user_id = :user_id');
    $statement->execute([':id' => $editId, ':user_id' => $userId]);
    $budgetToEdit = $statement->fetch();
    if (!$budgetToEdit) {
        http_response_code(404);
        exit('Budget not found.');
    }
    $form = ['amount' => $budgetToEdit['budget_amount'], 'start_date' => $budgetToEdit['start_date'], 'end_date' => $budgetToEdit['end_date']];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = (string) ($_POST['action'] ?? 'save');
    $postId = filter_var($_POST['budget_id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    $postIdProvided = isset($_POST['budget_id']);
    if (!in_array($action, ['save', 'delete'], true) || ($postIdProvided && ($postId === false || $postId === null))) {
        http_response_code(400);
        exit('Invalid budget action.');
    }
    if ($action === 'delete' && $postId !== false && $postId !== null) {
        $deletedRows = db_transaction($pdo, function (PDO $pdo) use ($postId, $userId): int {
            $statement = $pdo->prepare('DELETE FROM budgets WHERE budget_id = :id AND user_id = :user_id');
            $statement->execute([':id' => $postId, ':user_id' => $userId]);
            $deletedRows = $statement->rowCount();
            if ($deletedRows === 1) {
                log_activity($pdo, $userId, 'Deleted budget');
            }
            return $deletedRows;
        });
        if ($deletedRows !== 1) {
            http_response_code(404);
            exit('Budget not found.');
        }
        flash('Budget deleted.');
        redirect('budget.php');
    }

    $amount = valid_amount($_POST['amount'] ?? null);
    $start = trim((string) ($_POST['start_date'] ?? ''));
    $end = trim((string) ($_POST['end_date'] ?? ''));
    $form = ['amount' => (string) ($_POST['amount'] ?? ''), 'start_date' => $start, 'end_date' => $end];
    if ($amount === null || !valid_date($start) || !valid_date($end) || $start > $end) {
        $error = 'Enter a positive budget amount and a valid date range (end date must not be before start date).';
    } else {
        db_transaction($pdo, function (PDO $pdo) use ($postId, $amount, $start, $end, $userId): void {
            if ($postId !== false && $postId !== null) {
                $statement = $pdo->prepare('UPDATE budgets SET budget_amount = :amount, start_date = :start_date, end_date = :end_date WHERE budget_id = :id AND user_id = :user_id');
                $statement->execute([':amount' => $amount, ':start_date' => $start, ':end_date' => $end, ':id' => $postId, ':user_id' => $userId]);
                log_activity($pdo, $userId, 'Updated budget');
            } else {
                $statement = $pdo->prepare('INSERT INTO budgets (user_id, budget_amount, start_date, end_date) VALUES (:user_id, :amount, :start_date, :end_date)');
                $statement->execute([':user_id' => $userId, ':amount' => $amount, ':start_date' => $start, ':end_date' => $end]);
                log_activity($pdo, $userId, 'Created budget');
            }
        });
        flash($postId !== false && $postId !== null ? 'Budget updated.' : 'Budget saved.');
        redirect('budget.php');
    }
}

$statement = $pdo->prepare(
    'SELECT b.budget_id, b.budget_amount, b.start_date, b.end_date,
            COALESCE(SUM(e.amount), 0) AS spent
     FROM budgets b
     LEFT JOIN expenses e ON e.user_id = b.user_id AND e.expense_date BETWEEN b.start_date AND b.end_date
     WHERE b.user_id = :user_id
     GROUP BY b.budget_id, b.budget_amount, b.start_date, b.end_date
     ORDER BY b.start_date DESC, b.budget_id DESC'
);
$statement->execute([':user_id' => $userId]);
$budgets = $statement->fetchAll();

$pageTitle = 'Budgets';
require __DIR__ . '/includes/header.php';
?>
<section class="page-heading">
    <div><p class="eyebrow">Plan ahead</p><h1>Budget management</h1><p class="muted">Create spending limits for a date range and compare them with your recorded expenses.</p></div>
</section>
<section class="content-grid budget-layout">
    <article class="panel">
        <p class="eyebrow"><?= $editing ? 'Update plan' : 'New plan' ?></p>
        <h2><?= $editing ? 'Edit budget' : 'Set a budget' ?></h2>
        <?php if ($error !== ''): ?><div class="notice error" role="alert"><?= e($error) ?></div><?php endif; ?>
        <form method="post" class="stack">
            <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
            <input type="hidden" name="action" value="save">
            <?php if ($editing): ?><input type="hidden" name="budget_id" value="<?= (int) $editId ?>"><?php endif; ?>
            <label>Budget amount (₱)<input type="number" name="amount" min="0.01" max="99999999.99" step="0.01" value="<?= e($form['amount']) ?>" required></label>
            <label>Start date<input type="date" name="start_date" value="<?= e($form['start_date']) ?>" required></label>
            <label>End date<input type="date" name="end_date" value="<?= e($form['end_date']) ?>" required></label>
            <div class="button-row"><button class="button button-primary" type="submit"><?= $editing ? 'Save changes' : 'Create budget' ?></button><?php if ($editing): ?><a class="button button-quiet" href="budget.php">Cancel</a><?php endif; ?></div>
        </form>
    </article>
    <article class="panel">
        <p class="eyebrow">Your limits</p><h2>Budget history</h2>
        <?php if (!$budgets): ?><p class="empty-state">No budgets yet. Start with a weekly or monthly spending target.</p><?php endif; ?>
        <div class="budget-list">
        <?php foreach ($budgets as $budget): $amount = (float) $budget['budget_amount']; $spent = (float) $budget['spent']; $percent = $amount > 0 ? min(100, round($spent / $amount * 100)) : 0; ?>
            <div class="budget-item">
                <div class="panel-heading"><strong>₱<?= number_format($amount, 2) ?></strong><span class="<?= $spent > $amount ? 'text-danger' : 'muted' ?>"><?= number_format($spent / max($amount, 0.01) * 100, 0) ?>% used</span></div>
                <p class="muted"><?= e($budget['start_date']) ?> — <?= e($budget['end_date']) ?></p>
                <div class="bar-track"><span class="bar-fill <?= $spent > $amount ? 'bar-over' : '' ?>" style="width: <?= $percent ?>%"></span></div>
                <div class="button-row">
                    <a class="button button-small button-quiet" href="budget.php?edit=<?= (int) $budget['budget_id'] ?>">Edit</a>
                    <form method="post" onsubmit="return confirm('Delete this budget?')">
                        <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><input type="hidden" name="action" value="delete"><input type="hidden" name="budget_id" value="<?= (int) $budget['budget_id'] ?>">
                        <button class="button button-small button-danger" type="submit">Delete</button>
                    </form>
                </div>
            </div>
        <?php endforeach; ?>
        </div>
    </article>
</section>
<?php require __DIR__ . '/includes/footer.php'; ?>
