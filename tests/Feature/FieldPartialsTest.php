<?php

declare(strict_types=1);

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use WearePixel\Inlay\Livewire\PageEditor;
use WearePixel\Inlay\Models\Page;
use WearePixel\Inlay\Tests\Fixtures\TestHeroSection;

beforeEach(function () {
    config(['inlay.sections' => [
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
