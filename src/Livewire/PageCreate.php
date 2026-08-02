<?php

declare(strict_types=1);

namespace WearePixel\Inlay\Livewire;

use Illuminate\Contracts\View\View;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Component;
use WearePixel\Inlay\Models\Page;

#[Layout('inlay::layout')]
final class PageCreate extends Component
{
    public string $title = '';

    public string $slug = '';

    /**
     * Must be a public (Livewire-hydrated) property, not a plain private
     * one - Livewire reconstructs the component from its public-property
     * snapshot on every request, so a private property silently resets to
     * its default every round trip and would never actually remember that
     * the slug was edited by hand.
     */
    public bool $slugManuallyEdited = false;

    public function updatedTitle(): void
    {
        if (! $this->slugManuallyEdited) {
            $this->slug = Str::slug($this->title);
        }
    }

    public function updatedSlug(): void
    {
        $this->slugManuallyEdited = true;
    }

    public function save(): void
    {
        $validated = $this->validate([
            'title' => ['required', 'string', 'max:255'],
            'slug' => ['required', 'string', 'max:255', 'alpha_dash', Rule::unique('pages', 'slug')],
        ]);

        $page = Page::create($validated);

        $this->redirectRoute('inlay.pages.edit', ['page' => $page], navigate: false);
    }

    public function render(): View
    {
        return view('inlay::livewire.page-create', [
            'title' => 'Create page · Inlay',
        ]);
    }
}
