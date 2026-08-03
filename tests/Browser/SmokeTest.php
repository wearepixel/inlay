<?php

declare(strict_types=1);

it('loads the page index in a real browser', function () {
    $page = visit('/inlay/pages');

    $page->assertSee('Pages')->assertSee('New page');
});

/**
 * Regression test for a real bug: ".inlay a { color: inherit; }" is a
 * class+tag selector, which out-specifies a single-class rule like
 * ".inlay-button--primary" regardless of source order - silently winning
 * back the ambient near-black body text color on any <a> styled as a
 * primary button, rendering it as near-black-on-near-black. Asserting on
 * rendered color (not just text presence) is the only way this class of
 * bug gets caught - see the ":where(a)" fix in resources/dist/inlay.css.
 */
it('renders the New page button with legible white text, not the inherited body color', function () {
    $page = visit('/inlay/pages');

    $color = $page->script(
        "getComputedStyle([...document.querySelectorAll('a')].find(a => a.textContent.trim() === 'New page')).color",
    );

    expect($color)->toBe('rgb(255, 255, 255)');
});
