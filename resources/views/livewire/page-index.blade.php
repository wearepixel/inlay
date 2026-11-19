<div>
    <div class="inlay-topbar">
        <div>
            <span class="inlay-eyebrow">{{ __('inlay::admin.common.content_eyebrow') }}</span>
            <h1>{{ __('inlay::admin.pages.heading') }}</h1>
        </div>
        <div class="inlay-topbar__actions">
            <input
                type="search"
                wire:model.live.debounce.300ms="search"
                placeholder="{{ __('inlay::admin.pages.search_placeholder') }}"
                class="inlay-input"
                style="width: 220px;"
                data-test="page-search"
            >
            <a href="{{ \WeArePixel\Inlay\Facades\Inlay::adminUrl('pages.create') }}" class="inlay-button inlay-button--primary">{{ __('inlay::admin.pages.new_page') }}</a>
        </div>
    </div>

    <div class="inlay-page">
        <div class="inlay-filter-tabs" role="tablist" aria-label="{{ __('inlay::admin.pages.filter_label') }}">
            <button type="button" role="tab" wire:click="filterByStatus('all')" @class(['inlay-filter-tabs__tab' => true, 'is-active' => $statusFilter === 'all']) aria-selected="{{ $statusFilter === 'all' ? 'true' : 'false' }}">{{ __('inlay::admin.pages.filter_all') }}</button>
            <button type="button" role="tab" wire:click="filterByStatus('draft')" @class(['inlay-filter-tabs__tab' => true, 'is-active' => $statusFilter === 'draft']) aria-selected="{{ $statusFilter === 'draft' ? 'true' : 'false' }}">{{ __('inlay::admin.pages.filter_draft') }}</button>
            <button type="button" role="tab" wire:click="filterByStatus('published')" @class(['inlay-filter-tabs__tab' => true, 'is-active' => $statusFilter === 'published']) aria-selected="{{ $statusFilter === 'published' ? 'true' : 'false' }}">{{ __('inlay::admin.pages.filter_published') }}</button>
        </div>

        @if ($pages->isEmpty())
            <p class="inlay-empty-state">
                @if ($search !== '' || $statusFilter !== 'all')
                    {{ __('inlay::admin.pages.empty_filtered') }}
                @else
                    {{ __('inlay::admin.pages.empty') }}
                @endif
            </p>
        @else
            <table class="inlay-table">
                <thead>
                    <tr>
                        <th>{{ __('inlay::admin.pages.column_title') }}</th>
                        <th>{{ __('inlay::admin.pages.column_status') }}</th>
                        <th>{{ __('inlay::admin.pages.column_updated') }}</th>
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
                                        <span style="opacity: 0.6;">{{ __('inlay::admin.pages.no_public_path') }}</span>
                                    @endif
                                </div>
                            </td>
                            <td>
                                <div class="inlay-table__status">
                                    <span class="inlay-badge inlay-badge--{{ $page->status->value }}">{{ __('inlay::admin.status.'.$page->status->value) }}</span>
                                    @if ($page->is_homepage)
                                        <span class="inlay-badge inlay-badge--homepage">{{ __('inlay::admin.pages.homepage_badge') }}</span>
                                    @endif
                                    @if ($takenBy = $this->routeConflict($page))
                                        <span class="inlay-badge inlay-badge--hidden" title="{{ __('inlay::admin.pages.route_conflict_title', ['route' => ucfirst($takenBy)]) }}" data-test="route-conflict-{{ $page->id }}">{{ __('inlay::admin.pages.hidden_by_route') }}</span>
                                    @endif
                                </div>
                            </td>
                            <td title="{{ $page->updated_at?->toDayDateTimeString() }}">{{ $page->updated_at?->diffForHumans() }}</td>
                            <td>
                                <div class="inlay-table__actions">
                                    <a href="{{ \WeArePixel\Inlay\Facades\Inlay::adminUrl('pages.edit', $page) }}" class="inlay-button inlay-button--small">{{ __('inlay::admin.pages.edit') }}</a>
                                    <button
                                        type="button"
                                        wire:click="delete({{ $page->id }})"
                                        wire:confirm="{{ __('inlay::admin.pages.delete_confirm') }}"
                                        class="inlay-button inlay-button--small inlay-button--danger"
                                    >{{ __('inlay::admin.pages.delete') }}</button>
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
