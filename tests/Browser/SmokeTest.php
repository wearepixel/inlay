<?php

declare(strict_types=1);

it('loads the page index in a real browser', function () {
    $page = visit('/inlay/pages');

    $page->assertSee('Pages')->assertSee('New page');
});
