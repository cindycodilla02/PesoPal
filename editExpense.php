<?php
$id = filter_var($_GET['id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
if ($id === false || $id === null) {
    http_response_code(400);
    exit('Invalid expense ID.');
}
header('Location: transaction.php?type=expense&id=' . $id);
exit;
