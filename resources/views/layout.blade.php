<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title ?? 'Inlay' }}</title>
    <link rel="stylesheet" href="{{ route('inlay.assets.css') }}">
</head>
<body class="inlay">
    <div class="inlay-shell">
        <aside class="inlay-sidebar">
            <div class="inlay-sidebar__brand">Inlay</div>
            <nav class="inlay-nav">
                <a
                    href="{{ route('inlay.pages.index') }}"
                    class="inlay-nav__link {{ request()->routeIs('inlay.pages.*') ? 'is-active' : '' }}"
                >Pages</a>
            </nav>
        </aside>

        <div class="inlay-main">
            {{ $slot }}
        </div>
    </div>

    <script src="{{ route('inlay.assets.js') }}" defer></script>
    @livewireScripts
</body>
</html>
