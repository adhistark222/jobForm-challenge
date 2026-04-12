<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/helpers/validation/SubmissionValidator.php';

$regionsPath = __DIR__ . '/../data/regions.json';

$validPayload = [
    'job_title' => 'Corporate explainer',
    'job_small_script' => 'This is a short script sample.',
    'country' => 'Canada',
    'state_province' => 'Ontario',
    'budget' => '100_249',
];

test('validator accepts a valid payload without file', function () use ($regionsPath, $validPayload): void {
    $validator = new SubmissionValidator($regionsPath);
    $result = $validator->validate($validPayload, []);

    assert_same([], $result['errors'], 'Expected no validation errors.');
    assert_same('Corporate explainer', $result['values']['job_title']);
    assert_same('Ontario', $result['values']['state_province']);
});

test('validator rejects missing required fields', function () use ($regionsPath): void {
    $validator = new SubmissionValidator($regionsPath);
    $result = $validator->validate([
        'job_title' => ' ',
        'country' => '',
        'state_province' => '',
        'budget' => '',
    ], []);

    assert_has_key('job_title', $result['errors']);
    assert_has_key('country', $result['errors']);
    assert_has_key('state_province', $result['errors']);
    assert_has_key('budget', $result['errors']);
});

test('validator rejects invalid country and region mismatch', function () use ($regionsPath, $validPayload): void {
    $validator = new SubmissionValidator($regionsPath);
    $payload = $validPayload;
    $payload['country'] = 'USA';
    $payload['state_province'] = 'Ontario';

    $result = $validator->validate($payload, []);

    assert_has_key('state_province', $result['errors']);
});

test('validator rejects unsupported budget value', function () use ($regionsPath, $validPayload): void {
    $validator = new SubmissionValidator($regionsPath);
    $payload = $validPayload;
    $payload['budget'] = '999_1000';

    $result = $validator->validate($payload, []);

    assert_has_key('budget', $result['errors']);
});

test('validator rejects files over 20MB', function () use ($regionsPath, $validPayload): void {
    $validator = new SubmissionValidator($regionsPath);
    $file = [
        'name' => 'voice-script.pdf',
        'type' => 'application/pdf',
        'tmp_name' => __FILE__,
        'error' => UPLOAD_ERR_OK,
        'size' => 25 * 1024 * 1024,
    ];

    $result = $validator->validate($validPayload, ['attachment' => $file]);

    assert_has_key('attachment', $result['errors']);
});
