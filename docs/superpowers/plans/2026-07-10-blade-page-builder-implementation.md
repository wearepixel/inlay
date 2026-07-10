# Blade Page Builder Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Build `wearepixel/blade-page-builder`, a Composer package that gives a Laravel app a drag-and-drop, schema-driven page builder for Blade section components, with draft/publish and whole-page version history.

**Architecture:** A standard Laravel package (service provider, migrations, config) providing three layers: (1) a `Schema`/`Field` model that lets a Blade class component declare which of its constructor props are client-editable, (2) `Page`/`PageSection`/`PageRevision` Eloquent models plus `PublishPage`/`RestorePageRevision` actions for persistence and history, (3) a `PageEditor` Livewire component (drag-reorder, field editing, live preview, publish) and a `<x-page-builder::render>` Blade component for the public-facing render. Sections themselves are written by the consuming app, not shipped by the package.

**Tech Stack:** PHP 8.2+, Laravel Illuminate components ^11.0|^12.0, Livewire ^3.0|^4.0, Alpine.js + SortableJS (CDN) for drag interaction, Orchestra Testbench + Pest for package testing.

## Global Constraints

- Package name: `wearepixel/blade-page-builder`, PSR-4 root namespace `WearePixel\BladePageBuilder\`.
- No hard dependency on Filament — must work in any Livewire-only app.
- `declare(strict_types=1);` at the top of every PHP file, typed properties and return types throughout.
- No `dd()`, `dump()`, `ray()`, `var_dump()`, `print_r()` anywhere in shipped code.
- Tests written with Pest, feature-style where the unit under test touches the database or Livewire.
- No magic strings for status values — use backed enums (`PageStatus`, `FieldType`).
- `page_sections` is the single live-editing tree (no parallel draft/published rows). Published state is read from the latest `page_revisions.snapshot`, never from `page_sections` directly, on the public render path.
- Run the `security-reviewer` agent after Task 15 (the package handles rich text and file upload input directly).

---

### Task 1: Package scaffolding & test harness

**Files:**

- Create: `composer.json`
- Create: `phpunit.xml.dist`
- Create: `tests/TestCase.php`
- Create: `tests/Pest.php`
- Create: `config/blade-page-builder.php`
- Create: `src/BladePageBuilderServiceProvider.php`
- Test: `tests/Feature/ServiceProviderTest.php`

**Interfaces:**

- Produces: `WearePixel\BladePageBuilder\BladePageBuilderServiceProvider` (registered in `tests/TestCase.php::getPackageProviders()`), config key `blade-page-builder.sections` (array) and `blade-page-builder.disk` (string, default `'public'`).

- [ ] **Step 1: Create composer.json**

```json
{
  "name": "wearepixel/blade-page-builder",
  "description": "A drag-and-drop, schema-driven page builder engine for Blade components.",
  "type": "library",
  "license": "MIT",
  "require": {
    "php": "^8.2",
    "illuminate/support": "^11.0|^12.0",
    "illuminate/database": "^11.0|^12.0",
    "livewire/livewire": "^3.0|^4.0"
  },
  "require-dev": {
    "orchestra/testbench": "^9.0|^10.0",
    "pestphp/pest": "^3.0",
    "mockery/mockery": "^1.6",
    "laravel/pint": "^1.17"
  },
  "autoload": {
    "psr-4": {
      "WearePixel\\BladePageBuilder\\": "src/"
    }
  },
  "autoload-dev": {
    "psr-4": {
      "WearePixel\\BladePageBuilder\\Tests\\": "tests/"
    }
  },
  "extra": {
    "laravel": {
      "providers": [
        "WearePixel\\BladePageBuilder\\BladePageBuilderServiceProvider"
      ]
    }
  },
  "minimum-stability": "stable",
  "prefer-stable": true
}
```

- [ ] **Step 2: Create phpunit.xml.dist**

```xml
<?xml version="1.0" encoding="UTF-8"?>
<phpunit xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
         xsi:noNamespaceSchemaLocation="vendor/phpunit/phpunit/phpunit.xsd"
         bootstrap="vendor/autoload.php"
         colors="true">
    <testsuites>
        <testsuite name="Unit">
            <directory>tests/Unit</directory>
        </testsuite>
        <testsuite name="Feature">
            <directory>tests/Feature</directory>
        </testsuite>
    </testsuites>
</phpunit>
```

- [ ] **Step 3: Create config/blade-page-builder.php**

```php
<?php

declare(strict_types=1);

return [
    'sections' => [],
    'disk' => 'public',
];
```

- [ ] **Step 4: Create src/BladePageBuilderServiceProvider.php**

```php
<?php

declare(strict_types=1);

namespace WearePixel\BladePageBuilder;

use Illuminate\Support\ServiceProvider;

final class BladePageBuilderServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/blade-page-builder.php', 'blade-page-builder');
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
        $this->loadViewsFrom(__DIR__.'/../resources/views', 'blade-page-builder');

        $this->publishes([
            __DIR__.'/../config/blade-page-builder.php' => config_path('blade-page-builder.php'),
        ], 'blade-page-builder-config');
    }
}
```

- [ ] **Step 5: Create tests/TestCase.php**

```php
<?php

declare(strict_types=1);

namespace WearePixel\BladePageBuilder\Tests;

use Orchestra\Testbench\TestCase as Orchestra;
use WearePixel\BladePageBuilder\BladePageBuilderServiceProvider;

abstract class TestCase extends Orchestra
{
    /**
     * @return array<int, class-string>
     */
    protected function getPackageProviders($app): array
    {
        return [
            \Livewire\LivewireServiceProvider::class,
            BladePageBuilderServiceProvider::class,
        ];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('database.default', 'testing');
        $app['config']->set('database.connections.testing', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);
    }
}
```

- [ ] **Step 6: Create tests/Pest.php**

```php
<?php

declare(strict_types=1);

use WearePixel\BladePageBuilder\Tests\TestCase;

uses(TestCase::class)->in('Feature', 'Unit');
```

- [ ] **Step 7: Write the failing test**

```php
<?php

declare(strict_types=1);

it('merges the package config', function () {
    expect(config('blade-page-builder.disk'))->toBe('public')
        ->and(config('blade-page-builder.sections'))->toBe([]);
});
```

Save as `tests/Feature/ServiceProviderTest.php`.

- [ ] **Step 8: Install dependencies and run the test**

Run: `composer install && vendor/bin/pest tests/Feature/ServiceProviderTest.php`
Expected: PASS (config merging is already wired in Step 4, so this confirms the harness itself works end to end).

- [ ] **Step 9: Commit**

```bash
git add composer.json phpunit.xml.dist config src tests
git commit -m "chore: scaffold package with test harness"
```

---

### Task 2: Migrations & PageStatus enum

**Files:**

- Create: `src/Enums/PageStatus.php`
- Create: `database/migrations/2025_01_01_000001_create_pages_table.php`
- Create: `database/migrations/2025_01_01_000002_create_page_sections_table.php`
- Create: `database/migrations/2025_01_01_000003_create_page_revisions_table.php`
- Test: `tests/Feature/MigrationsTest.php`

**Interfaces:**

- Produces: `WearePixel\BladePageBuilder\Enums\PageStatus` (`Draft = 'draft'`, `Published = 'published'`); tables `pages`, `page_sections`, `page_revisions`.

- [ ] **Step 1: Write the failing test**

```php
<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Schema;

it('creates the pages, page_sections, and page_revisions tables', function () {
    expect(Schema::hasTable('pages'))->toBeTrue()
        ->and(Schema::hasColumns('pages', ['title', 'slug', 'status', 'meta_title', 'meta_description']))->toBeTrue()
        ->and(Schema::hasTable('page_sections'))->toBeTrue()
        ->and(Schema::hasColumns('page_sections', ['page_id', 'section_type', 'order', 'props']))->toBeTrue()
        ->and(Schema::hasTable('page_revisions'))->toBeTrue()
        ->and(Schema::hasColumns('page_revisions', ['page_id', 'snapshot', 'created_by', 'label']))->toBeTrue();
});
```

Save as `tests/Feature/MigrationsTest.php`.

- [ ] **Step 2: Run test to verify it fails**

Run: `vendor/bin/pest tests/Feature/MigrationsTest.php`
Expected: FAIL — tables do not exist yet.

- [ ] **Step 3: Create src/Enums/PageStatus.php**

```php
<?php

declare(strict_types=1);

namespace WearePixel\BladePageBuilder\Enums;

