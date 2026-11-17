<?php

declare(strict_types=1);

use WeArePixel\Inlay\Composition\CompositionReport;
use WeArePixel\Inlay\Facades\Inlay;

beforeEach(function () {
    Inlay::singleFileSection(__DIR__.'/../../Fixtures/single-file/composition/promo.blade.php');
    Inlay::singleFileSection(__DIR__.'/../../Fixtures/single-file/composition/broken.blade.php');
});

it('describes what each section renders, which content is still default, and what is broken', function () {
    $report = app(CompositionReport::class)->build([
        ['handle' => 'promo', 'settings' => ['cta' => ['label' => 'Order now', 'url' => '/order', 'openInNewTab' => false], 'items' => [['title' => 'Chef made']]]],
        ['handle' => 'broken', 'settings' => []],
        ['handle' => 'gone', 'settings' => []],
    ]);

    [$promo, $broken, $gone] = $report['sections'];

    expect($promo['outline']['headings'])->toBe([['level' => 2, 'text' => 'Fresh meals, delivered']])
        ->and($promo['outline']['links'])->toBe([['text' => 'Order now', 'href' => '/order']])
        ->and($promo['outline']['text'])->toContain('Chef made')
        ->and($promo['defaults_in_use'])->toBe(['heading' => 'Fresh meals, delivered'])
        ->and($promo['problems'])->toBe([])
        ->and($broken['problems'][0])->toStartWith('Failed to render:')
        ->and($gone['problems'])->toBe(['This section is not registered and will not render.'])
        ->and($report['warnings'])->toHaveCount(2);
});
