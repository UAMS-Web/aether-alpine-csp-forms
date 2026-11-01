<?php

declare(strict_types=1);

use Statamic\Facades\Addon;
use UAMSWeb\Aether\Forms\AlpineCsp\ServiceProvider;

/**
 * The add-on boots inside a minimal Statamic.
 *
 * @command  vendor/bin/pest tests/Integration/ServiceProviderTest.php
 */
it('is discovered by Statamic as an add-on', function (): void {
    // Expect the add-on among those Statamic booted
    expect(Addon::all()->map->package()->all())->toContain('uams-web/aether-alpine-csp-forms');
});

it('publishes its config file under the tag the README names', function (): void {
    // Read the paths published under the config tag
    $paths = ServiceProvider::pathsToPublish(null, 'aether-alpine-csp-forms-config');

    // Expect the package config to publish to the app's config directory
    expect(array_map(realpath(...), array_keys($paths)))->toBe([realpath(__DIR__.'/../../config/aether-alpine-csp-forms.php')])
        ->and(array_values($paths))->toBe([config_path('aether-alpine-csp-forms.php')]);
});

it('publishes the built front-end script for the host to load', function (): void {
    // Read the paths published under the add-on's tag
    $paths = ServiceProvider::pathsToPublish(null, 'aether-alpine-csp-forms');

    // Expect the built script directory to publish under the package's public directory
    expect(array_map(realpath(...), array_keys($paths)))->toContain(realpath(__DIR__.'/../../resources/dist/frontend'))
        ->and(array_values($paths))->toContain(public_path('vendor/aether-alpine-csp-forms/frontend'))
        ->and(__DIR__.'/../../resources/dist/frontend/alpine-csp.js')->toBeFile();
});
