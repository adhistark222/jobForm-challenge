<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/helpers/db/connection.php';
require_once __DIR__ . '/../src/models/Submission.php';

test('database connection and MySQL insert work with live schema', function (): void {
    $host = getenv('DB_HOST') ?: '';
    $name = getenv('DB_NAME') ?: '';
    $user = getenv('DB_USER') ?: '';

    // Allow local non-docker runs to continue without false failures.
    if ($host === '' || $name === '' || $user === '') {
        return;
    }

    $pdo = DatabaseConnection::createPdoFromEnvironment();
    $probe = $pdo->query('SELECT 1 AS ok')->fetch(PDO::FETCH_ASSOC);
    assert_same('1', (string) ($probe['ok'] ?? ''), 'DB connection probe failed.');

    $model = new Submission($pdo);
    $insertId = $model->insert([
        'job_title' => 'Integration Test Submission',
        'job_small_script' => 'Integration check',
        'country' => 'Canada',
        'state_province' => 'Ontario',
        'budget' => '100_249',
        'attachment_original_name' => null,
        'attachment_stored_name' => null,
        'attachment_extension' => null,
        'attachment_mime' => null,
        'attachment_size' => null,
    ]);

    assert_true($insertId > 0, 'Expected insert id from MySQL to be positive.');

    $stmt = $pdo->prepare('SELECT country, state_province FROM submissions WHERE id = :id');
    $stmt->execute([':id' => $insertId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

    assert_same('Canada', (string) ($row['country'] ?? ''), 'Country persistence mismatch.');
    assert_same('Ontario', (string) ($row['state_province'] ?? ''), 'State/province persistence mismatch.');

    $cleanup = $pdo->prepare('DELETE FROM submissions WHERE id = :id');
    $cleanup->execute([':id' => $insertId]);
});
