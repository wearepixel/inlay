<link rel="stylesheet" href="{{ route('inlay.admin.assets.css') }}?v={{ \WeArePixel\Inlay\InlayManager::assetVersion('inlay.css') }}">
<div {{ $attributes->class(['inlay', 'inlay-frame']) }}>
    {{ $slot }}
</div>
<script src="{{ route('inlay.admin.assets.js') }}?v={{ \WeArePixel\Inlay\InlayManager::assetVersion('inlay.js') }}" defer></script>
