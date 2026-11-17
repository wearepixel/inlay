<?php

declare(strict_types=1);

use Illuminate\Foundation\Auth\User;
use Illuminate\Support\Facades\Gate;
use Livewire\Livewire;
use WeArePixel\Inlay\Enums\PageStatus;
use WeArePixel\Inlay\Facades\Inlay;
use WeArePixel\Inlay\Livewire\PageCreate;
use WeArePixel\Inlay\Livewire\PageEditor;
use WeArePixel\Inlay\Livewire\PageIndex;
use WeArePixel\Inlay\Livewire\SiteSettingsEditor;
use WeArePixel\Inlay\Models\Page;
use WeArePixel\Inlay\Models\SiteSetting;
use WeArePixel\Inlay\Schema\Settings;

beforeEach(function () {
    config()->set('inlay.admin.ability', 'inlay.manage-pages');

    $this->allowed = true;
    Gate::define('inlay.manage-pages', fn (?User $user) => $this->allowed);

    $this->actingAs(User::forceCreate(['name' => 'Editor', 'email' => 'editor@example.com', 'password' => 'x']));
});

it('refuses to mount any admin component when the ability is denied', function (string $component, array $params) {
    $this->allowed = false;

    Livewire::test($component, $params)->assertForbidden();
})->with([
    'index' => [PageIndex::class, []],
    'create' => [PageCreate::class, []],
    'site settings' => [SiteSettingsEditor::class, []],
]);

it('refuses to mount the editor when the ability is denied', function () {
    $this->allowed = false;
    $page = Page::create(['title' => 'Home', 'path' => 'home']);

    Livewire::test(PageEditor::class, ['page' => $page])->assertForbidden();
});

it('does not delete a page once the ability is revoked mid-session', function () {
    $page = Page::create(['title' => 'Home', 'path' => 'home']);
    $component = Livewire::test(PageIndex::class);

    $this->allowed = false;
    $component->call('delete', $page->id);

    expect(Page::find($page->id))->not->toBeNull();
});

it('does not publish once the ability is revoked mid-session', function () {
    $page = Page::create(['title' => 'Home', 'path' => 'home']);
    $component = Livewire::test(PageEditor::class, ['page' => $page]);

    $this->allowed = false;
    $component->call('publish');

    expect($page->fresh()->status)->toBe(PageStatus::Draft);
});

it('does not save a site setting once the ability is revoked mid-session', function () {
    Inlay::siteSettings('contact', Settings::make()->text('phone'));
    $component = Livewire::test(SiteSettingsEditor::class);

    $this->allowed = false;
    $component->set('editingProps.phone', '0400 000 000');

    expect(SiteSetting::where('handle', 'contact')->exists())->toBeFalse();
});

it('passes the page to the ability, so a gate can scope access per page', function () {
    $home = Page::create(['title' => 'Home', 'path' => 'home']);
    $about = Page::create(['title' => 'About', 'path' => 'about']);
    Gate::define('inlay.manage-pages', fn (?User $user, ?Page $page = null) => $page?->is($home) ?? true);

    Livewire::test(PageEditor::class, ['page' => $home])->assertOk();
    Livewire::test(PageEditor::class, ['page' => $about])->assertForbidden();
});

it('forbids the preview route when the ability is denied', function () {
    $page = Page::create(['title' => 'Home', 'path' => 'home']);
    $this->allowed = false;

    $this->get(route('inlay.admin.pages.preview', $page))->assertForbidden();
});

it('leaves access to the route middleware when no ability is configured', function () {
    config()->set('inlay.admin.ability', null);
    $this->allowed = false;

    Livewire::test(PageIndex::class)->assertOk();
});
