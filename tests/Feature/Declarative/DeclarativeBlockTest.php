<?php

declare(strict_types=1);

use WeArePixel\Inlay\Composition\InvalidCompositionException;
use WeArePixel\Inlay\Declarative\DeclarativeBlockCompiler;
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
    WeArePixel\Inlay\Facades\Inlay::singleFileBlock(__DIR__.'/../../Fixtures/single-file/composition/promo.blade.php');

    expect(compileErrors(promoBlock(['handle' => 'promo'])))->toContain('handle: "promo" is already used by a block a developer registered. Choose another handle.');
});