enum PageStatus: string
{
    case Draft = 'draft';
    case Published = 'published';
}
```

- [ ] **Step 4: Create the pages migration**

```php
<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pages', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->string('slug')->unique();
            $table->string('status')->default('draft');
            $table->string('meta_title')->nullable();
            $table->text('meta_description')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pages');
    }
};
```

- [ ] **Step 5: Create the page_sections migration**

```php
<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('page_sections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('page_id')->constrained()->cascadeOnDelete();
            $table->string('section_type');
            $table->unsignedInteger('order')->default(0);
            $table->json('props')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('page_sections');
    }
};
```

- [ ] **Step 6: Create the page_revisions migration**

```php
<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('page_revisions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('page_id')->constrained()->cascadeOnDelete();
            $table->json('snapshot');
            $table->unsignedBigInteger('created_by')->nullable();
            $table->string('label')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('page_revisions');
    }
};
```

`created_by` is intentionally not a foreign key — the package doesn't assume a specific `users` table shape in the consuming app.

- [ ] **Step 7: Run test to verify it passes**

Run: `vendor/bin/pest tests/Feature/MigrationsTest.php`
Expected: PASS

- [ ] **Step 8: Commit**

```bash
git add src/Enums database/migrations tests/Feature/MigrationsTest.php
git commit -m "feat: add pages, page_sections, and page_revisions migrations"
```

---

### Task 3: Eloquent models

**Files:**

- Create: `src/Models/Page.php`
- Create: `src/Models/PageSection.php`
- Create: `src/Models/PageRevision.php`
- Test: `tests/Feature/ModelsTest.php`

**Interfaces:**

- Consumes: `PageStatus` enum (Task 2), `pages`/`page_sections`/`page_revisions` tables (Task 2).
- Produces: `Page::sections(): HasMany`, `Page::revisions(): HasMany`, `Page::isPublished(): bool`; `PageSection::page(): BelongsTo`; `PageRevision::page(): BelongsTo`. All three are `App\Models`-style plain Eloquent models under `WearePixel\BladePageBuilder\Models`.

- [ ] **Step 1: Write the failing test**

```php
<?php

declare(strict_types=1);

use WearePixel\BladePageBuilder\Enums\PageStatus;
use WearePixel\BladePageBuilder\Models\Page;
use WearePixel\BladePageBuilder\Models\PageRevision;
use WearePixel\BladePageBuilder\Models\PageSection;

it('relates pages to sections and revisions, and casts props/snapshot as arrays', function () {
    $page = Page::create(['title' => 'Home', 'slug' => 'home']);

    $section = $page->sections()->create([
        'section_type' => 'test-hero',
        'order' => 0,
        'props' => ['heading' => 'Welcome'],
    ]);

    $revision = $page->revisions()->create([
        'snapshot' => [['section_type' => 'test-hero', 'order' => 0, 'props' => ['heading' => 'Welcome']]],
    ]);

    expect($page->status)->toBe(PageStatus::Draft)
        ->and($page->isPublished())->toBeFalse()
        ->and($page->sections()->first()->id)->toBe($section->id)
        ->and($section->props)->toBe(['heading' => 'Welcome'])
        ->and($page->revisions()->first()->id)->toBe($revision->id)
        ->and($revision->snapshot)->toBeArray()
        ->and($section->page->id)->toBe($page->id)
        ->and($revision->page->id)->toBe($page->id);
});
```

Save as `tests/Feature/ModelsTest.php`.

- [ ] **Step 2: Run test to verify it fails**

Run: `vendor/bin/pest tests/Feature/ModelsTest.php`
Expected: FAIL — model classes do not exist yet.

- [ ] **Step 3: Create src/Models/Page.php**

```php
<?php

declare(strict_types=1);

namespace WearePixel\BladePageBuilder\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use WearePixel\BladePageBuilder\Enums\PageStatus;

final class Page extends Model
{
    protected $fillable = ['title', 'slug', 'status', 'meta_title', 'meta_description'];

    protected $casts = [
        'status' => PageStatus::class,
    ];

    protected $attributes = [
        'status' => 'draft',
    ];

    public function sections(): HasMany
    {
        return $this->hasMany(PageSection::class)->orderBy('order');
    }

    public function revisions(): HasMany
    {
        return $this->hasMany(PageRevision::class)->latest();
    }

    public function isPublished(): bool
    {
        return $this->status === PageStatus::Published;
    }
}
```

- [ ] **Step 4: Create src/Models/PageSection.php**

```php
<?php

declare(strict_types=1);

namespace WearePixel\BladePageBuilder\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class PageSection extends Model
{
    protected $fillable = ['page_id', 'section_type', 'order', 'props'];

    protected $casts = [
        'order' => 'integer',
        'props' => 'array',
    ];

    public function page(): BelongsTo
    {
        return $this->belongsTo(Page::class);
    }
}
```

- [ ] **Step 5: Create src/Models/PageRevision.php**

```php
<?php

declare(strict_types=1);

namespace WearePixel\BladePageBuilder\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class PageRevision extends Model
{
    protected $fillable = ['page_id', 'snapshot', 'created_by', 'label'];

    protected $casts = [
        'snapshot' => 'array',
    ];

    public function page(): BelongsTo
    {
        return $this->belongsTo(Page::class);
    }
}
```

- [ ] **Step 6: Run test to verify it passes**

Run: `vendor/bin/pest tests/Feature/ModelsTest.php`
Expected: PASS

- [ ] **Step 7: Commit**

```bash
git add src/Models tests/Feature/ModelsTest.php
git commit -m "feat: add Page, PageSection, and PageRevision models"
```

---

### Task 4: FieldType enum, Field value object, Schema builder

**Files:**

- Create: `src/Schema/FieldType.php`
- Create: `src/Schema/Field.php`
- Create: `src/Schema/Schema.php`
- Test: `tests/Unit/SchemaTest.php`

**Interfaces:**

- Produces: `FieldType` enum (`Text`, `Textarea`, `Richtext`, `Image`, `Select`, `Link`, `Repeater`, `Group`); `Field` (readonly `name`, `type`, `label`, `options` array, mutable `?Closure $classResolver`, `?Schema $itemSchema`, method `resolveClass(mixed $value): ?string`); `Schema` (`make(): self`, `text/textarea/richtext/image/link(string $name, string $label): self`, `select(string $name, string $label, array $options): self`, `repeater(string $name, string $label, Schema $itemSchema): self`, `group(string $name, string $label, Schema $itemSchema): self`, `mapsToClass(Closure $resolver): self`, `field(string $name): ?Field`, `fields(): array<Field>`).

- [ ] **Step 1: Write the failing test**

```php
<?php

declare(strict_types=1);

use WearePixel\BladePageBuilder\Schema\FieldType;
use WearePixel\BladePageBuilder\Schema\Schema;

it('builds a schema of fields including a class-mapped select', function () {
    $schema = Schema::make()
        ->text('heading', label: 'Heading')
        ->richtext('body', label: 'Body copy')
        ->image('photo', label: 'Photo')
        ->select('layout', label: 'Layout', options: [
            'image-left' => 'Image on left',
            'image-right' => 'Image on right',
        ])->mapsToClass(fn (string $value) => $value === 'image-left' ? 'flex-row-reverse' : 'flex-row');

    expect($schema->fields())->toHaveCount(4);

    $heading = $schema->field('heading');
    expect($heading->name)->toBe('heading')
        ->and($heading->type)->toBe(FieldType::Text)
        ->and($heading->label)->toBe('Heading');

    $layout = $schema->field('layout');
    expect($layout->type)->toBe(FieldType::Select)
        ->and($layout->options['options'])->toBe([
            'image-left' => 'Image on left',
            'image-right' => 'Image on right',
        ])
        ->and($layout->resolveClass('image-left'))->toBe('flex-row-reverse')
        ->and($layout->resolveClass('image-right'))->toBe('flex-row');

    expect($schema->field('missing'))->toBeNull();
});

it('nests a schema inside a repeater field', function () {
    $itemSchema = Schema::make()->text('question', label: 'Question')->textarea('answer', label: 'Answer');

    $schema = Schema::make()->repeater('faqs', label: 'FAQs', itemSchema: $itemSchema);

    $faqs = $schema->field('faqs');

    expect($faqs->type)->toBe(FieldType::Repeater)
        ->and($faqs->itemSchema)->toBe($itemSchema)
        ->and($faqs->itemSchema->fields())->toHaveCount(2);
});
```

Save as `tests/Unit/SchemaTest.php`.

- [ ] **Step 2: Run test to verify it fails**

Run: `vendor/bin/pest tests/Unit/SchemaTest.php`
Expected: FAIL — classes do not exist yet.

- [ ] **Step 3: Create src/Schema/FieldType.php**

```php
<?php

declare(strict_types=1);

namespace WearePixel\BladePageBuilder\Schema;

enum FieldType: string
{
    case Text = 'text';
    case Textarea = 'textarea';
    case Richtext = 'richtext';
    case Image = 'image';
    case Select = 'select';
    case Link = 'link';
    case Repeater = 'repeater';
    case Group = 'group';
}
```

- [ ] **Step 4: Create src/Schema/Field.php**

```php
<?php

declare(strict_types=1);

namespace WearePixel\BladePageBuilder\Schema;

use Closure;

final class Field
{
    public ?Closure $classResolver = null;

    /**
     * @param array<string, mixed> $options
     */
    public function __construct(
        public readonly string $name,
        public readonly FieldType $type,
        public readonly string $label,
        public readonly array $options = [],
        public readonly ?Schema $itemSchema = null,
    ) {}

    public function resolveClass(mixed $value): ?string
    {
        return $this->classResolver ? ($this->classResolver)($value) : null;
    }
}
```

- [ ] **Step 5: Create src/Schema/Schema.php**

```php
<?php

declare(strict_types=1);

namespace WearePixel\BladePageBuilder\Schema;

use Closure;
use LogicException;

