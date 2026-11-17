<?php

declare(strict_types=1);

use Livewire\Livewire;
use WeArePixel\Inlay\Facades\Inlay;
use WeArePixel\Inlay\Livewire\SiteSettingsEditor;
use WeArePixel\Inlay\Models\SiteSetting;
use WeArePixel\Inlay\Schema\Settings;
use WeArePixel\Inlay\SiteSettings\SiteSettingsRegistry;
use WeArePixel\Inlay\Support\ImageValue;
use WeArePixel\Inlay\Support\LinkValue;

beforeEach(function () {
    Inlay::siteSettings('contact', Settings::make()
        ->text('phone')->default('+61 400 000 000')
        ->text('email')
        ->image('logo'), label: 'Contact details');

    Inlay::siteSettings('header', Settings::make()
        ->link('cta')
        ->repeater('links', Settings::make()->text('label')->text('url')));
});

it('reads a setting from its default until an editor changes it', function () {
    expect(Inlay::setting('contact.phone'))->toBe('+61 400 000 000');

    Livewire::test(SiteSettingsEditor::class)
        ->set('editingProps.phone', '07 3000 0000');

    expect(Inlay::setting('contact.phone'))->toBe('07 3000 0000');
});

it('stores only the fields an editor changed', function () {
    Livewire::test(SiteSettingsEditor::class)
        ->set('editingProps.email', 'hello@example.com');

    expect(SiteSetting::where('handle', 'contact')->value('values'))
        ->toBe(['email' => 'hello@example.com']);
});

it('hydrates images and links like block settings', function () {
    Livewire::test(SiteSettingsEditor::class, ['group' => 'header'])
        ->set('editingProps.cta', ['label' => 'Enquire', 'url' => '/enquire', 'openInNewTab' => false]);

    expect(Inlay::setting('header.cta'))->toBeInstanceOf(LinkValue::class)
        ->and(Inlay::setting('header.cta')->url)->toBe('/enquire')
        ->and(Inlay::setting('contact.logo'))->toBeNull();

    SiteSetting::where('handle', 'contact')->first()?->delete();
    app(SiteSettingsRegistry::class)->store('contact', 'logo', 'inlay/logo.png');

    expect(Inlay::setting('contact.logo'))->toBeInstanceOf(ImageValue::class)
        ->and(Inlay::setting('contact.logo')->path)->toBe('inlay/logo.png');
});

it('edits repeater items and reads them with a nested key', function () {
    Livewire::test(SiteSettingsEditor::class, ['group' => 'header'])
        ->call('addRepeaterItem', 'links')
        ->set('editingProps.links.0.label', 'Certified products');

    expect(Inlay::setting('header.links.0.label'))->toBe('Certified products');
});

it('ignores a field the group does not declare', function () {
    Livewire::test(SiteSettingsEditor::class)
        ->set('editingProps.injected', 'nope');

    expect(SiteSetting::where('handle', 'contact')->exists())->toBeFalse();
});

it('switches between groups', function () {
    Livewire::test(SiteSettingsEditor::class)
        ->assertSee('Contact details')
        ->call('selectGroup', 'header')
        ->assertSet('group', 'header');
});

it('returns the default for an unknown group or field', function () {
    expect(Inlay::setting('missing.phone', 'x'))->toBe('x')
        ->and(Inlay::setting('contact', 'x'))->toBe('x');
});

it('404s an unknown group in the URL', function () {
    $this->get(route('inlay.admin.settings', 'missing'))->assertNotFound();
});
