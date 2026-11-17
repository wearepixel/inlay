<div>
    <div class="inlay-topbar">
        <div>
            <span class="inlay-eyebrow">Content</span>
            <h1>New page</h1>
        </div>
        <div class="inlay-topbar__actions">
            <a href="{{ \WeArePixel\Inlay\Facades\Inlay::adminUrl('pages.index') }}" class="inlay-button inlay-button--ghost">Cancel</a>
            <button type="button" wire:click="save" class="inlay-button inlay-button--primary" data-test="save-page">Create page</button>
        </div>
    </div>

    <div class="inlay-page" style="max-width: 480px;">
        <div class="inlay-field">
            <label class="inlay-field__label" for="page-title">Title</label>
            <input id="page-title" type="text" wire:model="title" class="inlay-input" autofocus data-test="page-title">
            @error('title') <p class="inlay-field__help" style="color: var(--inlay-danger);">{{ $message }}</p> @enderror
        </div>

        @if (count($this->definitions) > 1)
            <div class="inlay-field">
                <label class="inlay-field__label" for="page-definition">Page type</label>
                <select id="page-definition" wire:model="definitionHandle" class="inlay-input" data-test="page-definition">
                    @foreach ($this->definitions as $definition)
                        <option value="{{ $definition->handle }}">{{ $definition->label }}</option>
                    @endforeach
                </select>
                <p class="inlay-field__help">Controls which blocks this page may contain, and which structure it must retain. Cannot be changed after the page is created.</p>
            </div>
        @endif

        <div class="inlay-field">
            <label class="inlay-field__label" for="page-path">Public path</label>
            <input id="page-path" type="text" wire:model.live.debounce.300ms="path" class="inlay-input" placeholder="about/team" data-test="page-path">
            <p class="inlay-field__help">
                Optional. Leave blank for a page with no public URL of its own - your application can still render
                it directly via <code>Inlay::renderPage()</code>.
                @if ($this->effectiveUrl)
                    Will publish at <code data-test="effective-url">{{ $this->effectiveUrl }}</code>.
                @endif
            </p>
            @error('path') <p class="inlay-field__help" style="color: var(--inlay-danger);">{{ $message }}</p> @enderror
        </div>

        <div class="inlay-field">
            <label class="inlay-field__label" style="display: flex; align-items: center; gap: 8px;">
                <input type="checkbox" wire:model.live="isHomepage" data-test="page-is-homepage">
                Set as homepage
            </label>
            <p class="inlay-field__help">Serves this page at your site's root URL instead of the public path above. Replaces the current homepage, if one is already set.</p>
        </div>

        <div class="inlay-field">
            <label class="inlay-field__label" for="page-meta-title">Meta title</label>
            <input id="page-meta-title" type="text" wire:model="metaTitle" class="inlay-input" data-test="page-meta-title">
            <p class="inlay-field__help">Shown in search results and browser tabs. Falls back to the page title above when left blank.</p>
            @error('metaTitle') <p class="inlay-field__help" style="color: var(--inlay-danger);">{{ $message }}</p> @enderror
        </div>

        <div class="inlay-field">
            <label class="inlay-field__label" for="page-meta-description">Meta description</label>
            <textarea id="page-meta-description" wire:model="metaDescription" class="inlay-input" rows="3" data-test="page-meta-description"></textarea>
            <p class="inlay-field__help">A short summary shown in search results. Left blank, no description is output.</p>
            @error('metaDescription') <p class="inlay-field__help" style="color: var(--inlay-danger);">{{ $message }}</p> @enderror
        </div>
    </div>
</div>
