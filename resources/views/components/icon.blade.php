{{--
    Inlay's own inline SVG icon set - no build step, no CDN, no icon font.
    A fixed, hand-picked set of paths keyed by name; $name is always a
    literal string from the package's own views, never user- or
    block-supplied, so echoing its path unescaped carries no injection risk.
--}}
@props(['name', 'size' => 16])
@php
    $paths = [
        'duplicate' => '<rect x="8" y="8" width="12" height="12" rx="2"/><path d="M16 8V6a2 2 0 0 0-2-2H6a2 2 0 0 0-2 2v8a2 2 0 0 0 2 2h2"/>',
        'trash' => '<path d="M4 6h16"/><path d="M9 6V4a2 2 0 0 1 2-2h2a2 2 0 0 1 2 2v2"/><path d="M6 6l1 14a2 2 0 0 0 2 2h6a2 2 0 0 0 2-2l1-14"/><path d="M10 11v6"/><path d="M14 11v6"/>',
        'grip' => '<circle cx="9" cy="6" r="1"/><circle cx="9" cy="12" r="1"/><circle cx="9" cy="18" r="1"/><circle cx="15" cy="6" r="1"/><circle cx="15" cy="12" r="1"/><circle cx="15" cy="18" r="1"/>',
        'chevron-down' => '<path d="M6 9l6 6 6-6"/>',
        'chevron-left' => '<path d="M15 18l-6-6 6-6"/>',
        'arrow-left' => '<path d="M19 12H5"/><path d="M11 18l-6-6 6-6"/>',
        'search' => '<circle cx="11" cy="11" r="7"/><path d="M21 21l-4.3-4.3"/>',
        'refresh' => '<path d="M21 12a9 9 0 1 1-3-6.7"/><path d="M21 3v6h-6"/>',
        'lock' => '<rect x="5" y="11" width="14" height="9" rx="2"/><path d="M8 11V7a4 4 0 0 1 8 0v4"/>',
        'close' => '<path d="M18 6 6 18"/><path d="M6 6l12 12"/>',
        'globe' => '<circle cx="12" cy="12" r="9"/><path d="M3 12h18"/><path d="M12 3a15 15 0 0 1 0 18"/><path d="M12 3a15 15 0 0 0 0 18"/>',
        'keyboard' => '<rect x="3" y="6" width="18" height="12" rx="2"/><path d="M6.5 10h.01M9.5 10h.01M12.5 10h.01M15.5 10h.01M17.5 10h.01M6.5 14h11"/>',
        'image' => '<rect x="3" y="4" width="18" height="16" rx="2"/><circle cx="9" cy="10" r="1.75"/><path d="M21 16l-5.5-5.5a2 2 0 0 0-2.8 0L4 19"/>',
    ];

    $path = $paths[$name] ?? '';
@endphp
<svg
    {{ $attributes->merge(['class' => 'inlay-icon']) }}
    width="{{ $size }}"
    height="{{ $size }}"
    viewBox="0 0 24 24"
    fill="none"
    stroke="currentColor"
    stroke-width="1.75"
    stroke-linecap="round"
    stroke-linejoin="round"
    aria-hidden="true"
>{!! $path !!}</svg>
