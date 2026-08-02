<div>
    <div class="inlay-topbar">
        <div>
            <span class="inlay-eyebrow">Content</span>
            <h1>New page</h1>
        </div>
        <div class="inlay-topbar__actions">
            <a href="{{ route('inlay.pages.index') }}" class="inlay-button inlay-button--ghost">Cancel</a>
            <button type="button" wire:click="save" class="inlay-button inlay-button--primary">Create page</button>
        </div>
    </div>

    <div class="inlay-page" style="max-width: 480px;">
        <div class="inlay-field">
            <label class="inlay-field__label" for="page-title">Title</label>
            <input id="page-title" type="text" wire:model.live.debounce.300ms="title" class="inlay-input" autofocus>
            @error('title') <p class="inlay-field__help" style="color: var(--inlay-danger);">{{ $message }}</p> @enderror
        </div>

        <div class="inlay-field">
            <label class="inlay-field__label" for="page-slug">Slug</label>
            <input id="page-slug" type="text" wire:model.live.debounce.300ms="slug" class="inlay-input">
            <p class="inlay-field__help">Used by your application to resolve this page's URL.</p>
            @error('slug') <p class="inlay-field__help" style="color: var(--inlay-danger);">{{ $message }}</p> @enderror
        </div>
    </div>
</div>
