<?php

declare(strict_types=1);

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use WeArePixel\Inlay\Facades\Inlay;
use WeArePixel\Inlay\Livewire\PageEditor;
use WeArePixel\Inlay\Livewire\SiteSettingsEditor;
use WeArePixel\Inlay\Models\Page;
use WeArePixel\Inlay\Schema\Settings;
use WeArePixel\Inlay\Tests\Fixtures\TestCardsSection;

beforeEach(fn () => Storage::fake('public'));

it('stores an image uploaded into a repeater item on that item', function () {
    config(['inlay.sections' => ['test-cards' => TestCardsSection::class]]);
    $page = Page::create(['title' => 'Home', 'path' => 'home']);
    $section = $page->sections()->create([
        'handle' => 'test-cards',
        'order' => 0,
        'settings' => ['cards' => [['title' => 'One', 'photo' => '']]],
    ]);

    Livewire::test(PageEditor::class, ['page' => $page])
        ->call('selectSection', $section->id)
        ->set('imageUploads.cards.0.photo', UploadedFile::fake()->image('card.jpg'))
        ->assertHasNoErrors();

    $cards = $section->fresh()->settings['cards'];

    expect($cards[0]['title'])->toBe('One')
        ->and($cards[0]['photo'])->toStartWith('inlay/');
    Storage::disk('public')->assertExists($cards[0]['photo']);
});

it('stores an image uploaded into a site settings repeater item on that item', function () {
    Inlay::siteSettings('partners', Settings::make()->repeater('logos', Settings::make()->image('logo')));

    Livewire::test(SiteSettingsEditor::class)
        ->call('addRepeaterItem', 'logos')
        ->set('imageUploads.logos.0.logo', UploadedFile::fake()->image('logo.png'))
        ->assertHasNoErrors();

    $path = Inlay::setting('partners.logos.0.logo')->path;

    expect($path)->toStartWith('inlay/');
    Storage::disk('public')->assertExists($path);
});

it('shows the thumbnail of an image inside a repeater item', function () {
    config(['inlay.sections' => ['test-cards' => TestCardsSection::class]]);
    $page = Page::create(['title' => 'Home', 'path' => 'home']);
    $section = $page->sections()->create([
        'handle' => 'test-cards',
        'order' => 0,
        'settings' => ['cards' => [['title' => 'One', 'photo' => '/images/one.jpg']]],
    ]);

    Livewire::test(PageEditor::class, ['page' => $page])
        ->call('selectSection', $section->id)
        ->assertSee('src="/images/one.jpg"', escape: false);
});
