<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title ?? 'Inlay' }}</title>
    {{--
        A "?v=" query string keyed to the file's own last-modified time -
        InlayAssetController caches these responses for an hour
        (Cache-Control: max-age=3600), which is fine for a given version but
        meant a change to inlay.css/inlay.js could sit invisible in an
        already-open browser tab for up to an hour with no way to tell.
        Changing the file changes the URL, so a stale cached response for
        the *old* URL is simply never requested again - no separate
        cache-clearing step needed after updating the package.
    --}}
    <link rel="stylesheet" href="{{ route('inlay.admin.assets.css') }}?v={{ \WeArePixel\Inlay\InlayManager::assetVersion('inlay.css') }}">
</head>
<body class="inlay">
    <div class="inlay-shell">
        {{--
            A slim top nav, not a permanent side rail - see the comment atop
            .inlay-topnav in resources/dist/inlay.css for why. Each
            destination is one more <a> here; Media/Navigation/Settings slot
            in the same way once they exist, with no structural change.
        --}}
        <header class="inlay-topnav">
            <span class="inlay-topnav__brand">Inlay</span>
            <nav class="inlay-topnav__links">
                <a
                    href="{{ route('inlay.admin.pages.index') }}"
                    class="inlay-topnav__link {{ request()->routeIs('inlay.admin.pages.*') ? 'is-active' : '' }}"
                >Pages</a>
            </nav>
        </header>

        <div class="inlay-main">
            {{ $slot }}
        </div>
    </div>

    <script src="{{ route('inlay.admin.assets.js') }}?v={{ \WeArePixel\Inlay\InlayManager::assetVersion('inlay.js') }}" defer></script>
    @livewireScripts
</body>
</html>
