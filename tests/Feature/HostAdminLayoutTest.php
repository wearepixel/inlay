<?php

declare(strict_types=1);

use WeArePixel\Inlay\Facades\Inlay;
use WeArePixel\Inlay\Models\Page;

it('renders the admin inside the host layout when one is configured', function () {
    config()->set('inlay.admin.layout', 'host-admin');

    $this->get(route('inlay.admin.pages.index'))
        ->assertOk()
        ->assertSee('data-test="host-nav"', escape: false)
        ->assertSee('inlay-frame')
        ->assertSee(route('inlay.admin.assets.css'))
        ->assertDontSee('inlay-topnav');
});

it('keeps its own shell when no host layout is configured', function () {
    $this->get(route('inlay.admin.pages.index'))
        ->assertOk()
        ->assertSee('inlay-topnav')
        ->assertDontSee('data-test="host-nav"', escape: false);
});

it('links to the host\'s own routes when it resolves admin urls', function () {
    $page = Page::create(['title' => 'Home', 'path' => 'home']);
    Inlay::adminUrlsUsing(fn (string $screen, mixed ...$parameters) => $screen === 'pages.edit'
        ? "/admin/content/{$parameters[0]->id}"
        : null);

    $this->get(route('inlay.admin.pages.index'))
        ->assertSee("/admin/content/{$page->id}")
        ->assertSee(route('inlay.admin.pages.create'));

    Inlay::adminUrlsUsing(null);
});
