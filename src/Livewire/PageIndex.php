<?php

declare(strict_types=1);

namespace WeArePixel\Inlay\Livewire;

use Illuminate\Contracts\View\View;
use Illuminate\Pagination\LengthAwarePaginator;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;
use WeArePixel\Inlay\Models\Page;

#[Layout('inlay::layout')]
final class PageIndex extends Component
{
    use WithPagination;

    public string $search = '';

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function delete(int $pageId): void
    {
        Page::whereKey($pageId)->delete();
    }

    public function render(): View
    {
        return view('inlay::livewire.page-index', [
            'title' => 'Pages · Inlay',
            'pages' => $this->pages(),
        ]);
    }

    /**
     * @return LengthAwarePaginator<int, Page>
     */
    private function pages(): LengthAwarePaginator
    {
        return Page::query()
            ->when($this->search !== '', fn ($query) => $query->where('title', 'like', "%{$this->search}%"))
            ->latest('updated_at')
            ->paginate(20);
    }
}
