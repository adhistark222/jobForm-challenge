<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/models/Submission.php';

test('model inserts a submission row and returns new id', function (): void {
    $pdo = new PDO('sqlite::memory:');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $pdo->exec('CREATE TABLE submissions (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        job_title TEXT NOT NULL,
        job_small_script TEXT NULL,
        country TEXT NOT NULL,
        state_province TEXT NOT NULL,
        budget TEXT NOT NULL,
        attachment_original_name TEXT NULL,
        attachment_stored_name TEXT NULL,
        attachment_extension TEXT NULL,
        attachment_mime TEXT NULL,
        attachment_size INTEGER NULL,
        created_at TEXT NOT NULL
    )');

    $model = new Submission($pdo);

    $newId = $model->insert([
        'job_title' => 'Radio Spot',
        'job_small_script' => 'Line one line two',
        'country' => 'CA',
        'state_province' => 'ON',
        'budget' => '100_249',
        'attachment_original_name' => null,
        'attachment_stored_name' => null,
        'attachment_extension' => null,
        'attachment_mime' => null,
        'attachment_size' => null,
    ]);

    assert_true($newId > 0, 'Expected insert id to be positive.');

    $query = $pdo->query('SELECT job_title, country, state_province, budget FROM submissions WHERE id = ' . (int) $newId);
    $row = $query->fetch(PDO::FETCH_ASSOC);

    assert_same('Radio Spot', $row['job_title']);
    assert_same('CA', $row['country']);
    assert_same('ON', $row['state_province']);
    assert_same('100_249', $row['budget']);
});
