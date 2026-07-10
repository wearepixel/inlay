<?php

declare(strict_types=1);

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use WearePixel\BladePageBuilder\Livewire\PageEditor;
use WearePixel\BladePageBuilder\Models\Page;
use WearePixel\BladePageBuilder\Tests\Fixtures\TestHeroSection;

beforeEach(function () {
    config(['blade-page-builder.sections' => [
        'test-hero' => TestHeroSection::class,
    ]]);
});

it('renders a text input and select options for the selected section schema', function () {
    $page = Page::create(['title' => 'Home', 'slug' => 'home']);
    $section = $page->sections()->create([
        'section_type' => 'test-hero',
        'order' => 0,
        'props' => ['heading' => 'Hello', 'layout' => 'image-right'],
    ]);

    Livewire::test(PageEditor::class, ['page' => $page])
        ->call('selectSection', $section->id)
        ->assertSee('Heading')
        ->assertSee('Layout')
        ->assertSee('Image on left')
        ->assertSee('Image on right');
});

it('stores an uploaded image and persists its path to the selected section props', function () {
    Storage::fake('public');

    $page = Page::create(['title' => 'Home', 'slug' => 'home']);
    $section = $page->sections()->create([
        'section_type' => 'test-hero',
        'order' => 0,
        'props' => ['heading' => 'Hello', 'layout' => 'image-right'],
    ]);

    $file = UploadedFile::fake()->image('photo.jpg');

    Livewire::test(PageEditor::class, ['page' => $page])
        ->call('selectSection', $section->id)
        ->set('imageUploads.photo', $file)
        ->assertOk();

    Storage::disk('public')->assertExists($section->fresh()->props['photo'] ?? '');
});

it('rejects a non-image upload and does not persist it to the selected section props', function () {
    Storage::fake('public');

    $page = Page::create(['title' => 'Home', 'slug' => 'home']);
    $section = $page->sections()->create([
        'section_type' => 'test-hero',
        'order' => 0,
        'props' => ['heading' => 'Hello', 'layout' => 'image-right'],
    ]);

    $file = UploadedFile::fake()->create('malicious.svg', 10, 'image/svg+xml');

    Livewire::test(PageEditor::class, ['page' => $page])
        ->call('selectSection', $section->id)
        ->set('imageUploads.photo', $file)
        ->assertHasErrors(['imageUploads.photo']);

    expect($section->fresh()->props['photo'] ?? null)->toBeNull();
});
