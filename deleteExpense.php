<?php
require_once __DIR__ . '/includes/app.php';
require_login();

flash('For your safety, delete records using the confirmation button in the transaction list.', 'error');
redirect('index.php');