final class Schema
{
    /** @var array<int, Field> */
    private array $fields = [];

    private ?Field $lastField = null;

    public static function make(): self
    {
        return new self();
    }

    public function text(string $name, string $label): self
    {
        return $this->addField($name, FieldType::Text, $label);
    }

    public function textarea(string $name, string $label): self
    {
        return $this->addField($name, FieldType::Textarea, $label);
    }

    public function richtext(string $name, string $label): self
    {
        return $this->addField($name, FieldType::Richtext, $label);
    }

    public function image(string $name, string $label): self
    {
        return $this->addField($name, FieldType::Image, $label);
    }

    public function link(string $name, string $label): self
    {
        return $this->addField($name, FieldType::Link, $label);
    }

    /**
     * @param array<string, string> $options
     */
    public function select(string $name, string $label, array $options): self
    {
        return $this->addField($name, FieldType::Select, $label, ['options' => $options]);
    }

    public function repeater(string $name, string $label, Schema $itemSchema): self
    {
        return $this->addField($name, FieldType::Repeater, $label, itemSchema: $itemSchema);
    }

    public function group(string $name, string $label, Schema $itemSchema): self
    {
        return $this->addField($name, FieldType::Group, $label, itemSchema: $itemSchema);
    }

    public function mapsToClass(Closure $resolver): self
    {
        if (! $this->lastField) {
            throw new LogicException('mapsToClass() must follow a field definition.');
        }

        $this->lastField->classResolver = $resolver;

        return $this;
    }

    public function field(string $name): ?Field
    {
        foreach ($this->fields as $field) {
            if ($field->name === $name) {
                return $field;
            }
        }

        return null;
    }

    /**
     * @return array<int, Field>
     */
    public function fields(): array
    {
        return $this->fields;
    }

    /**
     * @param array<string, mixed> $options
     */
    private function addField(string $name, FieldType $type, string $label, array $options = [], ?Schema $itemSchema = null): self
    {
        $field = new Field($name, $type, $label, $options, $itemSchema);

        $this->fields[] = $field;
        $this->lastField = $field;

        return $this;
    }
}
```

- [ ] **Step 6: Run test to verify it passes**

Run: `vendor/bin/pest tests/Unit/SchemaTest.php`
Expected: PASS

- [ ] **Step 7: Commit**

```bash
git add src/Schema tests/Unit/SchemaTest.php
git commit -m "feat: add Schema, Field, and FieldType for section field declarations"
```

---

### Task 5: Sectionable contract, SectionRegistry, test fixture section

**Files:**

- Create: `src/Contracts/Sectionable.php`
- Create: `src/Support/SectionRegistry.php`
- Create: `tests/Fixtures/TestHeroSection.php`
- Create: `tests/Fixtures/views/test-hero.blade.php`
- Modify: `tests/TestCase.php` (register the fixture view path and section)
- Test: `tests/Unit/SectionRegistryTest.php`

**Interfaces:**

- Consumes: `Schema` (Task 4).
- Produces: `Sectionable` interface (`schema(): Schema`); `SectionRegistry` (`__construct(array $sections)`, `resolve(string $slug): class-string<Sectionable>`, `slugs(): array<string>`); `TestHeroSection` fixture registered under slug `test-hero`.

- [ ] **Step 1: Write the failing test**

```php
<?php

declare(strict_types=1);

use WearePixel\BladePageBuilder\Support\SectionRegistry;
use WearePixel\BladePageBuilder\Tests\Fixtures\TestHeroSection;

it('resolves a registered slug to its section class', function () {
    $registry = new SectionRegistry(['test-hero' => TestHeroSection::class]);

    expect($registry->resolve('test-hero'))->toBe(TestHeroSection::class)
        ->and($registry->slugs())->toBe(['test-hero']);
});

it('throws for an unregistered slug', function () {
    $registry = new SectionRegistry([]);

    $registry->resolve('missing');
})->throws(InvalidArgumentException::class, 'No section registered for slug [missing].');
```

Save as `tests/Unit/SectionRegistryTest.php`.

- [ ] **Step 2: Run test to verify it fails**

Run: `vendor/bin/pest tests/Unit/SectionRegistryTest.php`
Expected: FAIL — classes do not exist yet.

- [ ] **Step 3: Create src/Contracts/Sectionable.php**

```php
<?php

declare(strict_types=1);

namespace WearePixel\BladePageBuilder\Contracts;

use WearePixel\BladePageBuilder\Schema\Schema;

interface Sectionable
{
    public static function schema(): Schema;
}
```

- [ ] **Step 4: Create src/Support/SectionRegistry.php**

```php
<?php

declare(strict_types=1);

namespace WearePixel\BladePageBuilder\Support;

use InvalidArgumentException;
use WearePixel\BladePageBuilder\Contracts\Sectionable;

final class SectionRegistry
{
    /**
     * @param array<string, class-string<Sectionable>> $sections
     */
    public function __construct(private readonly array $sections) {}

    public static function fromConfig(): self
    {
        return new self(config('blade-page-builder.sections', []));
    }

    /**
     * @return class-string<Sectionable>
     */
    public function resolve(string $slug): string
    {
        if (! isset($this->sections[$slug])) {
            throw new InvalidArgumentException("No section registered for slug [{$slug}].");
        }

        return $this->sections[$slug];
    }

    /**
     * @return array<int, string>
     */
    public function slugs(): array
    {
        return array_keys($this->sections);
    }
}
```

- [ ] **Step 5: Create tests/Fixtures/TestHeroSection.php**

```php
<?php

declare(strict_types=1);

namespace WearePixel\BladePageBuilder\Tests\Fixtures;

use Illuminate\View\Component;
use Illuminate\View\View;
use WearePixel\BladePageBuilder\Contracts\Sectionable;
use WearePixel\BladePageBuilder\Schema\Schema;

final class TestHeroSection extends Component implements Sectionable
{
    public function __construct(
        public string $heading = '',
        public string $layout = 'image-right',
    ) {}

    public static function schema(): Schema
    {
        return Schema::make()
            ->text('heading', label: 'Heading')
            ->select('layout', label: 'Layout', options: [
                'image-left' => 'Image on left',
                'image-right' => 'Image on right',
            ])->mapsToClass(fn (string $value) => $value === 'image-left' ? 'flex-row-reverse' : 'flex-row');
    }

    public function with(): array
    {
        return [
            'layoutClass' => static::schema()->field('layout')?->resolveClass($this->layout),
        ];
    }

    public function render(): View
    {
        return view('test-hero');
    }
}
```

- [ ] **Step 6: Create tests/Fixtures/views/test-hero.blade.php**

```blade
<div class="{{ $layoutClass }}">
    <h1>{{ $heading }}</h1>
</div>
```

- [ ] **Step 7: Register the fixture view path in tests/TestCase.php**

Add to `defineEnvironment()`, after the database config:

```php
        $app['view']->addLocation(__DIR__.'/Fixtures/views');
```

- [ ] **Step 8: Run test to verify it passes**

Run: `vendor/bin/pest tests/Unit/SectionRegistryTest.php`
Expected: PASS

- [ ] **Step 9: Commit**

```bash
git add src/Contracts src/Support tests/Fixtures tests/TestCase.php tests/Unit/SectionRegistryTest.php
git commit -m "feat: add Sectionable contract, SectionRegistry, and test fixture section"
```

---

### Task 6: PublishPage action

**Files:**

- Create: `src/Actions/PublishPage.php`
- Test: `tests/Feature/PublishPageTest.php`

**Interfaces:**

- Consumes: `Page`, `PageSection`, `PageRevision` models (Task 3), `PageStatus` enum (Task 2).
- Produces: `PublishPage::execute(Page $page, ?int $createdBy = null, ?string $label = null): PageRevision`.

- [ ] **Step 1: Write the failing test**

```php
<?php

declare(strict_types=1);

use WearePixel\BladePageBuilder\Actions\PublishPage;
use WearePixel\BladePageBuilder\Enums\PageStatus;
use WearePixel\BladePageBuilder\Models\Page;

it('snapshots the current sections into a revision and marks the page published', function () {
    $page = Page::create(['title' => 'Home', 'slug' => 'home']);
    $page->sections()->create(['section_type' => 'test-hero', 'order' => 0, 'props' => ['heading' => 'Hi']]);
    $page->sections()->create(['section_type' => 'test-hero', 'order' => 1, 'props' => ['heading' => 'Bye']]);

    $revision = (new PublishPage())->execute($page, createdBy: 7, label: 'Initial publish');

    expect($revision->page_id)->toBe($page->id)
        ->and($revision->created_by)->toBe(7)
        ->and($revision->label)->toBe('Initial publish')
        ->and($revision->snapshot)->toBe([
            ['section_type' => 'test-hero', 'order' => 0, 'props' => ['heading' => 'Hi']],
            ['section_type' => 'test-hero', 'order' => 1, 'props' => ['heading' => 'Bye']],
        ])
        ->and($page->fresh()->status)->toBe(PageStatus::Published);
});
```

Save as `tests/Feature/PublishPageTest.php`.

- [ ] **Step 2: Run test to verify it fails**

Run: `vendor/bin/pest tests/Feature/PublishPageTest.php`
Expected: FAIL — `PublishPage` does not exist yet.

- [ ] **Step 3: Create src/Actions/PublishPage.php**

```php
<?php

