<?php

declare(strict_types=1);

use WeArePixel\Inlay\Composition\InvalidCompositionException;
use WeArePixel\Inlay\Declarative\DeclarativeBlockCompiler;
use WeArePixel\Inlay\Facades\Inlay;
use WeArePixel\Inlay\Support\SectionRenderer;

beforeEach(function () {
    config([
        'inlay.styling.stylesheets' => [__DIR__.'/../../Fixtures/css/app.css'],
        'inlay.styling.vite_manifest' => '/nope',
    ]);
});

function promoBlock(array $overrides = []): array
{
    return array_replace_recursive([
        'handle' => 'figma-promo',
        'name' => 'Figma promo',
        'description' => 'A promo band rebuilt from Figma.',
        'category' => 'Marketing',
        'settings' => [
            ['name' => 'heading', 'type' => 'text', 'label' => 'Heading', 'default' => 'Hello', 'required' => true],
            ['name' => 'body', 'type' => 'richtext', 'label' => 'Body', 'default' => '<p>Body</p>'],
            ['name' => 'tone', 'type' => 'select', 'label' => 'Tone', 'options' => [['value' => 'light', 'label' => 'Light'], ['value' => 'dark', 'label' => 'Dark']], 'default' => 'dark'],
            ['name' => 'cta', 'type' => 'link', 'label' => 'Button', 'default' => ['label' => 'Go', 'url' => '/go']],
            ['name' => 'photo', 'type' => 'image', 'label' => 'Photo', 'default' => ''],
            ['name' => 'flag', 'type' => 'toggle', 'label' => 'Flag', 'default' => false],
            ['name' => 'items', 'type' => 'repeater', 'label' => 'Items', 'item_settings' => [
                ['name' => 'title', 'type' => 'text', 'label' => 'Title'],
            ], 'default' => [['title' => 'One'], ['title' => 'Two']]],
        ],
        'template' => [
            'tag' => 'section',
            'class' => ['py-20', ['class' => 'bg-brand-600', 'when' => ['setting' => 'tone', 'equals' => 'dark']]],
            'attrs' => ['aria-label' => ['setting' => 'heading']],
            'children' => [
                ['tag' => 'h2', 'class' => 'lg:grid-cols-2', 'text' => ['setting' => 'heading']],
                ['tag' => 'div', 'html' => ['setting' => 'body']],
                ['tag' => 'ul', 'children' => [
                    ['tag' => 'li', 'each' => ['setting' => 'items', 'as' => 'item'], 'text' => ['setting' => 'item.title']],
                ]],
                ['tag' => 'img', 'if' => ['setting' => 'photo'], 'attrs' => ['src' => ['setting' => 'photo'], 'alt' => '']],
                ['tag' => 'a', 'class' => 'flex', 'link' => ['setting' => 'cta']],
                ['text' => 'Literal & <b>escaped</b>'],
            ],
        ],
    ], $overrides);
}

function compileErrors(array $definition): array
{
    try {
        app(DeclarativeBlockCompiler::class)->compile($definition);
    } catch (InvalidCompositionException $exception) {
        return array_map(fn ($error) => $error->path.': '.$error->message, $exception->errors);
    }

    throw new RuntimeException('Expected the block to be rejected.');
}

it('compiles a declarative block and renders it with escaping, conditions, loops and links', function () {
    $definition = app(DeclarativeBlockCompiler::class)->compile(promoBlock());

    $html = app(SectionRenderer::class)->renderDefinition($definition, ['heading' => 'Hi "there" <script>', 'body' => '<p>Ok</p><script>x</script>']);

    expect($html)
        ->toContain('<section class="py-20 bg-brand-600" aria-label="Hi &quot;there&quot; &lt;script&gt;">')
        ->toContain('<h2 class="lg:grid-cols-2">Hi &quot;there&quot; &lt;script&gt;</h2>')
        ->toContain('<div><p>Ok</p></div>')
        ->toContain('<li>One</li><li>Two</li>')
        ->toContain('<a class="flex" href="/go">Go</a>')
        ->toContain('Literal &amp; &lt;b&gt;escaped&lt;/b&gt;')
        ->not->toContain('<img')
        ->not->toContain('<script');
});

it('rejects classes the site CSS does not define, with suggestions', function () {
    $errors = compileErrors(promoBlock(['template' => ['children' => [['tag' => 'h2', 'class' => 'bg-brand-500 text-[#123456]']]]]));

    expect($errors[0])->toStartWith('template.children.0.class: "bg-brand-500" is not defined in the site\'s CSS. Similar: bg-brand-600')
        ->and($errors[1])->toStartWith('template.children.0.class: "text-[#123456]" is not defined');
});

it('rejects anything that could run script or escape the design system', function (array $node, string $expectedPath) {
    $errors = compileErrors(promoBlock(['template' => ['children' => [$node]]]));

    expect(implode("\n", $errors))->toContain($expectedPath);
})->with([
    'script tag' => [['tag' => 'script', 'text' => 'alert(1)'], 'template.children.0.tag'],
    'iframe' => [['tag' => 'iframe'], 'template.children.0.tag'],
    'style tag' => [['tag' => 'style', 'text' => 'body{}'], 'template.children.0.tag'],
    'inline style' => [['tag' => 'div', 'attrs' => ['style' => 'color:red']], 'template.children.0.attrs.style'],
    'event handler' => [['tag' => 'div', 'attrs' => ['onclick' => 'x()']], 'template.children.0.attrs.onclick'],
    'javascript href' => [['tag' => 'a', 'attrs' => ['href' => 'javascript:alert(1)'], 'text' => 'x'], 'template.children.0.attrs.href'],
    'html from a text setting' => [['tag' => 'div', 'html' => ['setting' => 'heading']], 'template.children.0.html'],
    'unknown setting' => [['tag' => 'p', 'text' => ['setting' => 'nope']], 'template.children.0.text'],
    'image src from text' => [['tag' => 'img', 'attrs' => ['src' => ['setting' => 'heading'], 'alt' => '']], 'template.children.0.attrs.src'],
    'image without alt' => [['tag' => 'img', 'attrs' => ['src' => ['setting' => 'photo']]], 'template.children.0.attrs.alt'],
    'item outside a loop' => [['tag' => 'p', 'text' => ['setting' => 'item.title']], 'template.children.0.text'],
]);

