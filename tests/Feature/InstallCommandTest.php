<?php

declare(strict_types=1);
use WearePixel\Inlay\Models\Page;

it('publishes the config and runs migrations', function () {
    $this->artisan('inlay:install')
        ->expectsOutputToContain('Installing Inlay')
        ->assertSuccessful();

    expect(Page::query()->count())->toBe(0);
});
