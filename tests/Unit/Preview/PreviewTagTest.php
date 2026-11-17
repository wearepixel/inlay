<?php

declare(strict_types=1);

use WeArePixel\Inlay\Preview\PreviewTag;

it('puts the attributes on a single root element, leaving the structure alone', function () {
    expect(PreviewTag::apply("  <tr><td>One</td></tr>\n", ['data-inlay-block' => 'a']))
        ->toBe('<tr data-inlay-block="a"><td>One</td></tr>');
});

it('treats nested elements of the same name as one root', function () {
    expect(PreviewTag::apply('<div class="x"><div>a</div></div>', ['data-id' => '1']))
        ->toBe('<div data-id="1" class="x"><div>a</div></div>');
});

it('wraps markup with more than one root element', function () {
    expect(PreviewTag::apply('<div>a</div><div>b</div>', ['data-id' => '1']))
        ->toBe('<div data-id="1" style="display: contents"><div>a</div><div>b</div></div>');
});

it('wraps markup that starts with text', function () {
    expect(PreviewTag::apply('Hello <b>there</b>', ['data-id' => '1']))
        ->toBe('<div data-id="1" style="display: contents">Hello <b>there</b></div>');
});

it('tags a lone void element and wraps one followed by siblings', function () {
    expect(PreviewTag::apply('<img src="/a.png">', ['data-id' => '1']))->toBe('<img data-id="1" src="/a.png">')
        ->and(PreviewTag::apply('<img src="/a.png"><p>Caption</p>', ['data-id' => '1']))->toStartWith('<div data-id="1"');
});

it('escapes attribute values', function () {
    expect(PreviewTag::apply('<p>a</p>', ['data-label' => '"><script>']))
        ->toBe('<p data-label="&quot;&gt;&lt;script&gt;">a</p>');
});