it('rejects a handle that a developer-registered block already uses', function () {
    Inlay::singleFileBlock(__DIR__.'/../../Fixtures/single-file/composition/promo.blade.php');

    expect(compileErrors(promoBlock(['handle' => 'promo'])))->toContain('handle: "promo" is already used by a block a developer registered. Choose another handle.');
});

it('rejects a loop nested inside another loop', function () {
    $errors = compileErrors(promoBlock(['template' => ['children' => [
        ['tag' => 'div', 'each' => ['setting' => 'items'], 'children' => [
            ['tag' => 'div', 'each' => ['setting' => 'items', 'as' => 'inner'], 'text' => 'x'],
        ]],
    ]]]));

    expect(implode("\n", $errors))->toContain('template.children.0.children.0.each: Loops cannot be nested');
});

it('caps repeater items in defaults and the size of a definition', function () {
    $tooMany = array_fill(0, 101, ['title' => 'x']);

    expect(implode("\n", compileErrors(promoBlock(['settings' => [6 => ['default' => $tooMany]]]))))->toContain('at most 100 items')
        ->and(implode("\n", compileErrors(promoBlock(['template' => ['class' => str_repeat('flex ', 300)]]))))->toContain('at most 1000 characters')
        ->and(implode("\n", compileErrors(promoBlock(['description' => str_repeat('x', 70000)]))))->toContain('at most 64 KB');
});

it('rejects protocol-relative links, ids from settings, and a link with an href', function (array $node, string $expected) {
    expect(implode("\n", compileErrors(promoBlock(['template' => ['children' => [$node]]]))))->toContain($expected);
})->with([
    'protocol relative' => [['tag' => 'a', 'attrs' => ['href' => '//evil.example'], 'text' => 'x'], 'attrs.href'],
    'backslash host' => [['tag' => 'a', 'attrs' => ['href' => '/\\evil.example'], 'text' => 'x'], 'attrs.href'],
    'id from a setting' => [['tag' => 'div', 'attrs' => ['id' => ['setting' => 'heading']]], 'attrs.id'],
    'link and href together' => [['tag' => 'a', 'link' => ['setting' => 'cta'], 'attrs' => ['href' => '/x']], 'attrs.href'],
]);

it('never renders attributes or tags outside the allowlist, even from a tampered stored definition', function () {
    $tampered = [...promoBlock(), 'template' => [
        'tag' => 'div',
        'attrs' => ['onmouseover' => 'alert(1)', 'style' => 'position:fixed', 'x><script>alert(1)</script><b' => 'y', 'id' => 'a" onclick="x', 'title' => 'ok'],
        'children' => [['tag' => 'script', 'text' => 'alert(1)']],
    ]];

    $html = app(SectionRenderer::class)->renderDefinition(app(DeclarativeBlockCompiler::class)->hydrate($tampered));

    expect($html)->toBe('<div title="ok"></div>');
});

it('stops rendering at the element ceiling instead of exhausting the worker', function () {
    $settings = promoBlock()['settings'];
    $settings[6]['default'] = array_fill(0, 100, ['title' => 'x']);
    $tampered = [...promoBlock(), 'settings' => $settings, 'template' => ['tag' => 'div', 'children' => [
        ['tag' => 'div', 'each' => ['setting' => 'items'], 'children' => [
            ['tag' => 'div', 'each' => ['setting' => 'items', 'as' => 'b'], 'children' => [
                ['tag' => 'span', 'each' => ['setting' => 'items', 'as' => 'c'], 'text' => 'x'],
            ]],
        ]],
    ]]];

    $html = app(SectionRenderer::class)->renderDefinition(app(DeclarativeBlockCompiler::class)->hydrate($tampered));

    expect(substr_count($html, '<span>'))->toBeGreaterThan(1000)->toBeLessThan(5001);
});

it('keeps repeater link defaults as plain arrays and defaults a select to its first option', function () {
    $definition = app(DeclarativeBlockCompiler::class)->compile(promoBlock(['settings' => [
        2 => ['default' => null],
        6 => ['item_settings' => [1 => ['name' => 'link', 'type' => 'link', 'default' => ['label' => 'More', 'url' => '/more']]]],
    ]]));

    expect($definition->settings->field('tone')->default)->toBe('light')
        ->and($definition->settings->field('items')->itemSchema->field('link')->nestedDefault)->toBe(['label' => 'More', 'url' => '/more', 'openInNewTab' => false]);
});

it('rejects a condition whose value cannot match the setting', function (array $when, string $expected) {
    expect(implode("\n", compileErrors(promoBlock(['template' => ['children' => [['tag' => 'p', 'if' => $when, 'text' => 'x']]]]))))->toContain($expected);
})->with([
    'unknown option' => [['setting' => 'tone', 'equals' => 'purple'], 'Must be one of: light, dark'],
    'string for a toggle' => [['setting' => 'flag', 'equals' => 'false'], 'Must be true or false'],
]);

it('reports a bad default where the agent wrote it', function () {
    expect(compileErrors(promoBlock(['settings' => [3 => ['default' => ['url' => 'javascript:x']]]])))
        ->toContain('settings.3.default.url: Only http(s), mailto and relative URLs are allowed.');
});
