<div class="mb-4">
    <label class="mb-1 block text-sm font-medium text-gray-700">{{ $field->label }}</label>
    <select wire:model.live="{{ $modelPath }}" class="w-full rounded-md border-gray-300 shadow-sm">
        @foreach ($field->options['options'] as $value => $optionLabel)
            <option value="{{ $value }}">{{ $optionLabel }}</option>
        @endforeach
    </select>
</div>
