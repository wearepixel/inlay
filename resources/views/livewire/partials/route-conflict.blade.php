{{-- Expects $url and $takenBy (the route that answers $url instead of the page) --}}
<div class="inlay-violation-banner inlay-route-conflict" data-test="route-conflict" role="alert">
    <span class="inlay-violation-banner__title">This page won't show at {{ $url }}</span>
    <p class="inlay-route-conflict__body">
        {{ ucfirst($takenBy) }} answers that URL first. Remove or rename that route, give this page another path,
        or make sure <code>Inlay::pageRoutes()</code> is the last route your application registers.
    </p>
</div>
