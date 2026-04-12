<?php

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

$testFiles = [
    __DIR__ . '/SubmissionValidatorTest.php',
    __DIR__ . '/SubmissionModelTest.php',
    __DIR__ . '/DatabaseConnectionIntegrationTest.php',
    __DIR__ . '/FormControllerFlowTest.php',
];

foreach ($testFiles as $testFile) {
    require_once $testFile;
}

global $__tests;

$passed = 0;
$failed = 0;

foreach ($__tests as $test) {
    try {
        $test['fn']();
        $passed++;
        echo "[PASS] {$test['name']}" . PHP_EOL;
    } catch (Throwable $exception) {
        $failed++;
        echo "[FAIL] {$test['name']}: {$exception->getMessage()}" . PHP_EOL;
    }
}

echo PHP_EOL . "Total: " . ($passed + $failed) . ", Passed: $passed, Failed: $failed" . PHP_EOL;

exit($failed > 0 ? 1 : 0);
