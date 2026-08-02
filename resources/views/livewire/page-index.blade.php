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
            <a href="{{ route('inlay.pages.create') }}" class="inlay-button inlay-button--primary">New page</a>
        </div>
    </div>

    <div class="inlay-page">
        @if ($pages->isEmpty())
            <p class="inlay-empty-state">No pages yet. Create the first one to get started.</p>
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
                                <a href="{{ route('inlay.pages.edit', $page) }}" class="inlay-table__title">{{ $page->title }}</a>
                                <div class="inlay-table__slug">/{{ $page->slug }}</div>
                            </td>
                            <td>
                                <span class="inlay-badge inlay-badge--{{ $page->status->value }}">{{ $page->status->value }}</span>
                            </td>
                            <td>{{ $page->updated_at?->diffForHumans() }}</td>
                            <td style="text-align: right;">
                                <a href="{{ route('inlay.pages.edit', $page) }}" class="inlay-button inlay-button--small">Edit</a>
                                <button
                                    type="button"
                                    wire:click="delete({{ $page->id }})"
                                    wire:confirm="Delete this page? This cannot be undone."
                                    class="inlay-button inlay-button--small inlay-button--danger"
                                >Delete</button>
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
