<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title ?? __('inlay::admin.nav.brand') }}</title>
    {{-- "?v=" busts the 1-hour Cache-Control on these assets whenever the file itself changes. --}}
    <link rel="stylesheet" href="{{ route('inlay.admin.assets.css') }}?v={{ \WeArePixel\Inlay\InlayManager::assetVersion('inlay.css') }}">
    @livewireStyles
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
            <span class="inlay-topnav__brand">{{ __('inlay::admin.nav.brand') }}</span>
            <nav class="inlay-topnav__links">
                <a
                    href="{{ route('inlay.admin.pages.index') }}"
                    class="inlay-topnav__link {{ request()->routeIs('inlay.admin.pages.*') ? 'is-active' : '' }}"
                >{{ __('inlay::admin.nav.pages') }}</a>
                @if (app(\WeArePixel\Inlay\SiteSettings\SiteSettingsRegistry::class)->all() !== [])
                    <a
                        href="{{ \WeArePixel\Inlay\Facades\Inlay::adminUrl('settings') }}"
                        class="inlay-topnav__link {{ request()->routeIs('inlay.admin.settings') ? 'is-active' : '' }}"
                    >{{ __('inlay::admin.nav.settings') }}</a>
                @endif
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
