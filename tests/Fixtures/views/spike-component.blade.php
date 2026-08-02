<div
    data-spike-heading="{{ $heading }}"
    data-spike-size="{{ $imageSize->value }}"
    data-spike-repo="{{ $repositoryLabel() }}"
>
    @if ($action)
        <a href="{{ $action->url }}">{{ $action->label }}</a>
    @endif

    <livewire:spike-inline-widget />
</div>
