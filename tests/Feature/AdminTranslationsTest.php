<?php

declare(strict_types=1);

use Livewire\Livewire;
use WeArePixel\Inlay\Livewire\PageEditor;
use WeArePixel\Inlay\Models\Page;

it('renders editor wording a host app has overridden in its translations', function () {
    app('translator')->addLines(['admin.editor.publish' => 'Go live'], 'en', 'inlay');

    $page = Page::create(['title' => 'Home', 'path' => 'home']);

    Livewire::test(PageEditor::class, ['page' => $page])
        ->assertSeeHtml('wire:target="publish">Go live</span>')
        ->assertDontSeeHtml('wire:target="publish">Publish</span>');
});