declare(strict_types=1);

namespace WearePixel\BladePageBuilder\Actions;

use Illuminate\Support\Facades\DB;
use WearePixel\BladePageBuilder\Enums\PageStatus;
use WearePixel\BladePageBuilder\Models\Page;
use WearePixel\BladePageBuilder\Models\PageRevision;
use WearePixel\BladePageBuilder\Models\PageSection;

final class PublishPage
{
    public function execute(Page $page, ?int $createdBy = null, ?string $label = null): PageRevision
    {
        return DB::transaction(function () use ($page, $createdBy, $label) {
            $snapshot = $page->sections()
                ->orderBy('order')
                ->get()
                ->map(fn (PageSection $section) => [
                    'section_type' => $section->section_type,
                    'order' => $section->order,
                    'props' => $section->props,
                ])
                ->all();

            $revision = $page->revisions()->create([
                'snapshot' => $snapshot,
                'created_by' => $createdBy,
                'label' => $label,
            ]);

            $page->update(['status' => PageStatus::Published]);

            return $revision;
        });
    }
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `vendor/bin/pest tests/Feature/PublishPageTest.php`
Expected: PASS

- [ ] **Step 5: Commit**

```bash
git add src/Actions/PublishPage.php tests/Feature/PublishPageTest.php
git commit -m "feat: add PublishPage action with whole-page snapshotting"
```

---

### Task 7: RestorePageRevision action

**Files:**

- Create: `src/Actions/RestorePageRevision.php`
- Test: `tests/Feature/RestorePageRevisionTest.php`

**Interfaces:**

- Consumes: `PublishPage::execute()` (Task 6), `PageRevision`/`Page`/`PageSection` models (Task 3).
- Produces: `RestorePageRevision::execute(PageRevision $revision, ?int $createdBy = null): PageRevision`.

- [ ] **Step 1: Write the failing test**

```php
<?php

declare(strict_types=1);

use WearePixel\BladePageBuilder\Actions\PublishPage;
use WearePixel\BladePageBuilder\Actions\RestorePageRevision;
use WearePixel\BladePageBuilder\Models\Page;

it('overwrites live sections from a revision snapshot and creates a new revision', function () {
    $page = Page::create(['title' => 'Home', 'slug' => 'home']);
    $page->sections()->create(['section_type' => 'test-hero', 'order' => 0, 'props' => ['heading' => 'Original']]);

    $original = (new PublishPage())->execute($page);

    $page->sections()->first()->update(['props' => ['heading' => 'Edited']]);
    (new PublishPage())->execute($page);

    expect($page->revisions()->count())->toBe(2);

    $restored = (new RestorePageRevision(new PublishPage()))->execute($original, createdBy: 3);

    expect($page->sections()->first()->props)->toBe(['heading' => 'Original'])
        ->and($page->revisions()->count())->toBe(3)
        ->and($restored->label)->toBe("Restored from revision #{$original->id}")
        ->and($restored->created_by)->toBe(3);
});
```

Save as `tests/Feature/RestorePageRevisionTest.php`.

- [ ] **Step 2: Run test to verify it fails**

Run: `vendor/bin/pest tests/Feature/RestorePageRevisionTest.php`
Expected: FAIL — `RestorePageRevision` does not exist yet.

- [ ] **Step 3: Create src/Actions/RestorePageRevision.php**

```php
<?php

declare(strict_types=1);

namespace WearePixel\BladePageBuilder\Actions;

use Illuminate\Support\Facades\DB;
use WearePixel\BladePageBuilder\Models\PageRevision;

final class RestorePageRevision
{
    public function __construct(private readonly PublishPage $publishPage) {}

    public function execute(PageRevision $revision, ?int $createdBy = null): PageRevision
    {
        return DB::transaction(function () use ($revision, $createdBy) {
            $page = $revision->page;

            $page->sections()->delete();

            foreach ($revision->snapshot as $sectionData) {
                $page->sections()->create($sectionData);
            }

            return $this->publishPage->execute(
                $page,
                createdBy: $createdBy,
                label: "Restored from revision #{$revision->id}",
            );
        });
    }
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `vendor/bin/pest tests/Feature/RestorePageRevisionTest.php`
Expected: PASS

- [ ] **Step 5: Commit**

```bash
git add src/Actions/RestorePageRevision.php tests/Feature/RestorePageRevisionTest.php
git commit -m "feat: add RestorePageRevision action"
```

---

### Task 8: SectionRenderer

**Files:**

- Create: `src/Support/SectionRenderer.php`
- Test: `tests/Feature/SectionRendererTest.php`

**Interfaces:**

- Consumes: `SectionRegistry::resolve()` (Task 5), `Sectionable` fixture (Task 5).
- Produces: `SectionRenderer::__construct(SectionRegistry $registry)`, `SectionRenderer::render(iterable $sections): string` where each `$sections` item is `['section_type' => string, 'props' => array]`.

- [ ] **Step 1: Write the failing test**

```php
<?php

declare(strict_types=1);

use WearePixel\BladePageBuilder\Support\SectionRegistry;
use WearePixel\BladePageBuilder\Support\SectionRenderer;
use WearePixel\BladePageBuilder\Tests\Fixtures\TestHeroSection;

it('renders each section through its own Blade view with resolved class mapping', function () {
    $renderer = new SectionRenderer(new SectionRegistry(['test-hero' => TestHeroSection::class]));

    $html = $renderer->render([
        ['section_type' => 'test-hero', 'props' => ['heading' => 'Welcome', 'layout' => 'image-left']],
        ['section_type' => 'test-hero', 'props' => ['heading' => 'Bye', 'layout' => 'image-right']],
    ]);

    expect($html)
        ->toContain('Welcome')
        ->toContain('flex-row-reverse')
        ->toContain('Bye')
        ->toContain('flex-row"');
});
```

Save as `tests/Feature/SectionRendererTest.php`.

- [ ] **Step 2: Run test to verify it fails**

Run: `vendor/bin/pest tests/Feature/SectionRendererTest.php`
Expected: FAIL — `SectionRenderer` does not exist yet.

- [ ] **Step 3: Create src/Support/SectionRenderer.php**

```php
<?php

declare(strict_types=1);

namespace WearePixel\BladePageBuilder\Support;

final class SectionRenderer
{
    public function __construct(private readonly SectionRegistry $registry) {}

