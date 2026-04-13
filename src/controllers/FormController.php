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

        $contentLength = (int) ($_SERVER['CONTENT_LENGTH'] ?? 0);
        if ($contentLength > 0 && empty($post) && empty($files)) {
            return [
                'status' => 422,
                'errors' => ['attachment' => 'Your file exceeds the 20MB limit. Please choose a smaller file and fill in the form again.'],
                'old' => [],
                'csrf_token' => $session['csrf_token'],
                'success' => false,
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

        $viewData = $this->buildViewData($state);
        $errors             = $viewData['errors'];
        $old                = $viewData['old'];
        $csrfToken          = $viewData['csrfToken'];
        $regions            = $viewData['regions'];
        $jobTitleValue      = $viewData['jobTitleValue'];
        $scriptValue        = $viewData['scriptValue'];
        $selectedBudget     = $viewData['selectedBudget'];
        $selectedCountry    = $viewData['selectedCountry'];
        $selectedStateProvince = $viewData['selectedStateProvince'];
        $countryNames       = $viewData['countryNames'];
        $stateOptions       = $viewData['stateOptions'];

        require __DIR__ . '/../views/form.php';
    }

    private function buildViewData(array $state): array
    {
        $errors = is_array($state['errors'] ?? null) ? $state['errors'] : [];
        $old = is_array($state['old'] ?? null) ? $state['old'] : [];
        $regions = $this->validator->getRegions();

        $selectedCountry = trim((string) ($old['country'] ?? ''));
        $selectedStateProvince = trim((string) ($old['state_province'] ?? ''));

        $countryNames = array_keys($regions);
        sort($countryNames);

        return [
            'errors' => $errors,
            'old' => $old,
            'csrfToken' => (string) ($state['csrf_token'] ?? ''),
            'regions' => $regions,
            'jobTitleValue' => (string) ($old['job_title'] ?? ''),
            'scriptValue' => (string) ($old['job_small_script'] ?? ''),
            'selectedBudget' => trim((string) ($old['budget'] ?? '')),
            'selectedCountry' => $selectedCountry,
            'selectedStateProvince' => $selectedStateProvince,
            'countryNames' => $countryNames,
            'stateOptions' => $this->buildStateOptions($regions, $countryNames, $selectedCountry),
        ];
    }

    private function buildStateOptions(array $regions, array $countryNames, string $selectedCountry): array
    {
        if ($selectedCountry !== '' && isset($regions[$selectedCountry]) && is_array($regions[$selectedCountry])) {
            return [$selectedCountry => $regions[$selectedCountry]];
        }

        $grouped = [];
        foreach ($countryNames as $countryName) {
            $options = $regions[$countryName] ?? [];
            if (!is_array($options) || count($options) === 0) {
                continue;
            }
            $grouped[$countryName] = $options;
        }

        return $grouped;
    }
}