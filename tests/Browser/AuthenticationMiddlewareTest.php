<?php

declare(strict_types=1);

use Illuminate\Foundation\Auth\User;
use WearePixel\Inlay\Enums\PageStatus;
use WearePixel\Inlay\Models\Page;
use WearePixel\Inlay\Tests\Fixtures\BrowserHeroSection;

/**
 * Confirms config('inlay.middleware') actually protects the admin's
 * initial GET requests, and that a legitimately authenticated session can
 * still complete a full Livewire action (add/select/publish) end to end -
 * i.e. an 'auth'-guarded admin doesn't wrongly block a real, authenticated
 * editor. Exercised against the fixed /inlay-secure route group registered
 * in tests/TestCase.php (see that file for why a separate, permanently
 * 'auth'-guarded group is used instead of toggling config per test).
 *
 * The specific mechanical property this whole feature exists for - that
 * Livewire's own /livewire/update endpoint inherits the SAME configured
 * middleware rather than only the initial page load - is proven directly
 * in tests/Feature/InlayPersistentMiddlewareTest.php. It is not
 * re-simulated here: this plugin's Laravel integration authenticates a
 * test via the shared in-process Auth guard (the same mechanism a normal
 * actingAs() feature test uses), not a real session cookie, so there is no
 * way to represent "a real browser whose session cookie has since expired"
 * without standing up a genuine login form purely to defeat this plugin's
 * test-only auth shortcut - out of scope for proving a fact the
 * Feature-level test already proves precisely.
 */
beforeEach(function () {
    config(['inlay.sections' => ['hero' => BrowserHeroSection::class]]);
});

it('redirects an unauthenticated visitor away from the initial admin request', function () {
    visit('/inlay-secure/pages')
        ->assertDontSee('New page')
        ->assertPathIs('/login');
});

it('allows an authenticated visitor to load the admin and complete a real Livewire action', function () {
    $user = User::forceCreate([
        'name' => 'Editor',
        'email' => 'editor@example.test',
        'password' => bcrypt('password'),
    ]);

    $this->actingAs($user);

    $page = Page::create(['title' => 'Home', 'slug' => 'home']);
    $page->sections()->create(['handle' => 'hero', 'order' => 0, 'settings' => []]);

    visit('/inlay-secure/pages')->assertSee('Pages')->assertSee('New page');

    visit("/inlay-secure/pages/{$page->id}/edit")
        ->click('@publish')
        ->wait(1)
        ->assertSee('Published');

    expect($page->fresh()->status)->toBe(PageStatus::Published);
});