    /**
     * @param iterable<array{section_type: string, props: array<string, mixed>}> $sections
     */
    public function render(iterable $sections): string
    {
        $html = '';

        foreach ($sections as $section) {
            $componentClass = $this->registry->resolve($section['section_type']);

            /** @var \Illuminate\View\Component $component */
            $component = new $componentClass(...$section['props']);

            $data = array_merge(
                get_object_vars($component),
                method_exists($component, 'with') ? $component->with() : [],
            );

            $html .= $component->render()->with($data)->render();
        }

        return $html;
    }
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `vendor/bin/pest tests/Feature/SectionRendererTest.php`
Expected: PASS

- [ ] **Step 5: Commit**

```bash
git add src/Support/SectionRenderer.php tests/Feature/SectionRendererTest.php
git commit -m "feat: add SectionRenderer for rendering section trees to HTML"
```

---

### Task 9: Live-site rendering component (`<x-page-builder::render>`)

**Files:**

- Create: `src/View/Components/RenderPage.php`
- Modify: `src/BladePageBuilderServiceProvider.php` (register the component)
- Test: `tests/Feature/RenderPageComponentTest.php`

**Interfaces:**

- Consumes: `SectionRenderer` (Task 8), `Page::revisions()` (Task 3).
- Produces: Blade tag `<x-page-builder::render :page="$page" />`, rendering the page's **latest published revision** — never the live `page_sections` draft.

- [ ] **Step 1: Write the failing test**

```php
<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Blade;
use WearePixel\BladePageBuilder\Actions\PublishPage;
use WearePixel\BladePageBuilder\Models\Page;

beforeEach(function () {
    config(['blade-page-builder.sections' => [
        'test-hero' => \WearePixel\BladePageBuilder\Tests\Fixtures\TestHeroSection::class,
    ]]);
});

it('renders the latest published revision, ignoring unpublished draft edits', function () {
    $page = Page::create(['title' => 'Home', 'slug' => 'home']);
    $page->sections()->create(['section_type' => 'test-hero', 'order' => 0, 'props' => ['heading' => 'Published heading']]);
    (new PublishPage())->execute($page);

    $page->sections()->first()->update(['props' => ['heading' => 'Unpublished draft edit']]);

    $html = Blade::render('<x-page-builder::render :page="$page" />', ['page' => $page]);

    expect($html)
        ->toContain('Published heading')
        ->not->toContain('Unpublished draft edit');
});

it('renders nothing for a page that has never been published', function () {
    $page = Page::create(['title' => 'Home', 'slug' => 'home']);

    $html = Blade::render('<x-page-builder::render :page="$page" />', ['page' => $page]);

    expect(trim($html))->toBe('');
});
```

Save as `tests/Feature/RenderPageComponentTest.php`.

- [ ] **Step 2: Run test to verify it fails**

Run: `vendor/bin/pest tests/Feature/RenderPageComponentTest.php`
Expected: FAIL — the `page-builder::render` tag is not registered yet.

- [ ] **Step 3: Create src/View/Components/RenderPage.php**

```php
<?php

declare(strict_types=1);

namespace WearePixel\BladePageBuilder\View\Components;

use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\HtmlString;
use Illuminate\View\Component;
use WearePixel\BladePageBuilder\Models\Page;
use WearePixel\BladePageBuilder\Support\SectionRenderer;

final class RenderPage extends Component
{
    private HtmlString $html;

    public function __construct(Page $page, SectionRenderer $renderer)
    {
        $revision = $page->revisions()->first();

        $sections = $revision ? $revision->snapshot : [];

        $this->html = new HtmlString($renderer->render($sections));
    }

    public function render(): Htmlable
    {
        return $this->html;
    }
}
```

Returning `Htmlable` directly (rather than a `'{{ $html }}'` string) sidesteps Laravel's inline-template-vs-view-name heuristic for `Component::render()` — a bare `{{ $html }}` string has no `<` or newline, so it would be resolved as a _view name_ lookup and throw a view-not-found error instead of rendering.

- [ ] **Step 4: Register the component in the service provider**

In `src/BladePageBuilderServiceProvider.php`, add the import:

```php
use Illuminate\Support\Facades\Blade;
use WearePixel\BladePageBuilder\View\Components\RenderPage;
```

and add to the end of `boot()`:

```php
        Blade::component(RenderPage::class, 'render', 'page-builder');
```

- [ ] **Step 5: Run test to verify it passes**

Run: `vendor/bin/pest tests/Feature/RenderPageComponentTest.php`
Expected: PASS

- [ ] **Step 6: Commit**

```bash
git add src/View src/BladePageBuilderServiceProvider.php tests/Feature/RenderPageComponentTest.php
git commit -m "feat: add x-page-builder::render component for public rendering"
```

---

### Task 10: PageEditor Livewire component — core

**Files:**

- Create: `src/Livewire/PageEditor.php`
- Create: `resources/views/livewire/page-editor.blade.php`
- Modify: `src/BladePageBuilderServiceProvider.php` (register the Livewire component)
- Test: `tests/Feature/PageEditorTest.php`

**Interfaces:**

- Consumes: `Page`/`PageSection` models (Task 3), `SectionRegistry` (Task 5), `SectionRenderer` (Task 8), `PublishPage` (Task 6).
- Produces: `PageEditor` Livewire component with public `Page $page`, `?int $selectedSectionId`, `array $editingProps`; methods `updateOrder(array $orderedIds)`, `addSection(string $slug)`, `selectSection(int $sectionId)`, `updatedEditingProps()`, `publish()`. Registered as `<livewire:page-builder::page-editor :page="$page" />`.

- [ ] **Step 1: Write the failing test**

```php
<?php

declare(strict_types=1);

use Livewire\Livewire;
use WearePixel\BladePageBuilder\Enums\PageStatus;
use WearePixel\BladePageBuilder\Livewire\PageEditor;
use WearePixel\BladePageBuilder\Models\Page;

beforeEach(function () {
    config(['blade-page-builder.sections' => [
        'test-hero' => \WearePixel\BladePageBuilder\Tests\Fixtures\TestHeroSection::class,
    ]]);
});

it('adds a section with default props derived from its schema', function () {
    $page = Page::create(['title' => 'Home', 'slug' => 'home']);

    Livewire::test(PageEditor::class, ['page' => $page])
        ->call('addSection', 'test-hero')
        ->assertOk();

    $section = $page->sections()->first();

    expect($section->section_type)->toBe('test-hero')
        ->and($section->props)->toBe(['heading' => '', 'layout' => '']);
});

it('persists drag-reordering', function () {
    $page = Page::create(['title' => 'Home', 'slug' => 'home']);
    $first = $page->sections()->create(['section_type' => 'test-hero', 'order' => 0, 'props' => []]);
    $second = $page->sections()->create(['section_type' => 'test-hero', 'order' => 1, 'props' => []]);

    Livewire::test(PageEditor::class, ['page' => $page])
        ->call('updateOrder', [$second->id, $first->id])
        ->assertOk();

    expect($first->fresh()->order)->toBe(1)
        ->and($second->fresh()->order)->toBe(0);
});

it('loads a selected section into editingProps and persists edits', function () {
    $page = Page::create(['title' => 'Home', 'slug' => 'home']);
    $section = $page->sections()->create([
        'section_type' => 'test-hero',
        'order' => 0,
        'props' => ['heading' => 'Old', 'layout' => 'image-right'],
    ]);

    Livewire::test(PageEditor::class, ['page' => $page])
        ->call('selectSection', $section->id)
        ->assertSet('editingProps', ['heading' => 'Old', 'layout' => 'image-right'])
        ->set('editingProps.heading', 'New heading')
        ->assertOk();

    expect($section->fresh()->props['heading'])->toBe('New heading');
});

it('publishes the page', function () {
    $page = Page::create(['title' => 'Home', 'slug' => 'home']);
    $page->sections()->create(['section_type' => 'test-hero', 'order' => 0, 'props' => ['heading' => 'Hi', 'layout' => 'image-right']]);

    Livewire::test(PageEditor::class, ['page' => $page])
        ->call('publish')
        ->assertDispatched('page-published');

    expect($page->fresh()->status)->toBe(PageStatus::Published)
        ->and($page->revisions()->count())->toBe(1);
});
```

Save as `tests/Feature/PageEditorTest.php`.

- [ ] **Step 2: Run test to verify it fails**

Run: `vendor/bin/pest tests/Feature/PageEditorTest.php`
Expected: FAIL — `PageEditor` does not exist yet.

- [ ] **Step 3: Create src/Livewire/PageEditor.php**

```php
<?php

declare(strict_types=1);

namespace WearePixel\BladePageBuilder\Livewire;

use Illuminate\Contracts\View\View;
use Illuminate\Support\HtmlString;
use Livewire\Component;
use WearePixel\BladePageBuilder\Actions\PublishPage;
use WearePixel\BladePageBuilder\Models\Page;
use WearePixel\BladePageBuilder\Models\PageSection;
use WearePixel\BladePageBuilder\Schema\Schema;
use WearePixel\BladePageBuilder\Support\SectionRegistry;
use WearePixel\BladePageBuilder\Support\SectionRenderer;

final class PageEditor extends Component
{
    public Page $page;

    public ?int $selectedSectionId = null;

    /** @var array<string, mixed> */
    public array $editingProps = [];

    public function mount(Page $page): void
    {
        $this->page = $page;
    }

    public function updateOrder(array $orderedIds): void
    {
        foreach ($orderedIds as $index => $id) {
            PageSection::whereKey($id)
                ->where('page_id', $this->page->id)
                ->update(['order' => $index]);
        }
    }

    public function addSection(string $slug): void
    {
        $componentClass = app(SectionRegistry::class)->resolve($slug);

        $props = [];
        foreach ($componentClass::schema()->fields() as $field) {
            $props[$field->name] = $field->type === \WearePixel\BladePageBuilder\Schema\FieldType::Repeater ? [] : '';
        }

        $section = $this->page->sections()->create([
            'section_type' => $slug,
            'order' => (int) ($this->page->sections()->max('order') + 1),
            'props' => $props,
        ]);

        $this->selectedSectionId = $section->id;
        $this->editingProps = $props;
    }

    public function selectSection(int $sectionId): void
    {
        $section = PageSection::whereKey($sectionId)
            ->where('page_id', $this->page->id)
            ->firstOrFail();

        $this->selectedSectionId = $sectionId;
        $this->editingProps = $section->props ?? [];
    }

    public function addRepeaterItem(string $fieldName): void
    {
        $this->editingProps[$fieldName] ??= [];
        $this->editingProps[$fieldName][] = [];
        $this->persistEditingProps();
    }

    public function removeRepeaterItem(string $fieldName, int $index): void
    {
        unset($this->editingProps[$fieldName][$index]);
        $this->editingProps[$fieldName] = array_values($this->editingProps[$fieldName] ?? []);
        $this->persistEditingProps();
    }

    public function updatedEditingProps(): void
    {
        $this->persistEditingProps();
    }

    public function publish(): void
    {
        app(PublishPage::class)->execute($this->page, auth()->id());
        $this->dispatch('page-published');
    }

    public function render(): View
    {
        return view('blade-page-builder::livewire.page-editor', [
            'sections' => $this->page->sections()->orderBy('order')->get(),
            'availableSections' => app(SectionRegistry::class)->slugs(),
            'selectedSchema' => $this->resolveSelectedSchema(),
            'previewHtml' => $this->renderPreview(),
        ]);
    }

    private function resolveSelectedSchema(): ?Schema
    {
        if (! $this->selectedSectionId) {
            return null;
        }

        $section = PageSection::whereKey($this->selectedSectionId)
            ->where('page_id', $this->page->id)
            ->first();

        if (! $section) {
            return null;
        }

        return app(SectionRegistry::class)->resolve($section->section_type)::schema();
    }

    private function renderPreview(): HtmlString
    {
        $sections = $this->page->sections()->orderBy('order')->get()
            ->map(fn (PageSection $section) => [
                'section_type' => $section->section_type,
                'props' => $section->props,
            ])
            ->all();

        return new HtmlString(app(SectionRenderer::class)->render($sections));
    }

    private function persistEditingProps(): void
    {
        if (! $this->selectedSectionId) {
            return;
        }

        PageSection::whereKey($this->selectedSectionId)
            ->where('page_id', $this->page->id)
            ->update(['props' => $this->editingProps]);
    }
}
```

- [ ] **Step 4: Create a minimal resources/views/livewire/page-editor.blade.php**

```blade
<div>
    <ul>
        @foreach ($sections as $section)
            <li wire:key="section-{{ $section->id }}" wire:click="selectSection({{ $section->id }})">
                {{ $section->section_type }}
            </li>
        @endforeach
    </ul>
</div>
```

This is deliberately minimal — Task 13 replaces it with the full drag/field-panel/preview layout once the field partials (Tasks 11–12) exist.

- [ ] **Step 5: Register the Livewire component**

In `src/BladePageBuilderServiceProvider.php`, add the import:

```php
use Livewire\Livewire;
use WearePixel\BladePageBuilder\Livewire\PageEditor;
```

and add to the end of `boot()`:

```php
        Livewire::component('page-builder::page-editor', PageEditor::class);
```

- [ ] **Step 6: Run test to verify it passes**

Run: `vendor/bin/pest tests/Feature/PageEditorTest.php`
Expected: PASS

- [ ] **Step 7: Commit**

```bash
git add src/Livewire resources/views/livewire src/BladePageBuilderServiceProvider.php tests/Feature/PageEditorTest.php
git commit -m "feat: add PageEditor Livewire component core behavior"
```

---

### Task 11: Simple field partials (text, textarea, richtext, select, link, image)

**Files:**

- Create: `resources/views/fields/text.blade.php`
- Create: `resources/views/fields/textarea.blade.php`
- Create: `resources/views/fields/richtext.blade.php`
- Create: `resources/views/fields/select.blade.php`
- Create: `resources/views/fields/link.blade.php`
- Create: `resources/views/fields/image.blade.php`
- Modify: `src/Livewire/PageEditor.php` (add `WithFileUploads`, `$imageUploads`, `updatedImageUploads()`)
- Modify: `tests/Fixtures/TestHeroSection.php` (add a `photo` image field for test coverage)
- Test: `tests/Feature/FieldPartialsTest.php`

**Interfaces:**

- Consumes: `Field`/`Schema` (Task 4), `PageEditor::$editingProps` (Task 10).
- Produces: every field partial accepts `Field $field` and `string $modelPath` (a dotted Livewire binding path, e.g. `editingProps.heading`), plus `$editingProps` from the parent view for the image preview.

- [ ] **Step 1: Write the failing test**

```php
<?php

declare(strict_types=1);

use Livewire\Livewire;
use WearePixel\BladePageBuilder\Livewire\PageEditor;
use WearePixel\BladePageBuilder\Models\Page;

beforeEach(function () {
    config(['blade-page-builder.sections' => [
        'test-hero' => \WearePixel\BladePageBuilder\Tests\Fixtures\TestHeroSection::class,
    ]]);
});

it('renders a text input and select options for the selected section schema', function () {
    $page = Page::create(['title' => 'Home', 'slug' => 'home']);
    $section = $page->sections()->create([
        'section_type' => 'test-hero',
        'order' => 0,
        'props' => ['heading' => 'Hello', 'layout' => 'image-right'],
    ]);

    Livewire::test(PageEditor::class, ['page' => $page])
        ->call('selectSection', $section->id)
        ->assertSee('Heading')
        ->assertSee('Layout')
        ->assertSee('Image on left')
        ->assertSee('Image on right');
});

it('stores an uploaded image and persists its path to the selected section props', function () {
    Storage::fake('public');

    $page = Page::create(['title' => 'Home', 'slug' => 'home']);
    $section = $page->sections()->create([
        'section_type' => 'test-hero',
        'order' => 0,
        'props' => ['heading' => 'Hello', 'layout' => 'image-right'],
    ]);

    $file = UploadedFile::fake()->image('photo.jpg');

    Livewire::test(PageEditor::class, ['page' => $page])
        ->call('selectSection', $section->id)
        ->set('imageUploads.photo', $file)
        ->assertOk();

    Storage::disk('public')->assertExists($section->fresh()->props['photo'] ?? '');
});
```

Save as `tests/Feature/FieldPartialsTest.php`. Add `use Illuminate\Http\UploadedFile;` and `use Illuminate\Support\Facades\Storage;` at the top.

- [ ] **Step 2: Add a photo field to the fixture section**

The second test needs an `image` field on the fixture schema. Replace the full contents of `tests/Fixtures/TestHeroSection.php` with:

```php
<?php

declare(strict_types=1);

namespace WearePixel\BladePageBuilder\Tests\Fixtures;

use Illuminate\View\Component;
use Illuminate\View\View;
use WearePixel\BladePageBuilder\Contracts\Sectionable;
use WearePixel\BladePageBuilder\Schema\Schema;

final class TestHeroSection extends Component implements Sectionable
{
    public function __construct(
        public string $heading = '',
        public string $layout = 'image-right',
        public ?string $photo = null,
    ) {}

    public static function schema(): Schema
    {
        return Schema::make()
            ->text('heading', label: 'Heading')
            ->select('layout', label: 'Layout', options: [
                'image-left' => 'Image on left',
                'image-right' => 'Image on right',
            ])->mapsToClass(fn (string $value) => $value === 'image-left' ? 'flex-row-reverse' : 'flex-row')
            ->image('photo', label: 'Photo');
    }

    public function with(): array
    {
        return [
            'layoutClass' => static::schema()->field('layout')?->resolveClass($this->layout),
        ];
    }

    public function render(): View
    {
        return view('test-hero');
    }
}
```

- [ ] **Step 3: Run test to verify it fails**

Run: `vendor/bin/pest tests/Feature/FieldPartialsTest.php`
Expected: FAIL — no field partials rendered yet, `imageUploads` property doesn't exist.

- [ ] **Step 4: Create resources/views/fields/text.blade.php**

```blade
<div class="mb-4">
    <label class="mb-1 block text-sm font-medium text-gray-700">{{ $field->label }}</label>
    <input type="text" wire:model.live.debounce.400ms="{{ $modelPath }}"
        class="w-full rounded-md border-gray-300 shadow-sm">
</div>
```

- [ ] **Step 5: Create resources/views/fields/textarea.blade.php**

```blade
<div class="mb-4">
    <label class="mb-1 block text-sm font-medium text-gray-700">{{ $field->label }}</label>
    <textarea wire:model.live.debounce.400ms="{{ $modelPath }}" rows="4"
        class="w-full rounded-md border-gray-300 shadow-sm"></textarea>
</div>
```

- [ ] **Step 6: Create resources/views/fields/richtext.blade.php**

```blade
<div class="mb-4">
    <label class="mb-1 block text-sm font-medium text-gray-700">{{ $field->label }}</label>
    <textarea wire:model.live.debounce.400ms="{{ $modelPath }}" rows="8"
        class="w-full rounded-md border-gray-300 shadow-sm"></textarea>
</div>
```

v1 ships richtext as a plain textarea — swapping in a WYSIWYG library is a drop-in replacement of this one partial later, no schema/data changes required.

- [ ] **Step 7: Create resources/views/fields/select.blade.php**

```blade
<div class="mb-4">
    <label class="mb-1 block text-sm font-medium text-gray-700">{{ $field->label }}</label>
    <select wire:model.live="{{ $modelPath }}" class="w-full rounded-md border-gray-300 shadow-sm">
        @foreach ($field->options['options'] as $value => $optionLabel)
            <option value="{{ $value }}">{{ $optionLabel }}</option>
        @endforeach
    </select>
</div>
```

- [ ] **Step 8: Create resources/views/fields/link.blade.php**

```blade
<div class="mb-4 space-y-2">
    <label class="block text-sm font-medium text-gray-700">{{ $field->label }}</label>
    <input type="text" placeholder="Label" wire:model.live.debounce.400ms="{{ $modelPath }}.label"
        class="w-full rounded-md border-gray-300 shadow-sm">
    <input type="url" placeholder="URL" wire:model.live.debounce.400ms="{{ $modelPath }}.url"
        class="w-full rounded-md border-gray-300 shadow-sm">
    <label class="flex items-center gap-2 text-sm text-gray-600">
        <input type="checkbox" wire:model.live="{{ $modelPath }}.new_tab">
        Open in new tab
    </label>
</div>
```

- [ ] **Step 9: Create resources/views/fields/image.blade.php**

```blade
<div class="mb-4">
    <label class="mb-1 block text-sm font-medium text-gray-700">{{ $field->label }}</label>
    @if (! empty($editingProps[$field->name]))
        <img src="{{ Illuminate\Support\Facades\Storage::disk(config('blade-page-builder.disk', 'public'))->url($editingProps[$field->name]) }}"
            class="mb-2 h-24 w-auto rounded object-cover">
    @endif
    <input type="file" wire:model="imageUploads.{{ $field->name }}" accept="image/*">
    @error('imageUploads.' . $field->name)
        <span class="text-sm text-red-600">{{ $message }}</span>
    @enderror
</div>
```

- [ ] **Step 10: Add file upload handling to src/Livewire/PageEditor.php**

Add the import and trait:

```php
use Livewire\WithFileUploads;
```

```php
final class PageEditor extends Component
{
    use WithFileUploads;

    /** @var array<string, mixed> */
    public array $imageUploads = [];
```

Add this method (near `updatedEditingProps`):

```php
    public function updatedImageUploads(mixed $value, string $key): void
    {
        if (! $value instanceof \Livewire\Features\SupportFileUploads\TemporaryUploadedFile) {
            return;
        }

        $path = $value->store('page-builder', config('blade-page-builder.disk', 'public'));

        $this->editingProps[$key] = $path;
        $this->persistEditingProps();
    }
```

- [ ] **Step 11: Wire the field loop into resources/views/livewire/page-editor.blade.php**

Replace the file's contents with:

```blade
<div>
    <ul>
        @foreach ($sections as $section)
            <li wire:key="section-{{ $section->id }}" wire:click="selectSection({{ $section->id }})">
                {{ $section->section_type }}
            </li>
        @endforeach
    </ul>

    @if ($selectedSchema)
        <div>
            @foreach ($selectedSchema->fields() as $field)
                @include('blade-page-builder::fields.' . $field->type->value, [
                    'field' => $field,
                    'modelPath' => "editingProps.{$field->name}",
                ])
            @endforeach
        </div>
    @endif
</div>
```

- [ ] **Step 12: Run test to verify it passes**

Run: `vendor/bin/pest tests/Feature/FieldPartialsTest.php`
Expected: PASS

- [ ] **Step 13: Commit**

```bash
git add resources/views/fields src/Livewire/PageEditor.php resources/views/livewire/page-editor.blade.php tests/Feature/FieldPartialsTest.php tests/Fixtures/TestHeroSection.php
git commit -m "feat: add simple field partials and image upload handling"
```

---

### Task 12: Repeater and Group field partials

**Files:**

- Create: `resources/views/fields/repeater.blade.php`
- Create: `resources/views/fields/group.blade.php`
- Test: `tests/Feature/RepeaterAndGroupFieldsTest.php`

**Interfaces:**

- Consumes: `Field::$itemSchema` (Task 4), `PageEditor::addRepeaterItem()` / `removeRepeaterItem()` (Task 10), the field partials from Task 11 (rendered recursively for nested fields).

- [ ] **Step 1: Write the failing test**

```php
<?php

declare(strict_types=1);

use Livewire\Livewire;
use WearePixel\BladePageBuilder\Livewire\PageEditor;
use WearePixel\BladePageBuilder\Models\Page;
use WearePixel\BladePageBuilder\Tests\Fixtures\TestFaqSection;

beforeEach(function () {
    config(['blade-page-builder.sections' => [
        'test-faq' => TestFaqSection::class,
    ]]);
});

it('adds and removes repeater items, persisting each edit', function () {
    $page = Page::create(['title' => 'Home', 'slug' => 'home']);
    $section = $page->sections()->create(['section_type' => 'test-faq', 'order' => 0, 'props' => ['items' => []]]);

    $test = Livewire::test(PageEditor::class, ['page' => $page])
        ->call('selectSection', $section->id)
        ->call('addRepeaterItem', 'items')
        ->assertOk();

    expect($section->fresh()->props['items'])->toHaveCount(1);

    $test->set('editingProps.items.0.question', 'What is this?')->assertOk();

    expect($section->fresh()->props['items'][0]['question'])->toBe('What is this?');

    $test->call('removeRepeaterItem', 'items', 0)->assertOk();

    expect($section->fresh()->props['items'])->toBe([]);
});
```

Save as `tests/Feature/RepeaterAndGroupFieldsTest.php`.

- [ ] **Step 2: Create the fixture used by the test — tests/Fixtures/TestFaqSection.php**

```php
<?php

declare(strict_types=1);

namespace WearePixel\BladePageBuilder\Tests\Fixtures;

use Illuminate\View\Component;
use Illuminate\View\View;
use WearePixel\BladePageBuilder\Contracts\Sectionable;
use WearePixel\BladePageBuilder\Schema\Schema;

final class TestFaqSection extends Component implements Sectionable
{
    public function __construct(
        public array $items = [],
    ) {}

    public static function schema(): Schema
    {
        $itemSchema = Schema::make()
            ->text('question', label: 'Question')
            ->textarea('answer', label: 'Answer');

        return Schema::make()->repeater('items', label: 'FAQ items', itemSchema: $itemSchema);
    }

    public function render(): View
    {
        return view('test-faq');
    }
}
```

- [ ] **Step 3: Create tests/Fixtures/views/test-faq.blade.php**

```blade
<div>
    @foreach ($items as $item)
        <p>{{ $item['question'] ?? '' }}</p>
    @endforeach
</div>
```

- [ ] **Step 4: Run test to verify it fails**

Run: `vendor/bin/pest tests/Feature/RepeaterAndGroupFieldsTest.php`
Expected: FAIL — the `repeater` field partial doesn't exist yet, so `@include` throws a view-not-found error.

- [ ] **Step 5: Create resources/views/fields/repeater.blade.php**

```blade
<div class="mb-4 space-y-3 rounded-md border border-gray-200 p-3">
    <p class="text-sm font-medium text-gray-700">{{ $field->label }}</p>

    @foreach (($editingProps[$field->name] ?? []) as $index => $item)
        <div class="space-y-2 rounded-md border border-gray-100 p-2" wire:key="repeater-{{ $field->name }}-{{ $index }}">
            @foreach ($field->itemSchema->fields() as $subField)
                @include('blade-page-builder::fields.' . $subField->type->value, [
                    'field' => $subField,
                    'modelPath' => "editingProps.{$field->name}.{$index}.{$subField->name}",
                ])
            @endforeach
            <button type="button" wire:click="removeRepeaterItem('{{ $field->name }}', {{ $index }})"
                class="text-sm text-red-600">Remove</button>
        </div>
    @endforeach

    <button type="button" wire:click="addRepeaterItem('{{ $field->name }}')"
        class="text-sm text-blue-600">Add item</button>
</div>
```

- [ ] **Step 6: Create resources/views/fields/group.blade.php**

```blade
<div class="mb-4 space-y-3 rounded-md border border-gray-200 p-3">
    <p class="text-sm font-medium text-gray-700">{{ $field->label }}</p>

    @foreach ($field->itemSchema->fields() as $subField)
        @include('blade-page-builder::fields.' . $subField->type->value, [
            'field' => $subField,
            'modelPath' => "editingProps.{$field->name}.{$subField->name}",
        ])
    @endforeach
</div>
```

- [ ] **Step 7: Run test to verify it passes**

Run: `vendor/bin/pest tests/Feature/RepeaterAndGroupFieldsTest.php`
Expected: PASS

- [ ] **Step 8: Commit**

```bash
git add resources/views/fields/repeater.blade.php resources/views/fields/group.blade.php tests/Feature/RepeaterAndGroupFieldsTest.php tests/Fixtures/TestFaqSection.php tests/Fixtures/views/test-faq.blade.php
git commit -m "feat: add repeater and group field partials"
```

---

### Task 13: Drag-and-drop wiring and full editor layout

**Files:**

- Modify: `resources/views/livewire/page-editor.blade.php` (full layout: section list with drag handles, add-section picker, field panel, preview pane)
- Test: `tests/Feature/PageEditorLayoutTest.php`

**Interfaces:**

- Consumes: `PageEditor::$sections`, `$availableSections`, `$selectedSchema`, `$previewHtml` (Task 10), field partials (Tasks 11–12).
- Produces: full editor markup including a SortableJS-driven section list calling `$wire.updateOrder(orderedIds)` on drop.

- [ ] **Step 1: Write the failing test**

```php
<?php

declare(strict_types=1);

use Livewire\Livewire;
use WearePixel\BladePageBuilder\Livewire\PageEditor;
use WearePixel\BladePageBuilder\Models\Page;

beforeEach(function () {
    config(['blade-page-builder.sections' => [
        'test-hero' => \WearePixel\BladePageBuilder\Tests\Fixtures\TestHeroSection::class,
    ]]);
});

it('renders the section list with drag data attributes, the add-section picker, and the preview pane', function () {
    $page = Page::create(['title' => 'Home', 'slug' => 'home']);
    $section = $page->sections()->create(['section_type' => 'test-hero', 'order' => 0, 'props' => ['heading' => 'Hi', 'layout' => 'image-right']]);

    Livewire::test(PageEditor::class, ['page' => $page])
        ->assertSee('data-section-id="' . $section->id . '"', escape: false)
        ->assertSee('test-hero')
        ->assertSeeHtml('wire:click="addSection(\'test-hero\')"')
        ->assertSeeHtml('$wire.updateOrder')
        ->assertSee('Publish');
});
```

Save as `tests/Feature/PageEditorLayoutTest.php`.

- [ ] **Step 2: Run test to verify it fails**

Run: `vendor/bin/pest tests/Feature/PageEditorLayoutTest.php`
Expected: FAIL — the minimal layout from Task 11 doesn't have drag attributes, an add-section picker, or a publish button.

- [ ] **Step 3: Replace resources/views/livewire/page-editor.blade.php with the full layout**

```blade
<div class="grid grid-cols-3 gap-6">
    @once
        <script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.2/Sortable.min.js"></script>
    @endonce

    <div class="col-span-1 space-y-4">
        <div
            x-data
            x-init="
                Sortable.create($refs.sectionList, {
                    animation: 150,
                    handle: '.drag-handle',
                    onEnd: () => {
                        const orderedIds = Array.from($refs.sectionList.children).map(el => el.dataset.sectionId);
                        $wire.updateOrder(orderedIds);
                    },
                });
            "
        >
            <ul x-ref="sectionList" class="space-y-2">
                @foreach ($sections as $section)
                    <li
                        data-section-id="{{ $section->id }}"
                        wire:key="section-{{ $section->id }}"
                        wire:click="selectSection({{ $section->id }})"
                        class="flex items-center gap-2 rounded-md border border-gray-200 p-3 {{ $selectedSectionId === $section->id ? 'ring-2 ring-blue-500' : '' }}"
                    >
                        <span class="drag-handle cursor-move text-gray-400">::</span>
                        <span>{{ $section->section_type }}</span>
                    </li>
                @endforeach
            </ul>
        </div>

        <div class="space-y-1">
            <p class="text-sm font-medium text-gray-700">Add section</p>
            @foreach ($availableSections as $slug)
                <button type="button" wire:click="addSection('{{ $slug }}')"
                    class="block w-full rounded-md border border-gray-200 p-2 text-left text-sm hover:bg-gray-50">
                    {{ $slug }}
                </button>
            @endforeach
        </div>

        <button type="button" wire:click="publish"
            class="w-full rounded-md bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-500">
            Publish
        </button>
    </div>

    <div class="col-span-1">
        @if ($selectedSchema)
            @foreach ($selectedSchema->fields() as $field)
                @include('blade-page-builder::fields.' . $field->type->value, [
                    'field' => $field,
                    'modelPath' => "editingProps.{$field->name}",
                ])
            @endforeach
        @endif
    </div>

    <div class="col-span-1 rounded-md border border-gray-200 p-4">
        {{ $previewHtml }}
    </div>
</div>
```

- [ ] **Step 4: Run test to verify it passes**

Run: `vendor/bin/pest tests/Feature/PageEditorLayoutTest.php`
Expected: PASS

- [ ] **Step 5: Commit**

```bash
git add resources/views/livewire/page-editor.blade.php tests/Feature/PageEditorLayoutTest.php
git commit -m "feat: wire drag-and-drop reordering and assemble the full editor layout"
```

---

### Task 14: Version history UI

**Files:**

- Modify: `src/Livewire/PageEditor.php` (add `showingHistory`, `revisions()` data, `restore()` method)
- Modify: `resources/views/livewire/page-editor.blade.php` (add a history panel)
- Test: `tests/Feature/PageEditorHistoryTest.php`

**Interfaces:**

- Consumes: `RestorePageRevision` (Task 7), `Page::revisions()` (Task 3).
- Produces: `PageEditor::toggleHistory()`, `PageEditor::restore(int $revisionId)`, view data `revisions`.

- [ ] **Step 1: Write the failing test**

```php
<?php

declare(strict_types=1);

use Livewire\Livewire;
use WearePixel\BladePageBuilder\Actions\PublishPage;
use WearePixel\BladePageBuilder\Livewire\PageEditor;
use WearePixel\BladePageBuilder\Models\Page;

beforeEach(function () {
    config(['blade-page-builder.sections' => [
        'test-hero' => \WearePixel\BladePageBuilder\Tests\Fixtures\TestHeroSection::class,
    ]]);
});

it('lists revisions and restores a chosen one', function () {
    $page = Page::create(['title' => 'Home', 'slug' => 'home']);
    $page->sections()->create(['section_type' => 'test-hero', 'order' => 0, 'props' => ['heading' => 'Original', 'layout' => 'image-right']]);
    $original = (new PublishPage())->execute($page);

    $page->sections()->first()->update(['props' => ['heading' => 'Edited', 'layout' => 'image-right']]);
    (new PublishPage())->execute($page);

    Livewire::test(PageEditor::class, ['page' => $page])
        ->call('toggleHistory')
        ->assertSee('Restored from revision')
        ->call('restore', $original->id)
        ->assertOk();

    expect($page->sections()->first()->props['heading'])->toBe('Original');
});
```

Save as `tests/Feature/PageEditorHistoryTest.php`.

- [ ] **Step 2: Run test to verify it fails**

Run: `vendor/bin/pest tests/Feature/PageEditorHistoryTest.php`
Expected: FAIL — `toggleHistory`/`restore` don't exist yet.

- [ ] **Step 3: Add history state and methods to src/Livewire/PageEditor.php**

Add the import:

```php
use WearePixel\BladePageBuilder\Actions\RestorePageRevision;
```

Add the public property (near `$selectedSectionId`):

```php
    public bool $showingHistory = false;
```

Add these methods (near `publish()`):

```php
    public function toggleHistory(): void
    {
        $this->showingHistory = ! $this->showingHistory;
    }

    public function restore(int $revisionId): void
    {
        $revision = $this->page->revisions()->findOrFail($revisionId);

        app(RestorePageRevision::class)->execute($revision, auth()->id());

        $this->selectedSectionId = null;
        $this->editingProps = [];
        $this->showingHistory = false;
    }
```

Add `'revisions' => $this->page->revisions()->get(),` to the array returned from `render()`.

- [ ] **Step 4: Add a history panel to resources/views/livewire/page-editor.blade.php**

Add, directly after the "Publish" button:

```blade
        <button type="button" wire:click="toggleHistory" class="w-full text-sm text-gray-600 underline">
            Version history
        </button>

        @if ($showingHistory)
            <ul class="space-y-2">
                @foreach ($revisions as $revision)
                    <li class="flex items-center justify-between rounded-md border border-gray-200 p-2 text-sm">
                        <span>{{ $revision->label ?? $revision->created_at->toDayDateTimeString() }}</span>
                        <button type="button" wire:click="restore({{ $revision->id }})" class="text-blue-600">
                            Restore
                        </button>
                    </li>
                @endforeach
            </ul>
        @endif
```

- [ ] **Step 5: Run test to verify it passes**

Run: `vendor/bin/pest tests/Feature/PageEditorHistoryTest.php`
Expected: PASS

- [ ] **Step 6: Commit**

```bash
git add src/Livewire/PageEditor.php resources/views/livewire/page-editor.blade.php tests/Feature/PageEditorHistoryTest.php
git commit -m "feat: add version history panel with restore"
```

---

### Task 15: Quality pass, README, and security review

**Files:**

- Create: `pint.json`
- Create: `README.md`
- Modify: none (verification-only steps)

**Interfaces:**

- None — this task only verifies and documents what earlier tasks built.

- [ ] **Step 1: Create pint.json**

```json
{
  "preset": "laravel"
}
```

- [ ] **Step 2: Run the full test suite**

Run: `vendor/bin/pest`
Expected: PASS — every test from Tasks 1–14 passes together.

- [ ] **Step 3: Run Pint**

Run: `vendor/bin/pint`
Expected: exits 0, reformatting any files that drifted from the preset during earlier tasks.

- [ ] **Step 4: Write README.md**

````markdown
# Blade Page Builder

A drag-and-drop, schema-driven page builder engine for Laravel Blade components.

## Install

