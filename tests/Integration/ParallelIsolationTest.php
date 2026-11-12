<?php

declare(strict_types=1);

use UAMSWeb\Aether\Forms\AlpineCsp\Tests\TestCase;

/**
 * Parallel workers keep their written files apart.
 *
 * @command  vendor/bin/pest tests/Integration/ParallelIsolationTest.php
 */
it('names a storage directory per worker token', function (mixed $token, ?string $expected): void {
    // Expect a directory under the base for a usable token, and none otherwise
    expect(TestCase::workerStoragePath('/base', $token))->toBe($expected);
})->with([
    'string token' => ['3', '/base/worker-3'],
    'integer token' => [7, '/base/worker-7'],
    'no token' => [null, null],
    'array token' => [[1], null],
]);

it('writes real-time facade stubs under the worker storage directory', function (): void {
    // Read this worker's directory name, which only a parallel run has
    $worker = TestCase::workerStoragePath('', $_SERVER['TEST_TOKEN'] ?? null);

    // Read the directory Laravel writes facade stubs to
    $cache = str_replace(DIRECTORY_SEPARATOR, '/', storage_path('framework/cache'));

    // Expect this worker's own directory in a parallel run, and the shared one otherwise
    expect($cache)->when($worker !== null, fn ($e) => $e->toContain("{$worker}/"))
        ->when($worker === null, fn ($e) => $e->not->toContain('/worker-'))
        ->and($cache)->toBeDirectory();
});
