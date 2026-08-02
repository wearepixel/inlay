<?php

declare(strict_types=1);
use WeArePixel\Inlay\Models\Page;

it('publishes the config and runs migrations', function () {
    $this->artisan('inlay:install')
        ->expectsOutputToContain('Installing Inlay')
        ->assertSuccessful();

    expect(Page::query()->count())->toBe(0);
});
