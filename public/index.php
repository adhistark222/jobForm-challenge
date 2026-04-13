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

try {
	$formController = new FormController();
	$formController->showForm();
} catch (PDOException $e) {
	error_log('DB connection failed: ' . $e->getMessage());
	http_response_code(503);
	echo '<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8"><title>Unavailable</title></head>'
		. '<body><p>Service temporarily unavailable. Please try again shortly.</p></body></html>';
}

