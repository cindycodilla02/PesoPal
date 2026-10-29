<?php
require_once __DIR__ . '/includes/app.php';
require_login();

$type = (string) ($_GET['type'] ?? $_POST['type'] ?? '');
if (!in_array($type, ['expense', 'allowance'], true)) {
    http_response_code(400);
    exit('Invalid transaction type.');
}
$isExpense = $type === 'expense';
$table = $isExpense ? 'expenses' : 'allowances';
$idColumn = $isExpense ? 'expense_id' : 'allowance_id';
$id = filter_var($_GET['id'] ?? $_POST['id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
$idProvided = isset($_GET['id']) || isset($_POST['id']);
if ($idProvided && ($id === false || $id === null)) {
    http_response_code(400);
    exit('Invalid transaction ID.');
}
$editing = $id !== false && $id !== null;
$userId = (int) $_SESSION['user_id'];
$error = '';
$categories = $isExpense
    ? $pdo->query('SELECT category_id, category_name FROM categories ORDER BY category_name')->fetchAll()
    : [];
$record = [
    'amount' => '',
    'date' => date('Y-m-d'),
    'kind' => 'Weekly',
    'category_id' => '',
    'description' => '',
];

if ($editing) {
    $query = "SELECT * FROM {$table} WHERE {$idColumn} = :id AND user_id = :user_id";
    $statement = $pdo->prepare($query);
    $statement->execute([':id' => $id, ':user_id' => $userId]);
    $existing = $statement->fetch();
    if (!$existing) {
        http_response_code(404);
        exit('Transaction not found.');
    }
    $record = [
        'amount' => $existing['amount'],
        'date' => $isExpense ? $existing['expense_date'] : $existing['allowance_date'],
        'kind' => $isExpense ? 'Weekly' : $existing['allowance_type'],
        'category_id' => $isExpense ? $existing['category_id'] : '',
        'description' => $isExpense ? $existing['description'] : ($existing['notes'] ?? ''),
    ];
}

$action = (string) ($_POST['action'] ?? 'save');
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !in_array($action, ['save', 'delete'], true)) {
    http_response_code(400);
    exit('Invalid transaction action.');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'delete') {
    verify_csrf();
    if (!$editing) {
        http_response_code(400);
        exit('Invalid transaction.');
    }
    $deletedRows = db_transaction($pdo, function (PDO $pdo) use ($table, $idColumn, $id, $userId, $type): int {
        $statement = $pdo->prepare("DELETE FROM {$table} WHERE {$idColumn} = :id AND user_id = :user_id");
        $statement->execute([':id' => $id, ':user_id' => $userId]);
        $deletedRows = $statement->rowCount();
        if ($deletedRows === 1) {
            log_activity($pdo, $userId, 'Deleted ' . $type);
        }
        return $deletedRows;
    });
    if ($deletedRows !== 1) {
        http_response_code(404);
        exit('Transaction not found.');
    }
    flash(ucfirst($type) . ' deleted.');
    redirect('index.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $amount = valid_amount($_POST['amount'] ?? null);
    $date = trim((string) ($_POST['date'] ?? ''));
    $description = trim((string) ($_POST['description'] ?? ''));
    $record['amount'] = (string) ($_POST['amount'] ?? '');
    $record['date'] = $date;
    $record['kind'] = (string) ($_POST['allowance_type'] ?? '');
    $record['category_id'] = (string) ($_POST['category_id'] ?? '');
    $record['description'] = $description;

    if ($amount === null || !valid_date($date)) {
        $error = 'Enter a valid positive amount and date.';
    } elseif ($isExpense && ($description === '' || strlen($description) > 255)) {
        $error = 'Enter an expense description (up to 255 characters).';
    } elseif (!$isExpense && !in_array($record['kind'], ['Daily', 'Weekly', 'Monthly'], true)) {
        $error = 'Choose a valid allowance frequency.';
    } elseif ($isExpense && !filter_var($record['category_id'], FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]])) {
        $error = 'Choose an expense category.';
    } elseif (!$isExpense && strlen($description) > 255) {
        $error = 'Notes must be 255 characters or fewer.';
    } else {
        if ($isExpense) {
            $categoryCheck = $pdo->prepare('SELECT 1 FROM categories WHERE category_id = :category_id');
            $categoryCheck->execute([':category_id' => (int) $record['category_id']]);
            if (!$categoryCheck->fetchColumn()) {
                $error = 'The selected expense category is unavailable.';
            }
        }
        if ($error === '') {
            if ($isExpense) {
                if ($editing) {
                    $sql = 'UPDATE expenses SET category_id = :category_id, amount = :amount, expense_date = :date, description = :description WHERE expense_id = :id AND user_id = :user_id';
                } else {
                    $sql = 'INSERT INTO expenses (user_id, category_id, amount, expense_date, description) VALUES (:user_id, :category_id, :amount, :date, :description)';
                }
                $params = [
                    ':user_id' => $userId,
                    ':category_id' => (int) $record['category_id'],
                    ':amount' => $amount,
                    ':date' => $date,
                    ':description' => $description,
                ];
            } else {
                if ($editing) {
                    $sql = 'UPDATE allowances SET amount = :amount, allowance_date = :date, allowance_type = :kind, notes = :notes WHERE allowance_id = :id AND user_id = :user_id';
                } else {
                    $sql = 'INSERT INTO allowances (user_id, amount, allowance_date, allowance_type, notes) VALUES (:user_id, :amount, :date, :kind, :notes)';
                }
                $params = [
                    ':user_id' => $userId,
                    ':amount' => $amount,
                    ':date' => $date,
                    ':kind' => $record['kind'],
                    ':notes' => $description !== '' ? $description : null,
                ];
            }
            if ($editing) {
                $params[':id'] = $id;
            }
            $affectedRows = db_transaction($pdo, function (PDO $pdo) use ($sql, $params, $userId, $editing, $type): int {
                $statement = $pdo->prepare($sql);
                $statement->execute($params);
                log_activity($pdo, $userId, ($editing ? 'Updated ' : 'Added ') . $type);
                return $statement->rowCount();
            });
            flash(ucfirst($type) . ($editing ? ' updated.' : ' recorded.') . ($affectedRows === 0 && $editing ? ' No values changed.' : ''));
            redirect('index.php');
        }
    }
}

