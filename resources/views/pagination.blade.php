@if ($paginator->hasPages())
    <nav role="navigation" aria-label="{{ __('Pagination Navigation') }}" class="inlay-pagination">
        <ul class="inlay-pagination__list">
            <li>
                @if ($paginator->onFirstPage())
                    <span class="inlay-pagination__link inlay-pagination__link--disabled" aria-disabled="true" aria-label="{{ __('pagination.previous') }}">&laquo; {{ __('inlay::admin.pagination.previous') }}</span>
                @else
                    <a href="{{ $paginator->previousPageUrl() }}" rel="prev" class="inlay-pagination__link" aria-label="{{ __('pagination.previous') }}">&laquo; {{ __('inlay::admin.pagination.previous') }}</a>
                @endif
            </li>

            @foreach ($elements as $element)
                @if (is_string($element))
                    <li><span class="inlay-pagination__dots" aria-hidden="true">{{ $element }}</span></li>
                @endif

                @if (is_array($element))
                    @foreach ($element as $page => $url)
                        <li>
                            @if ($page == $paginator->currentPage())
                                <span class="inlay-pagination__link inlay-pagination__link--current" aria-current="page">{{ $page }}</span>
                            @else
                                <a href="{{ $url }}" class="inlay-pagination__link" aria-label="{{ __('Go to page :page', ['page' => $page]) }}">{{ $page }}</a>
                            @endif
                        </li>
                    @endforeach
                @endif
            @endforeach

            <li>
                @if ($paginator->hasMorePages())
                    <a href="{{ $paginator->nextPageUrl() }}" rel="next" class="inlay-pagination__link" aria-label="{{ __('pagination.next') }}">{{ __('inlay::admin.pagination.next') }} &raquo;</a>
                @else
                    <span class="inlay-pagination__link inlay-pagination__link--disabled" aria-disabled="true" aria-label="{{ __('pagination.next') }}">{{ __('inlay::admin.pagination.next') }} &raquo;</span>
                @endif
            </li>
        </ul>
    </nav>
@endif