    composer require wearepixel/blade-page-builder
    php artisan vendor:publish --tag=blade-page-builder-config
    php artisan migrate

## Usage

1. Write a section as a Blade class component implementing `Sectionable`:

```php
class HeroSplit extends \Illuminate\View\Component implements \WearePixel\BladePageBuilder\Contracts\Sectionable
{
    public function __construct(
        public string $heading = '',
        public string $layout = 'image-right',
    ) {}

    public static function schema(): \WearePixel\BladePageBuilder\Schema\Schema
    {
        return \WearePixel\BladePageBuilder\Schema\Schema::make()
            ->text('heading', label: 'Heading')
            ->select('layout', label: 'Layout', options: [
                'image-left' => 'Image on left',
                'image-right' => 'Image on right',
            ])->mapsToClass(fn (string $value) => $value === 'image-left' ? 'flex-row-reverse' : 'flex-row');
    }

    public function render(): \Illuminate\View\View
    {
        return view('sections.hero-split');
    }
}
```
````

2. Register it in `config/blade-page-builder.php`:

```php
'sections' => [
    'hero-split' => \App\Sections\HeroSplit::class,
],
```

3. Mount the editor at your own admin route:

```blade
<livewire:page-builder::page-editor :page="$page" />
```

4. Render the published page on the public site:

```blade
<x-page-builder::render :page="$page" />
```

````

- [ ] **Step 5: Commit**

```bash
git add pint.json README.md
git commit -m "docs: add README and Pint config"
````

- [ ] **Step 6: Run the security-reviewer agent**

Dispatch the `security-reviewer` agent against the full package, per the standing rule for code that handles user input (rich text, image uploads). Address any CRITICAL/HIGH findings before considering the package done; include the report in the final summary to the user.
