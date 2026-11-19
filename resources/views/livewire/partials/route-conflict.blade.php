{{-- Expects $url and $takenBy (the route that answers $url instead of the page) --}}
<div class="inlay-violation-banner inlay-route-conflict" data-test="route-conflict" role="alert">
    <span class="inlay-violation-banner__title">{{ __('inlay::admin.route_conflict.title', ['url' => $url]) }}</span>
    <p class="inlay-route-conflict__body">
        {!! __('inlay::admin.route_conflict.body', ['route' => e(ucfirst($takenBy)), 'method' => '<code>Inlay::pageRoutes()</code>']) !!}
    </p>
</div>
