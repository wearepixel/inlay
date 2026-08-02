# Component authoring guide

A Component is an ordinary Laravel Blade class component - `Illuminate\View\Component` - that implements `WearePixel\Inlay\Contracts\InlayComponent`, a marker interface with no required methods. Nothing downstream (the registry, the picker, the settings inspector, rendering, publishing, revisions) treats a Component any differently based on how its settings were declared - this document covers both authoring styles and how they interact.

## Class + view Components

The only requirement is the marker interface and a `render()` method returning a view, exactly like any other Blade class component:

```php
namespace App\Inlay\Components;

use Illuminate\View\Component;
use Illuminate\View\View;
use WearePixel\Inlay\Contracts\InlayComponent;

final class Hero extends Component implements InlayComponent
{
    public function __construct(
        public string $heading = 'Welcome',
    ) {}

    public function render(): View
    {
        return view('components.hero');
    }
}
```

With no attributes and no `settings()` method, this component registers and renders fine - it simply has zero editable settings. The `heading` constructor parameter is never exposed to the editor unless you explicitly declare it (see below): an injected repository, a service, or any other constructor dependency is left entirely alone and resolved by the container as usual when the component renders. This is deliberate - a constructor parameter is never an editor setting by accident.

## Attributes

Declare a setting directly on the constructor parameter it maps to, using one of the field attributes:

