<?php

declare(strict_types=1);

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use WeArePixel\Inlay\Livewire\PageEditor;
use WeArePixel\Inlay\Models\Page;
use WeArePixel\Inlay\Tests\Fixtures\TestHeroSection;
use WeArePixel\Inlay\Tests\Fixtures\TestRequiredFieldSection;

beforeEach(function () {
    config(['inlay.blocks' => [
        'test-hero' => TestHeroSection::class,
    ]]);
});

it('renders a text input and select options for the selected section schema', function () {
    $page = Page::create(['title' => 'Home', 'slug' => 'home']);
    $section = $page->sections()->create([
        'handle' => 'test-hero',
        'order' => 0,
        'settings' => ['heading' => 'Hello', 'layout' => 'image-right'],
    ]);

    Livewire::test(PageEditor::class, ['page' => $page])
        ->call('selectSection', $section->id)
        ->assertSee('Heading')
        ->assertSee('Layout')
        // TestHeroLayout has no SettingOptions implementation, so its
        // option labels are convention-derived from the case name.
        ->assertSee('Image Left')
        ->assertSee('Image Right');
});

it('stores an uploaded image and persists its path to the selected section settings', function () {
    Storage::fake('public');

    $page = Page::create(['title' => 'Home', 'slug' => 'home']);
    $section = $page->sections()->create([
        'handle' => 'test-hero',
        'order' => 0,
        'settings' => ['heading' => 'Hello', 'layout' => 'image-right'],
    ]);

    $file = UploadedFile::fake()->image('photo.jpg');

    Livewire::test(PageEditor::class, ['page' => $page])
        ->call('selectSection', $section->id)
        ->set('imageUploads.photo', $file)
        ->assertOk();

    Storage::disk('public')->assertExists($section->fresh()->settings['photo'] ?? '');
});

it('rejects a non-image upload and does not persist it to the selected section settings', function () {
    Storage::fake('public');

    $page = Page::create(['title' => 'Home', 'slug' => 'home']);
    $section = $page->sections()->create([
        'handle' => 'test-hero',
        'order' => 0,
        'settings' => ['heading' => 'Hello', 'layout' => 'image-right'],
    ]);

    $file = UploadedFile::fake()->create('malicious.svg', 10, 'image/svg+xml');

    Livewire::test(PageEditor::class, ['page' => $page])
        ->call('selectSection', $section->id)
        ->set('imageUploads.photo', $file)
        ->assertHasErrors(['imageUploads.photo']);

    expect($section->fresh()->settings['photo'] ?? null)->toBeNull();
});

it('shows a calm inline hint for an empty required field, and hides it once filled', function () {
    config(['inlay.blocks' => ['test-required' => TestRequiredFieldSection::class]]);

    $page = Page::create(['title' => 'Home', 'slug' => 'home']);
    $section = $page->sections()->create(['handle' => 'test-required', 'order' => 0, 'settings' => []]);

    // A required-but-empty field is a normal state while composing a page,
    // not an error - it gets a calm hint, not the red styling reserved for
    // a genuine validation failure (see the image-upload-rejection test
    // above, which does use inlay-field__warning).
    $test = Livewire::test(PageEditor::class, ['page' => $page])
        ->call('selectSection', $section->id)
        ->assertSeeHtml('inlay-field__hint');

    $test->set('editingProps.heading', 'Filled in')
        ->assertDontSeeHtml('inlay-field__hint');
});

it("shows an enum case's optional description under a Select field once it's the current value", function () {
    config(['inlay.blocks' => ['test-required' => TestRequiredFieldSection::class]]);

    $page = Page::create(['title' => 'Home', 'slug' => 'home']);
    $section = $page->sections()->create(['handle' => 'test-required', 'order' => 0, 'settings' => ['size' => 'large']]);

    Livewire::test(PageEditor::class, ['page' => $page])
        ->call('selectSection', $section->id)
        ->assertSee('Full-width, best for a strong first impression.');
});
