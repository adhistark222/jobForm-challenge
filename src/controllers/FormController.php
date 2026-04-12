<?php

declare(strict_types=1);

require_once __DIR__ . '/../helpers/validation/SubmissionValidator.php';
require_once __DIR__ . '/../models/Submission.php';
require_once __DIR__ . '/../helpers/db/connection.php';

class FormController
{
    private SubmissionValidator $validator;
    private $submissionModel;

    public function __construct($validator = null, $submissionModel = null, $mailer = null)
    {
        $this->validator = $validator instanceof SubmissionValidator
            ? $validator
            : new SubmissionValidator(__DIR__ . '/../../data/regions.json');

        if ($submissionModel !== null) {
            $this->submissionModel = $submissionModel;
        } else {
            $pdo = DatabaseConnection::createPdoFromEnvironment();
            $this->submissionModel = new Submission($pdo);
        }

        // Placeholder only: email integration is intentionally deferred.
        // Constructor keeps third argument for backward compatibility with existing tests.
    }

    public function handle(string $method, array $post, array $files, array &$session): array
    {
        if (!isset($session['csrf_token']) || !is_string($session['csrf_token'])) {
            $session['csrf_token'] = bin2hex(random_bytes(32));
        }

        if (strtoupper($method) === 'GET') {
            return [
                'status' => 200,
                'errors' => [],
                'old' => [],
                'csrf_token' => $session['csrf_token'],
                'success' => isset($post['success']) && $post['success'] === '1',
            ];
        }

        if (($post['csrf_token'] ?? '') !== $session['csrf_token']) {
            return [
                'status' => 422,
                'errors' => ['csrf' => 'Invalid form token. Please retry.'],
                'old' => $post,
                'csrf_token' => $session['csrf_token'],
                'success' => false,
            ];
        }

        $result = $this->validator->validate($post, $files);

        if (!empty($result['errors'])) {
            return [
                'status' => 422,
                'errors' => $result['errors'],
                'old' => $result['values'],
                'csrf_token' => $session['csrf_token'],
                'success' => false,
            ];
        }

        $payload = array_merge($result['values'], $result['attachment']);
        $storedFilePath = null;

        if (($payload['attachment_stored_name'] ?? null) !== null) {
            $tmpFile = (string) (($files['attachment']['tmp_name'] ?? ''));
            $uploadDir = __DIR__ . '/../../storage/uploads';
            $destination = $uploadDir . '/' . (string) $payload['attachment_stored_name'];

            if (!is_dir($uploadDir) && !mkdir($uploadDir, 0775, true) && !is_dir($uploadDir)) {
                error_log('Upload store failed: could not create directory ' . $uploadDir);
                return [
                    'status' => 422,
                    'errors' => ['attachment' => 'Failed to store uploaded file. Please try again.'],
                    'old' => $result['values'],
                    'csrf_token' => $session['csrf_token'],
                    'success' => false,
                ];
            }

            $canStoreUpload = is_uploaded_file($tmpFile)
                && is_dir($uploadDir)
                && is_writable($uploadDir)
                && move_uploaded_file($tmpFile, $destination);

            if (!$canStoreUpload) {
                error_log('Upload store failed: tmp=' . $tmpFile . ', dir=' . $uploadDir . ', writable=' . (is_writable($uploadDir) ? 'yes' : 'no'));
                return [
                    'status' => 422,
                    'errors' => ['attachment' => 'Failed to store uploaded file. Please try again.'],
                    'old' => $result['values'],
                    'csrf_token' => $session['csrf_token'],
                    'success' => false,
                ];
            }

            $storedFilePath = $destination;
        }

        try {
            $this->submissionModel->insert($payload);
        } catch (Throwable $exception) {
            if ($storedFilePath !== null && is_file($storedFilePath)) {
                if (!unlink($storedFilePath)) {
                    error_log('Failed to remove orphaned upload: ' . $storedFilePath);
                }
            }

            error_log('Submission insert failed: ' . $exception->getMessage());

            return [
                'status' => 422,
                'errors' => ['submission' => 'Failed to save your submission. Please try again.'],
                'old' => $result['values'],
                'csrf_token' => $session['csrf_token'],
                'success' => false,
            ];
        }

        // Placeholder only:
        // TODO: Trigger confirmation/summary email to jobform@voices.com after successful insert.

        $session['flash_success'] = 'Submission received successfully.';

        return [
            'status' => 302,
            'redirect' => '/?success=1',
            'errors' => [],
            'old' => [],
            'csrf_token' => $session['csrf_token'],
            'success' => true,
        ];
    }

    public function showForm(): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }

        $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        $state = $this->handle($method, $_POST, $_FILES, $_SESSION);

        if (($state['status'] ?? 200) === 302 && isset($state['redirect'])) {
            header('Location: ' . $state['redirect']);
            exit;
        }

        $errors = $state['errors'] ?? [];
        $old = $state['old'] ?? [];
        $csrfToken = $state['csrf_token'] ?? '';
        $regions = $this->validator->getRegions();

        require __DIR__ . '/../views/form.php';
    }
}