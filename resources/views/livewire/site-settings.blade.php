<div
    x-data="{ message: '', timer: null }"
    x-on:setting-saved.window="message = @js(__('inlay::admin.common.saved')); clearTimeout(timer); timer = setTimeout(() => message = '', 1200)"
>
    <div class="inlay-topbar">
        <div>
            <span class="inlay-eyebrow">{{ __('inlay::admin.settings.eyebrow') }}</span>
            <h1>{{ __('inlay::admin.settings.heading') }}</h1>
        </div>
        <div class="inlay-topbar__actions">
            <span wire:loading.delay wire:target="editingProps, imageUploads" class="inlay-status inlay-status--saving">{{ __('inlay::admin.common.saving') }}</span>
            <span x-show="message" x-cloak x-text="message" x-transition.opacity class="inlay-status inlay-status--success" role="status" aria-live="polite"></span>
        </div>
    </div>

    <div class="inlay-page inlay-site-settings">
        @if ($current === null)
            <p class="inlay-inspector__empty">{{ __('inlay::admin.settings.empty') }}</p>
        @else
            @if (count($groups) > 1)
                <div class="inlay-tabs" role="tablist">
                    @foreach ($groups as $handle => $group)
                        <button
                            type="button"
                            role="tab"
                            wire:click="selectGroup(@js($handle))"
                            class="inlay-tabs__tab {{ $handle === $current->handle ? 'is-active' : '' }}"
                            aria-selected="{{ $handle === $current->handle ? 'true' : 'false' }}"
                            data-test="settings-group-{{ $handle }}"
                        >{{ $group->label }}</button>
                    @endforeach
                </div>
            @endif

            <div class="inlay-site-settings__fields" wire:key="settings-{{ $current->handle }}">
                @foreach ($fields as $field)
                    <div wire:key="field-{{ $current->handle }}-{{ $field->name }}" @if ($field->visibilityExpression()) x-data x-show="{{ $field->visibilityExpression() }}" x-cloak x-transition @endif>
                        @include(\WeArePixel\Inlay\Facades\Inlay::fieldView($field->type), [
                            'field' => $field,
                            'modelPath' => "editingProps.{$field->name}",
                        ])
                    </div>
                @endforeach
            </div>
        @endif
    </div>
</div>
