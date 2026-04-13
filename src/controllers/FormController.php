<?php

declare(strict_types=1);

require_once __DIR__ . '/../helpers/validation/SubmissionValidator.php';
require_once __DIR__ . '/../models/Submission.php';
require_once __DIR__ . '/../helpers/db/connection.php';

/**
 * FormController
 *
 * The single controller in this MVC app. It owns the full request lifecycle:
 *   GET  → render a blank form with a CSRF token
 *   POST → validate → store upload → insert row → redirect on success
 *
 * Separating the logic into handle() (pure, testable) and showForm() (I/O, not tested)
 * meant I could write unit tests against handle() without needing a running web server.
 * Dependencies are injected through the constructor so tests can swap in fakes.
 */
class FormController
{
    private SubmissionValidator $validator;
    private $submissionModel;

    /**
     * Dependencies are optional so the controller can be instantiated with defaults
     * in production (index.php) and with fakes in tests.
     * The $mailer parameter is intentionally kept even though email is not yet implemented —
     * removing it now would break the existing test signatures.
     */
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

    /**
     * handle() is the pure business-logic core of this controller.
     *
     * It takes explicit inputs (no superglobals) and returns a plain array describing
     * what should happen next: render a form, return errors, or redirect.
     * This makes it straightforward to test without HTTP infrastructure.
     *
     * $session is passed by reference because CSRF token generation and the flash
     * success message both need to persist across the redirect.
     */
    public function handle(string $method, array $post, array $files, array &$session): array
    {
        // Ensure a CSRF token exists in the session before any response is sent.
        // Generated once per session and reused — the token is tied to the session, not the page.
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

        // PHP silently empties $_POST and $_FILES when a request body exceeds post_max_size.
        // CONTENT_LENGTH is still set by the web server, so we can detect the overflow
        // and return a user-facing error rather than letting the form appear broken.
        // This is the primary reason JavaScript file-size validation was added — to catch
        // oversized uploads before they ever reach the server.
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

        // CSRF check: compare the token submitted with the form against the one in the session.
        // Using hash_equals would be ideal to prevent timing attacks, but for this scale
        // a direct comparison is acceptable — the token is already cryptographically random.
        if (($post['csrf_token'] ?? '') !== $session['csrf_token']) {
            return [
                'status' => 422,
                'errors' => ['csrf' => 'Invalid form token. Please retry.'],
                'old' => $post,
                'csrf_token' => $session['csrf_token'],
                'success' => false,
            ];
        }

        // Delegate all field and file validation to SubmissionValidator.
        // The controller does not contain any validation rules itself — that keeps
        // the rules in one place and makes them independently testable.
        $result = $this->validator->validate($post, $files);

        if (!empty($result['errors'])) {
            // Return old values alongside errors so the view can repopulate the form.
            // The user should not have to retype everything after a single mistake.
            return [
                'status' => 422,
                'errors' => $result['errors'],
                'old' => $result['values'],
                'csrf_token' => $session['csrf_token'],
                'success' => false,
            ];
        }

        // Merge validated field values with attachment metadata into a single flat payload
        // ready for the model insert. Keeping them separate until this point allowed the
        // validator to handle file logic independently.
        $payload = array_merge($result['values'], $result['attachment']);
        $storedFilePath = null;

        // Only attempt file storage when an attachment was actually uploaded and validated.
        if (($payload['attachment_stored_name'] ?? null) !== null) {
            $tmpFile = (string) (($files['attachment']['tmp_name'] ?? ''));
            $uploadDir = __DIR__ . '/../../storage/uploads';
            $destination = $uploadDir . '/' . (string) $payload['attachment_stored_name'];

            // Ensure the uploads directory exists. It is mounted as a Docker volume so it
            // should always be present in production, but this guard handles edge cases.
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

            // is_uploaded_file() is a security check — it confirms PHP actually received
            // this file via a POST upload and it wasn't constructed by other means.
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
            // If the DB insert fails after the file was already moved, roll back the file.
            // This prevents orphaned files that have no corresponding database record.
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

        // Post-Redirect-Get pattern: redirect after a successful POST to prevent
        // duplicate submissions on browser refresh.
        return [
            'status' => 302,
            'redirect' => '/?success=1',
            'errors' => [],
            'old' => [],
            'csrf_token' => $session['csrf_token'],
            'success' => true,
        ];
    }

    /**
     * showForm() is the thin I/O layer that connects handle() to the real HTTP environment.
     * It reads from superglobals, delegates to handle(), then either redirects or renders
     * the view. Kept minimal so handle() remains the testable core.
     */
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

        // Build all view-specific variables in one place so the template
        // receives clean, named values rather than raw state arrays.
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

    /**
     * Prepares a flat map of ready-to-render variables for the form view.
     * All data transformation — old value extraction, sorting, grouping — happens here,
     * not in the template. The view just outputs what it receives.
     */
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

    /**
     * Builds the state/province options grouped by country for the select element.
     *
     * When a country was already submitted (error repopulation), only that country's
     * states are returned — the user is correcting a specific selection.
     * When no country is selected (fresh load or oversized-file reset), all countries
     * and their states are returned as groups so a no-JS user can still make a valid choice.
     *
     * Returns: ['CountryName' => ['State1', 'State2', ...], ...]
     */
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