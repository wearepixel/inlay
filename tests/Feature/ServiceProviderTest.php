<?php

declare(strict_types=1);

it('merges the package config', function () {
    expect(config('inlay.disk'))->toBe('public')
        ->and(config('inlay.blocks'))->toBe([]);
});
