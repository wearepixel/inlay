<?php

declare(strict_types=1);

it('merges the package config', function () {
    expect(config('blade-page-builder.disk'))->toBe('public')
        ->and(config('blade-page-builder.sections'))->toBe([]);
});
