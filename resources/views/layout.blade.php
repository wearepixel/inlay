<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title ?? 'Inlay' }}</title>
    <link rel="stylesheet" href="{{ route('inlay.admin.assets.css') }}">
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

    <script src="{{ route('inlay.admin.assets.js') }}" defer></script>
    @livewireScripts
</body>
</html>
