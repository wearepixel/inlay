<?php

declare(strict_types=1);

namespace WeArePixel\Inlay\Livewire;

use Illuminate\Contracts\View\View;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;
use WeArePixel\Inlay\Livewire\Concerns\InlayAdminComponent;
use WeArePixel\Inlay\SiteSettings\SiteSettingsGroup;
use WeArePixel\Inlay\SiteSettings\SiteSettingsRegistry;

final class SiteSettingsEditor extends Component
{
    use InlayAdminComponent;
    use WithFileUploads;

    #[Locked]
    public ?string $group = null;

    /** @var array<string, mixed> */
    public array $editingProps = [];

    /** @var array<string, mixed> */
    public array $imageUploads = [];

    public function mount(?string $group = null): void
    {
        $registry = app(SiteSettingsRegistry::class);

        $this->group = $group !== null && $registry->group($group) !== null
            ? $group
            : array_key_first($registry->all());

        abort_if($group !== null && $this->group !== $group, 404);

        $this->loadGroup();
    }

    public function selectGroup(string $handle): void
    {
        if (app(SiteSettingsRegistry::class)->group($handle) === null) {
            return;
        }

        $this->group = $handle;
        $this->imageUploads = [];
        $this->loadGroup();
    }

    public function updatedEditingProps(mixed $value, string $key): void
    {
        $this->persist(explode('.', $key)[0]);
    }

    public function addRepeaterItem(string $path): void
    {
        $items = data_get($this->editingProps, $path);
        data_set($this->editingProps, $path, [...(is_array($items) ? array_values($items) : []), []]);
        $this->persist(explode('.', $path)[0]);
    }

    public function removeRepeaterItem(string $path, int $index): void
    {
        $items = data_get($this->editingProps, $path);
        $items = is_array($items) ? array_values($items) : [];
        unset($items[$index]);
        data_set($this->editingProps, $path, array_values($items));
        $this->persist(explode('.', $path)[0]);
    }

    public function updatedImageUploads(mixed $value, string $key): void
    {
        if (! $value instanceof TemporaryUploadedFile) {
            return;
        }

        $this->validate([
            "imageUploads.{$key}" => ['image', 'mimes:jpg,jpeg,png,webp,gif', 'max:5120'],
        ]);

        data_set($this->editingProps, $key, $value->store('inlay', config('inlay.disk', 'public')));
        $this->persist(explode('.', $key)[0]);
    }

    /**
     * Empties an optional image or link field, which has no text to delete.
     * $path is relative to editingProps, e.g. "photo" or "items.0.photo".
     */
    public function clearField(string $path): void
    {
        data_set($this->editingProps, $path, null);
        $this->persist(explode('.', $path)[0]);
    }

    public function render(): View
    {
        $registry = app(SiteSettingsRegistry::class);
        $current = $this->group !== null ? $registry->group($this->group) : null;

        return $this->adminView('inlay::livewire.site-settings', 'Settings · Inlay', [
            'groups' => $registry->all(),
            'current' => $current,
            'fields' => $current?->schema->fields() ?? [],
        ]);
    }

    private function loadGroup(): void
    {
        $this->editingProps = $this->group !== null
            ? app(SiteSettingsRegistry::class)->rawValues($this->group)
            : [];
    }

    private function persist(string $fieldName): void
    {
        $group = $this->group !== null ? app(SiteSettingsRegistry::class)->group($this->group) : null;

        if (! $group instanceof SiteSettingsGroup || $group->schema->field($fieldName) === null) {
            return;
        }

        $this->editingProps[$fieldName] = app(SiteSettingsRegistry::class)
            ->store($group->handle, $fieldName, $this->editingProps[$fieldName] ?? null);

        $this->dispatch('setting-saved');
    }
}
