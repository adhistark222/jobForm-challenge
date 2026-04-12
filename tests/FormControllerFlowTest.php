<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/controllers/FormController.php';

class FakeValidator
{
    public array $nextErrors = [];

    public function validate(array $input, array $files): array
    {
        return [
            'errors' => $this->nextErrors,
            'values' => $input,
            'attachment' => [
                'attachment_original_name' => null,
                'attachment_stored_name' => null,
                'attachment_extension' => null,
                'attachment_mime' => null,
                'attachment_size' => null,
            ],
        ];
    }
}

class FakeSubmissionModel
{
    public int $insertCallCount = 0;

    public function insert(array $payload): int
    {
        $this->insertCallCount++;
        return 1;
    }
}

class FakeMailer
{
    public int $sendCallCount = 0;

    public function sendSubmissionSummary(array $payload): bool
    {
        $this->sendCallCount++;
        return true;
    }
}

test('controller returns form state for GET requests', function (): void {
    $validator = new FakeValidator();
    $model = new FakeSubmissionModel();
    $mailer = new FakeMailer();

    $controller = new FormController($validator, $model, $mailer);
    $session = [];

    $result = $controller->handle('GET', [], [], $session);

    assert_same(200, $result['status']);
    assert_same([], $result['errors']);
    assert_same([], $result['old']);
    assert_has_key('csrf_token', $session);
});

test('controller returns errors and old values for invalid POST', function (): void {
    $validator = new FakeValidator();
    $validator->nextErrors = ['job_title' => 'Job title is required.'];

    $model = new FakeSubmissionModel();
    $mailer = new FakeMailer();

    $controller = new FormController($validator, $model, $mailer);
    $session = ['csrf_token' => 'known-token'];

    $postData = [
        'csrf_token' => 'known-token',
        'job_title' => '',
        'country' => 'Canada',
        'state_province' => 'Ontario',
        'budget' => '100_249',
    ];

    $result = $controller->handle('POST', $postData, [], $session);

    assert_same(422, $result['status']);
    assert_has_key('job_title', $result['errors']);
    assert_has_key('job_title', $result['old']);
    assert_same(0, $model->insertCallCount);
    assert_same(0, $mailer->sendCallCount);
});

test('controller persists and triggers email for valid POST', function (): void {
    $validator = new FakeValidator();
    $model = new FakeSubmissionModel();
    $mailer = new FakeMailer();

    $controller = new FormController($validator, $model, $mailer);
    $session = ['csrf_token' => 'valid-token'];

    $postData = [
        'csrf_token' => 'valid-token',
        'job_title' => 'Narration',
        'job_small_script' => 'sample',
        'country' => 'Canada',
        'state_province' => 'Ontario',
        'budget' => '100_249',
    ];

    $result = $controller->handle('POST', $postData, [], $session);

    assert_same(302, $result['status']);
    assert_same('/?success=1', $result['redirect']);
    assert_same(1, $model->insertCallCount);
    assert_same(1, $mailer->sendCallCount);
});
