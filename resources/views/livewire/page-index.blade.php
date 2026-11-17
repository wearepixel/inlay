<div>
    <div class="inlay-topbar">
        <div>
            <span class="inlay-eyebrow">Content</span>
            <h1>Pages</h1>
        </div>
        <div class="inlay-topbar__actions">
            <input
                type="search"
                wire:model.live.debounce.300ms="search"
                placeholder="Search pages…"
                class="inlay-input"
                style="width: 220px;"
                data-test="page-search"
            >
            <a href="{{ \WeArePixel\Inlay\Facades\Inlay::adminUrl('pages.create') }}" class="inlay-button inlay-button--primary">New page</a>
        </div>
    </div>

    <div class="inlay-page">
        <div class="inlay-filter-tabs" role="tablist" aria-label="Filter pages by status">
            <button type="button" role="tab" wire:click="filterByStatus('all')" @class(['inlay-filter-tabs__tab' => true, 'is-active' => $statusFilter === 'all']) aria-selected="{{ $statusFilter === 'all' ? 'true' : 'false' }}">All</button>
            <button type="button" role="tab" wire:click="filterByStatus('draft')" @class(['inlay-filter-tabs__tab' => true, 'is-active' => $statusFilter === 'draft']) aria-selected="{{ $statusFilter === 'draft' ? 'true' : 'false' }}">Draft</button>
            <button type="button" role="tab" wire:click="filterByStatus('published')" @class(['inlay-filter-tabs__tab' => true, 'is-active' => $statusFilter === 'published']) aria-selected="{{ $statusFilter === 'published' ? 'true' : 'false' }}">Published</button>
        </div>

        @if ($pages->isEmpty())
            <p class="inlay-empty-state">
                @if ($search !== '' || $statusFilter !== 'all')
                    No pages match this filter.
                @else
                    No pages yet. Create the first one to get started.
                @endif
            </p>
        @else
            <table class="inlay-table">
                <thead>
                    <tr>
                        <th>Title</th>
                        <th>Status</th>
                        <th>Updated</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($pages as $page)
                        <tr wire:key="page-{{ $page->id }}">
                            <td>
                                <a href="{{ \WeArePixel\Inlay\Facades\Inlay::adminUrl('pages.edit', $page) }}" class="inlay-table__title">{{ $page->title }}</a>
                                <div class="inlay-table__slug">
                                    @if ($this->url($page) !== null)
                                        {{ $this->url($page) }}
                                    @else
                                        <span style="opacity: 0.6;">No public path</span>
                                    @endif
                                </div>
                            </td>
                            <td>
                                <div class="inlay-table__status">
                                    <span class="inlay-badge inlay-badge--{{ $page->status->value }}">{{ $page->status->value }}</span>
                                    @if ($page->is_homepage)
                                        <span class="inlay-badge inlay-badge--homepage">Homepage</span>
                                    @endif
                                </div>
                            </td>
                            <td title="{{ $page->updated_at?->toDayDateTimeString() }}">{{ $page->updated_at?->diffForHumans() }}</td>
                            <td>
                                <div class="inlay-table__actions">
                                    <a href="{{ \WeArePixel\Inlay\Facades\Inlay::adminUrl('pages.edit', $page) }}" class="inlay-button inlay-button--small">Edit</a>
                                    <button
                                        type="button"
                                        wire:click="delete({{ $page->id }})"
                                        wire:confirm="Delete this page? This cannot be undone."
                                        class="inlay-button inlay-button--small inlay-button--danger"
                                    >Delete</button>
                                </div>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>

            <div style="margin-top: 20px;">
                {{ $pages->links('inlay::pagination') }}
            </div>
        @endif
    </div>
</div>
