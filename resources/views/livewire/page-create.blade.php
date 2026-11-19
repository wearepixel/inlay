<div>
    <div class="inlay-topbar">
        <div>
            <span class="inlay-eyebrow">{{ __('inlay::admin.common.content_eyebrow') }}</span>
            <h1>{{ __('inlay::admin.create.heading') }}</h1>
        </div>
        <div class="inlay-topbar__actions">
            <a href="{{ \WeArePixel\Inlay\Facades\Inlay::adminUrl('pages.index') }}" class="inlay-button inlay-button--ghost">{{ __('inlay::admin.create.cancel') }}</a>
            <button type="button" wire:click="save" class="inlay-button inlay-button--primary" data-test="save-page">{{ __('inlay::admin.create.submit') }}</button>
        </div>
    </div>

    <div class="inlay-page" style="max-width: 480px;">
        <div class="inlay-field">
            <label class="inlay-field__label" for="page-title">{{ __('inlay::admin.page_fields.title') }}</label>
            <input id="page-title" type="text" wire:model="title" class="inlay-input" autofocus data-test="page-title">
            @error('title') <p class="inlay-field__help" style="color: var(--inlay-danger);">{{ $message }}</p> @enderror
        </div>

        @if (count($this->definitions) > 1)
            <div class="inlay-field">
                <label class="inlay-field__label" for="page-definition">{{ __('inlay::admin.page_fields.page_type') }}</label>
                <select id="page-definition" wire:model="definitionHandle" class="inlay-input" data-test="page-definition">
                    @foreach ($this->definitions as $definition)
                        <option value="{{ $definition->handle }}">{{ $definition->label }}</option>
                    @endforeach
                </select>
                <p class="inlay-field__help">{{ __('inlay::admin.create.page_type_help') }}</p>
            </div>
        @endif

        <div class="inlay-field">
            <label class="inlay-field__label" for="page-path">{{ __('inlay::admin.page_fields.public_path') }}</label>
            <input id="page-path" type="text" wire:model.live.debounce.300ms="path" class="inlay-input" placeholder="{{ __('inlay::admin.page_fields.path_placeholder') }}" data-test="page-path">
            <p class="inlay-field__help">
                {!! __('inlay::admin.create.path_help', ['method' => '<code>Inlay::renderPage()</code>']) !!}
                @if ($this->effectiveUrl)
                    {!! __('inlay::admin.create.will_publish_at', ['url' => '<code data-test="effective-url">'.e($this->effectiveUrl).'</code>']) !!}
                @endif
            </p>
            @error('path') <p class="inlay-field__help" style="color: var(--inlay-danger);">{{ $message }}</p> @enderror
            @if ($this->routeConflict)
                @include('inlay::livewire.partials.route-conflict', ['url' => $this->effectiveUrl, 'takenBy' => $this->routeConflict])
            @endif
        </div>

        <div class="inlay-field">
            <label class="inlay-field__label" style="display: flex; align-items: center; gap: 8px;">
                <input type="checkbox" wire:model.live="isHomepage" data-test="page-is-homepage">
                {{ __('inlay::admin.page_fields.set_as_homepage') }}
            </label>
            <p class="inlay-field__help">{{ __('inlay::admin.create.homepage_help') }}</p>
        </div>

        <div class="inlay-field">
            <label class="inlay-field__label" for="page-meta-title">{{ __('inlay::admin.page_fields.meta_title') }}</label>
            <input id="page-meta-title" type="text" wire:model="metaTitle" class="inlay-input" data-test="page-meta-title">
            <p class="inlay-field__help">{{ __('inlay::admin.create.meta_title_help') }}</p>
            @error('metaTitle') <p class="inlay-field__help" style="color: var(--inlay-danger);">{{ $message }}</p> @enderror
        </div>

        <div class="inlay-field">
            <label class="inlay-field__label" for="page-meta-description">{{ __('inlay::admin.page_fields.meta_description') }}</label>
            <textarea id="page-meta-description" wire:model="metaDescription" class="inlay-input" rows="3" data-test="page-meta-description"></textarea>
            <p class="inlay-field__help">{{ __('inlay::admin.create.meta_description_help') }}</p>
            @error('metaDescription') <p class="inlay-field__help" style="color: var(--inlay-danger);">{{ $message }}</p> @enderror
        </div>
    </div>
</div>
