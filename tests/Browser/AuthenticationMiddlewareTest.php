<?php

declare(strict_types=1);

use Illuminate\Foundation\Auth\User;
use WeArePixel\Inlay\Enums\PageStatus;
use WeArePixel\Inlay\Models\Page;
use WeArePixel\Inlay\Tests\Fixtures\BrowserHeroSection;

/**
 * Confirms config('inlay.admin.middleware') actually protects the admin's
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
    config(['inlay.blocks' => ['hero' => BrowserHeroSection::class]]);
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

    $page = Page::create(['title' => 'Home', 'path' => 'home']);
    $page->sections()->create(['handle' => 'hero', 'order' => 0, 'settings' => []]);

    visit('/inlay-secure/pages')->assertSee('Pages')->assertSee('New page');

    visit("/inlay-secure/pages/{$page->id}/edit")
        ->click('@publish')
        ->wait(1)
        ->assertSee('Published');

    expect($page->fresh()->status)->toBe(PageStatus::Published);
});

/**
 * A real bug (found manually, against a genuine standalone Laravel app, not
 * this suite) sent a logged-in editor back to the login screen the moment
 * they performed their first Livewire action (adding a block,
 * publishing, etc) - the initial page load worked fine, every action after
 * it did not. Root cause: Livewire's persistent middleware re-runs the
 * *original* route's middleware against a *cloned* request on every action -
 * so when config('inlay.admin.middleware') resolved the "web" group into that
 * persistent set, EncryptCookies ran a second time against a cookie its
 * first pass had already decrypted, failed, and silently dropped it,
 * leaving the clone with no session at all. See InlayManager::adminRoutes() for
 * the fix (only middleware beyond "web" persists).
 *
 * This test performs a genuine login through TestCase's own '/test-login'
 * form (unlike the test above, which uses actingAs() and so never
 * round-trips a real, encrypted cookie) specifically to exercise that
 * decrypt-twice path. It is exactly the right shape to catch this bug, but
 * in practice does not: instrumenting Livewire's PersistentMiddleware
 * directly confirmed the session cookie genuinely is corrupted and nulled
 * on the clone here too, yet Auth::check() still reports authenticated
 * afterwards, because this plugin's in-process AMPHP server keeps one
 * booted Application - and with it the auth guard's already-resolved user -
 * alive across what a real server would treat as separate requests. So this
 * assertion passes identically whether InlayManager::adminRoutes() is fixed or
 * not; it is kept as real coverage of the login-then-act happy path, not as
 * a regression guard for this specific bug. The fix itself was verified
 * against a real `php artisan serve` process in a standalone sandbox app,
 * where each request really is independent.
 */
it('keeps a real, cookie-based session logged in across a Livewire action', function () {
    $user = User::forceCreate([
        'name' => 'Editor',
        'email' => 'editor@example.test',
        'password' => bcrypt('password'),
    ]);

    $page = Page::create(['title' => 'Home', 'path' => 'home']);
    $page->sections()->create(['handle' => 'hero', 'order' => 0, 'settings' => []]);

    visit('/test-login')
        ->fill('@email', $user->email)
        ->fill('@password', 'password')
        ->click('@login-submit')
        ->wait(1)
        ->assertSee('Pages');

    // A fresh visit() call, not chained off the login page - the same
    // pattern the rest of this suite uses for sequential navigation. The
    // browser context (and its cookies) persists across separate visit()
    // calls within one test.
    visit("/inlay-secure/pages/{$page->id}/edit")
        ->click('@publish')
        ->wait(1)
        ->assertSee('Published')
        ->assertPathIs("/inlay-secure/pages/{$page->id}/edit");

    expect($page->fresh()->status)->toBe(PageStatus::Published);
});