$pageTitle = ($editing ? 'Edit ' : 'Add ') . ucfirst($type);
require __DIR__ . '/includes/header.php';
?>
<section class="panel form-panel">
    <p class="eyebrow"><?= $editing ? 'Update record' : 'New record' ?></p>
    <h1><?= e($pageTitle) ?></h1>
    <?php if ($error !== ''): ?><div class="notice error" role="alert"><?= e($error) ?></div><?php endif; ?>
    <form method="post" class="stack">
        <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
        <input type="hidden" name="type" value="<?= e($type) ?>">
        <?php if ($editing): ?><input type="hidden" name="id" value="<?= (int) $id ?>"><?php endif; ?>
        <label>Amount (₱)<input type="number" name="amount" min="0.01" max="99999999.99" step="0.01" value="<?= e($record['amount']) ?>" required></label>
        <label>Date<input type="date" name="date" value="<?= e($record['date']) ?>" required></label>
        <?php if ($isExpense): ?>
            <label>Category
                <select name="category_id" required>
                    <option value="">Select category</option>
                    <?php foreach ($categories as $category): ?>
                        <option value="<?= (int) $category['category_id'] ?>" <?= (string) $category['category_id'] === (string) $record['category_id'] ? 'selected' : '' ?>><?= e($category['category_name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label>Description<input name="description" value="<?= e($record['description']) ?>" maxlength="255" required placeholder="What was this expense for?"></label>
        <?php else: ?>
            <label>Allowance frequency
                <select name="allowance_type" required>
                    <?php foreach (['Daily', 'Weekly', 'Monthly'] as $frequency): ?>
                        <option value="<?= e($frequency) ?>" <?= $frequency === $record['kind'] ? 'selected' : '' ?>><?= e($frequency) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label>Notes <span class="optional">(optional)</span><input name="description" value="<?= e($record['description']) ?>" maxlength="255" placeholder="e.g. Weekly allowance from parents"></label>
        <?php endif; ?>
        <div class="button-row">
            <button class="button button-primary" type="submit"><?= $editing ? 'Save changes' : 'Record ' . e($type) ?></button>
            <a class="button button-quiet" href="index.php">Cancel</a>
        </div>
    </form>
</section>
<?php require __DIR__ . '/includes/footer.php'; ?>
