<x-dynamic-component :component="$component" :title="$title ?? null">
    <x-inlay::frame>{{ $slot }}</x-inlay::frame>
</x-dynamic-component>
