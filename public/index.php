<?php
ini_set('display_errors', '0');
ini_set('display_startup_errors', '0');
ini_set('log_errors', '1');
ini_set('error_log', 'php://stderr');

require_once '../src/controllers/FormController.php';

if (isset($_GET['success']) && $_GET['success'] === '1') {
	require_once '../src/views/success.php';
	exit;
}

$formController = new FormController();
$formController->showForm();
?>

