<?php

declare(strict_types=1);

$__tests = [];

function test(string $name, callable $fn): void
{
    global $__tests;
    $__tests[] = ['name' => $name, 'fn' => $fn];
}

function assert_true(bool $condition, string $message = 'Expected condition to be true'): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

function assert_false(bool $condition, string $message = 'Expected condition to be false'): void
{
    if ($condition) {
        throw new RuntimeException($message);
    }
}

function assert_same($expected, $actual, string $message = ''): void
{
    if ($expected !== $actual) {
        $prefix = $message === '' ? '' : $message . ' ';
        throw new RuntimeException($prefix . 'Expected ' . var_export($expected, true) . ' but got ' . var_export($actual, true));
    }
}

function assert_has_key(string $key, array $array, string $message = ''): void
{
    if (!array_key_exists($key, $array)) {
        $prefix = $message === '' ? '' : $message . ' ';
        throw new RuntimeException($prefix . 'Missing array key: ' . $key);
    }
}
