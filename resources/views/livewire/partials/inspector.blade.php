{{-- Expects $inspectorTabs, $inspectorKey, $pathPrefix (e.g. "editingProps.") and $visibilityScope --}}
<div x-data="{ activeTab: 0 }" wire:key="inspector-{{ $inspectorKey }}">
    @if ($inspectorTabs->count() > 1)
        <div class="inlay-tabs" role="tablist">
            @foreach ($inspectorTabs as $index => $tab)
                <button
                    type="button"
                    role="tab"
                    class="inlay-tabs__tab"
                    :class="activeTab === {{ $index }} ? 'is-active' : ''"
                    :aria-selected="(activeTab === {{ $index }}).toString()"
                    x-on:click="activeTab = {{ $index }}"
                >{{ $tab['label'] }}</button>
            @endforeach
        </div>
    @endif

    @foreach ($inspectorTabs as $index => $tab)
        <div role="tabpanel" @if ($inspectorTabs->count() > 1) x-show="activeTab === {{ $index }}" @endif>
            @foreach ($tab['groups'] as $group)
                @if ($group['label'])
                    <div class="inlay-group-section" x-data="inlayCollapsibleGroup(@js($group['label']), false)">
                        <button
                            type="button"
                            class="inlay-group-section__header"
                            x-on:click="toggle()"
                            :aria-expanded="(!collapsed).toString()"
                        >
                            {{ $group['label'] }}
                            <span class="inlay-group-section__chevron" :class="collapsed ? 'is-collapsed' : ''" aria-hidden="true"><x-inlay::icon name="chevron-down" :size="12" /></span>
                        </button>
                        <div class="inlay-group-section__body" x-show="!collapsed" x-transition>
                            @foreach ($group['fields'] as $field)
                                <div wire:key="field-{{ $inspectorKey }}-{{ $field->name }}" @if ($field->visibilityExpression($visibilityScope)) x-data x-show="{{ $field->visibilityExpression($visibilityScope) }}" x-cloak x-transition @endif>
                                    @include(\WeArePixel\Inlay\Facades\Inlay::fieldView($field->type), [
                                        'field' => $field,
                                        'modelPath' => "{$pathPrefix}{$field->name}",
                                    ])
                                </div>
                            @endforeach
                        </div>
                    </div>
                @else
                    @foreach ($group['fields'] as $field)
                        <div wire:key="field-{{ $inspectorKey }}-{{ $field->name }}" @if ($field->visibilityExpression($visibilityScope)) x-data x-show="{{ $field->visibilityExpression($visibilityScope) }}" x-cloak x-transition @endif>
                            @include(\WeArePixel\Inlay\Facades\Inlay::fieldView($field->type), [
                                'field' => $field,
                                'modelPath' => "{$pathPrefix}{$field->name}",
                            ])
                        </div>
                    @endforeach
                @endif
            @endforeach
        </div>
    @endforeach
</div>
