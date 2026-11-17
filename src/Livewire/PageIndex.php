<?php

declare(strict_types=1);

namespace WeArePixel\Inlay\Livewire;

use Illuminate\Contracts\View\View;
use Illuminate\Pagination\LengthAwarePaginator;
use Livewire\Component;
use Livewire\WithPagination;
use WeArePixel\Inlay\Enums\PageStatus;
use WeArePixel\Inlay\InlayManager;
use WeArePixel\Inlay\Livewire\Concerns\InlayAdminComponent;
use WeArePixel\Inlay\Models\Page;

final class PageIndex extends Component
{
    use InlayAdminComponent;
    use WithPagination;

    public string $search = '';

    /**
     * 'all', or a WeArePixel\Inlay\Enums\PageStatus::value ('draft',
     * 'published') - a plain string rather than a nullable PageStatus so
     * "all" has a real, bindable value for the filter tabs to compare
     * against, instead of overloading null to mean it.
     */
    public string $statusFilter = 'all';

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function filterByStatus(string $status): void
    {
        $this->statusFilter = $status;
        $this->resetPage();
    }

    public function url(Page $page): ?string
    {
        return app(InlayManager::class)->url($page);
    }

    public function delete(int $pageId): void
    {
        Page::whereKey($pageId)->delete();
    }

    public function render(): View
    {
        return $this->adminView('inlay::livewire.page-index', 'Pages · Inlay', [
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
            ->when(
                PageStatus::tryFrom($this->statusFilter),
                fn ($query, PageStatus $status) => $query->where('status', $status),
            )
            ->latest('updated_at')
            ->paginate(20);
    }
}