| Attribute  | Field type                                                                                                                      |
| ---------- | ------------------------------------------------------------------------------------------------------------------------------- |
| `Text`     | Single-line text                                                                                                                |
| `Textarea` | Multi-line text                                                                                                                 |
| `Richtext` | Multi-line text (currently unsanitized HTML if you render it raw - see [Current limitations](../README.md#current-limitations)) |
| `Number`   | Integer or float                                                                                                                |
| `Toggle`   | Boolean                                                                                                                         |
| `Select`   | One value from a labeled set (a backed enum, or a literal options array)                                                        |
| `Image`    | A single uploaded file path                                                                                                     |
| `Link`     | Hydrates to `LinkValue` (label, url, openInNewTab)                                                                              |

There is no `Repeater` or `Group` attribute - a repeatable/grouped item schema has no single constructor parameter to attach to, so those two are fluent-only.

```php
use WearePixel\Inlay\Attributes\Number;
use WearePixel\Inlay\Attributes\Select;
use WearePixel\Inlay\Attributes\Text;
use WearePixel\Inlay\Attributes\Toggle;

final class Hero extends Component implements InlayComponent
{
    public function __construct(
        #[Text(label: 'Heading', maxLength: 80, required: true)]
        public string $heading = 'Welcome',

        #[Toggle(label: 'Show call to action')]
        public bool $showCta = false,

        #[Number(label: 'Columns')]
        public int $columns = 2,

        #[Select(label: 'Image size')]
        public ImageSize $imageSize = ImageSize::Large,
    ) {}

    // ...
}
```

Every attribute accepts `label`, `help`, `required`, and `visibleWhen` (an array pair: `[fieldName, value]`). `Text` additionally accepts `maxLength`; `Select` additionally accepts `options` (only needed when the constructor type isn't a backed enum - see [Enums](#enums)).

## Fluent `Settings`

Declare a static `settings(): Settings` method instead, useful for repeaters, groups, or keeping configuration out of the constructor signature entirely:

```php
use WearePixel\Inlay\Schema\Settings;

final class Faq extends Component implements InlayComponent
{
    public function __construct(
        public array $items = [],
    ) {}

    public static function settings(): Settings
    {
        $itemSchema = Settings::make()
            ->text('question')->label('Question')->required()
            ->textarea('answer')->label('Answer');

        return Settings::make()->repeater('items', $itemSchema)->label('FAQ items');
    }

    public function render(): View
    {
        return view('components.faq');
    }
}
```

`Settings::make()` returns a builder. Each field-defining call (`text()`, `textarea()`, `richtext()`, `number()`, `toggle()`, `select()`, `image()`, `link()`, `repeater()`, `group()`) starts a new field; the modifier calls that follow it (`label()`, `help()`, `required()`, `maxLength()`, `visibleWhen()`, `hiddenWhen()`, `options()`, `default()`) apply to whichever field was most recently defined. Calling a modifier before any field has been defined throws immediately (`...() must follow a field definition.`), as does declaring the same field name twice (`Duplicate setting [...] declared twice in the same settings definition.`).

`group()` works like `repeater()` but for a single nested object rather than a list:

```php
public static function settings(): Settings
{
    $addressSchema = Settings::make()
        ->text('street')->label('Street')
        ->text('city')->label('City');

    return Settings::make()->group('address', $addressSchema)->label('Address');
}
```

## Mixing attributes and fluent definitions

A single component can declare some settings via attributes and others via `settings()`. When both declare a field with the **same name**, the fluent declaration wins - the merge adopts every fluent field first, then adopts any attribute-declared field whose name wasn't already claimed. In practice this means: use attributes for the ordinary case, and drop into `settings()` only for the field that needs a repeater, a hand-tuned option list, or anything else attributes can't express - the two fields never actually collide unless you specifically override one.

## Categories and picker metadata

An optional `#[ComponentMeta]` class attribute controls how a component appears in the admin's component picker:

```php
use WearePixel\Inlay\Attributes\ComponentMeta;

#[ComponentMeta(
    label: 'Hero',
    category: 'Headers',
    description: 'A large banner with a heading and image.',
    icon: 'layout-header',
    thumbnail: null,
)]
final class Hero extends Component implements InlayComponent
{
    // ...
}
```

Every field is optional. With no `#[ComponentMeta]` at all, the picker falls back to a label derived from the component's registered handle (`Str::headline()`) and groups it under "Uncategorised".

## Constructor defaults

For every **top-level** setting, the constructor parameter's own default value and nullability are the exclusive source of truth:

- A parameter with a default (`public string $heading = 'Welcome'`) uses that as the setting's default.
- A nullable parameter with no default (`public ?string $subheading = null`) is fine too - `null` is a valid default.
- A required, non-nullable parameter with no default (`public string $heading`) fails registration immediately: `Component [...] setting [...] has no constructor default and is not nullable. A top-level setting must always be constructible with zero overrides - give it a default or make it nullable.`

There is deliberately no schema-level `.default()` for a top-level setting - it would create two sources of truth for the same thing. `.default()` on the fluent builder is only meaningful for a field **nested inside a repeater or group item schema**, which has no top-level constructor parameter to be authoritative instead:

```php
$itemSchema = Settings::make()
    ->text('question')->label('Question')
    ->toggle('featured')->label('Featured')->default(false); // fine - nested, no constructor param
```

## Enums

A `Select` setting typed as a PHP backed enum derives its options automatically:

```php
enum ImageSize: string
{
    case Small = 'small';
    case Large = 'large';
}
```

The stored value is always the enum's scalar `value`; the enum case is hydrated back before your component is constructed. Implement `WearePixel\Inlay\Support\SettingOptions` for custom labels (otherwise a case gets `Str::headline($case->name)`):

```php
enum ImageSize: string implements \WearePixel\Inlay\Support\SettingOptions
{
    case Small = 'small';
    case Large = 'large';

    public function label(): string
    {
        return match ($this) {
            self::Small => 'Small (400px)',
            self::Large => 'Large (1200px)',
        };
    }
}
```

If you declare `->options(ImageSize::class)` fluently _and_ the constructor parameter is typed as a different enum, registration fails: `Component [...] setting [...] declares options for [...], but the constructor expects [...].`

An orphaned stored value (a case removed from the enum after content was authored against it) falls back to the constructor default and logs a warning - uniformly across public rendering, the admin editor, and publishing. There is currently no separate admin-visible warning or publish-time block for this - see [Current limitations](../README.md#current-limitations).

## `LinkValue`

The `Link` field type hydrates to `WearePixel\Inlay\Support\LinkValue`, a plain readonly value object with `label`, `url`, and `openInNewTab`:

```php
use WearePixel\Inlay\Attributes\Link;
use WearePixel\Inlay\Support\LinkValue;

public function __construct(
    #[Link(label: 'Call to action')]
    public ?LinkValue $cta = null,
) {}
```

```blade
@if ($cta)
    <a href="{{ $cta->url }}" @if ($cta->openInNewTab) target="_blank" @endif>{{ $cta->label }}</a>
@endif
```

Only `http://`, `https://`, `mailto:`, and relative (scheme-less) URLs are ever persisted; anything else (e.g. `javascript:...`) is rejected before it reaches storage, at the point an editor saves it - you can render `$cta->url` into an `href` attribute without adding your own scheme check.

## Presentation stays host-owned

Inlay never generates or injects markup into a component's own output - it resolves settings and constructs your component; your component's own Blade view decides everything about structure, classes, and conditional rendering. A setting's value can change _which branch_ your view takes:

```blade
<img
    src="{{ $image }}"
    class="{{ $imageSize === ImageSize::Small ? 'max-w-md' : 'w-full' }}"
>
```

There is no mechanism for an editor to inject arbitrary CSS classes or markup that your component's own view didn't already provide for.

## Nested Blade and Livewire usage

A registered component is a normal Blade class component - it may freely contain Livewire, Alpine, or any other host-owned frontend behaviour in its own view:

```blade
{{-- resources/views/components/contact-section.blade.php --}}
<section>
    <h2>{{ $heading }}</h2>
    <livewire:contact-form />
</section>
```

Inlay renders every component through a genuine, compiled Blade component tag (not manual `new $class(...)` construction and not `<x-dynamic-component>` - see the rendering-mechanism note in `docs/v1-implementation-specification.md` §5 if you want the empirical detail), which is exactly what lets `<livewire:contact-form />` mount and hydrate with zero special handling.

## Single-file components

For a simple component, defining a class and a separate view is more ceremony than the component needs. A single-file component declares everything - handle, metadata, settings - in one Blade file, compiling into the exact same internal `ComponentDefinition` a class + view component resolves to. The registry, the picker, settings resolution, validation, persistence, preview, rendering, publishing, and revisions all treat the two identically; nothing downstream branches on which one produced a given component.

**This is not Livewire Volt.** It borrows the one-file ergonomic, but a single-file component is an ordinary Blade file rendered through Laravel's real view engine - it may contain nested Livewire tags exactly like a class-based component, but it is not itself a Livewire component and does not depend on the Volt package.

```blade
{{-- resources/views/inlay/components/hero.blade.php --}}
<?php

use App\Enums\ImageSize;
use WearePixel\Inlay\Schema\Settings;
use WearePixel\Inlay\SingleFile\Component;

component(
    Component::make('hero')
        ->name('Hero')
        ->category('Headers')
        ->icon('layout-header')
        ->settings(
            Settings::make()
                ->text('heading')->label('Heading')->default('Welcome')
                ->select('imageSize')->label('Image size')->options(ImageSize::class)->default(ImageSize::Large)
        )
);
?>
<section>
    <h1>{{ $heading }}</h1>
    <img class="{{ $imageSize === ImageSize::Small ? 'max-w-md' : 'w-full' }}" src="...">
</section>
```

The PHP block at the top declares the component; everything after the closing `?>` is ordinary Blade markup, rendered with every setting already available as a variable - exactly as `$data` is available in any Blade view.

### No constructor means Settings is authoritative for everything

A single-file component has no constructor, so there is nothing for the settings declaration to be cross-checked against - it must be authoritative on its own for field type, nullability, default, and enum/value-object type. Concretely, this means **`->default(...)` is required on every top-level setting** (unlike a class-based component, where the constructor parameter's own default is authoritative and a schema-level `.default()` is reserved for nested repeater/group fields only). Omitting it fails registration immediately:

```
Single-file component [hero] setting [heading] has no default(). A single-file component has no constructor to infer one from - every top-level setting must declare its own default via ->default(...).
```

Everything else about authoring settings - field types, `visibleWhen()`, enums, `LinkValue`, repeaters/groups - works identically to the fluent `Settings` API documented above.

### Registration

Register a single file explicitly, e.g. from `AppServiceProvider::boot()`:

```php
use WearePixel\Inlay\Facades\Inlay;

Inlay::singleFileComponent(resource_path('views/inlay/components/hero.blade.php'));
```

Or register every `*.blade.php` file directly inside a directory at once:

```php
Inlay::singleFileComponents(resource_path('views/inlay/components'));
```

Directory discovery uses each file's **own declared handle** (from `Component::make('hero')` inside it), not its filename - the filename is purely an organisational convenience for you, not a second source of truth for the handle. A handle already claimed by a class-based component, or by another single-file component, is a registration error rather than a silent override:

```
Cannot register single-file component [hero] - a class-based component is already registered for that handle.
```

### Referencing a single-file component without a class

A future Page Definition's component allow-list needs to name components, but a single-file component has no PHP class to put in a list of `::class` references. `WearePixel\Inlay\Support\ComponentHandle` is a small typed value object for exactly this case, decided now so that whenever Page Definitions ship, there's already a safe, IDE-discoverable way to reference a handle-only component rather than a raw string:

```php
// Illustrative - Page Definitions are not implemented yet.
public function allowedComponents(): array
{
    return [
        Hero::class,
        ComponentHandle::make('promo-banner'),
    ];
}
```

## Common registration errors and their messages

| Situation                                                                                | Exception message                                                                                                                                                                                                                                                 |
| ---------------------------------------------------------------------------------------- | ----------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| A `settings()`/attribute entry names a field with no matching constructor parameter      | `Component [...] declares a setting [...] with no matching constructor parameter.`                                                                                                                                                                                |
| A required, non-nullable constructor parameter has no default                            | `Component [...] setting [...] has no constructor default and is not nullable. A top-level setting must always be constructible with zero overrides - give it a default or make it nullable. A schema-level default is not permitted to substitute for one here.` |
| `->options(EnumClass::class)` doesn't match the constructor parameter's actual enum type | `Component [...] setting [...] declares options for [...], but the constructor expects [...].`                                                                                                                                                                    |
| The same setting name is declared twice in one `Settings` definition                     | `Duplicate setting [...] declared twice in the same settings definition.`                                                                                                                                                                                         |
| A `Settings` modifier is called before any field has been defined                        | `...() must follow a field definition.`                                                                                                                                                                                                                           |
| `->options(...)` is given a class-string that isn't an enum                              | `[...] passed to options() is not an enum.`                                                                                                                                                                                                                       |
| A single-file component's top-level setting has no `->default()`                         | `Single-file component [...] setting [...] has no default(). A single-file component has no constructor to infer one from - every top-level setting must declare its own default via ->default(...).`                                                             |
| A single-file component's Blade file never calls `component(...)`                        | `[...] does not declare a single-file component - it must call component(Component::make(...)) in a PHP block at the top of the file.`                                                                                                                            |
| `Inlay::singleFileComponent()` is given a path with no file                              | `No single-file component found at [...].`                                                                                                                                                                                                                        |
| A single-file handle collides with an already-registered component                       | `Cannot register single-file component [...] - a class-based component is already registered for that handle.` (or `... a single-file component is already registered ...`)                                                                                       |

All of these are raised the moment the component is resolved - the first time it's registered and used, not silently deferred to when an editor happens to touch that field.
