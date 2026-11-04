<?php

declare(strict_types=1);

use Illuminate\Foundation\Auth\User;
use Illuminate\Support\Facades\Gate;
use WeArePixel\Inlay\Contracts\Screenshotter;
use WeArePixel\Inlay\Facades\Inlay;
use WeArePixel\Inlay\Mcp\InlayServer;
use WeArePixel\Inlay\Mcp\Tools\CreatePageTool;
use WeArePixel\Inlay\Mcp\Tools\ListBlocksTool;
use WeArePixel\Inlay\Mcp\Tools\PreviewPageTool;
use WeArePixel\Inlay\Mcp\Tools\ReplaceSectionsTool;
use WeArePixel\Inlay\Models\Page;
use WeArePixel\Inlay\Screenshots\Screenshot;

beforeEach(function () {
    Inlay::singleFileBlock(__DIR__.'/../../Fixtures/single-file/composition/promo.blade.php');
    Inlay::previewView('host-preview');
    Inlay::previewLinkRoutes();
    Gate::define('inlay.manage-pages', fn (User $user) => true);

    $this->user = User::forceCreate(['name' => 'Agent', 'email' => 'agent@example.com', 'password' => 'secret']);
    $this->page = Page::create(['title' => 'Spring sale', 'path' => 'spring']);
    $this->page->sections()->create(['handle' => 'promo', 'order' => 0, 'settings' => ['heading' => 'Saved']]);
});

it('describes each block setting with its value format, options and default', function () {
    InlayServer::actingAs($this->user)
        ->tool(ListBlocksTool::class)
        ->assertStructuredContent(function ($json) {
            $json->where('blocks.0.handle', 'promo')
                ->where('blocks.0.settings.0.name', 'heading')
                ->where('blocks.0.settings.0.max_length', 20)
                ->where('blocks.0.settings.0.default', 'Fresh meals, delivered')
                ->where('blocks.0.settings.4.options', [['value' => 'left', 'label' => 'Left'], ['value' => 'center', 'label' => 'Center']])
                ->where('blocks.0.settings.7.item_settings.0.name', 'title')
                ->etc();
        });
});

it('returns every problem with its path and saves nothing when a composition is invalid', function () {
    InlayServer::actingAs($this->user)
        ->tool(ReplaceSectionsTool::class, ['page_id' => $this->page->id, 'sections' => [
            ['block' => 'promo', 'settings' => ['heading' => str_repeat('x', 30), 'cta' => ['url' => 'javascript:alert(1)']]],
        ]])
        ->assertHasErrors(['sections.0.settings.heading', 'sections.0.settings.cta.url']);

    expect($this->page->sections()->first()->settings)->toBe(['heading' => 'Saved']);
});

it('previews a proposed composition with a report, link and screenshots, without saving it', function () {
    app()->instance(Screenshotter::class, new class implements Screenshotter
    {
        public array $captured = [];

        public function capture(string $url, int $width): Screenshot
        {
            $this->captured[] = [$url, $width];

            return new Screenshot('jpeg-bytes', 'image/jpeg', $width);
        }
    });

    $response = InlayServer::actingAs($this->user)
        ->tool(PreviewPageTool::class, ['page_id' => $this->page->id, 'sections' => [
            ['block' => 'promo', 'settings' => ['heading' => 'Proposed', 'cta' => ['label' => 'Shop', 'url' => '/shop']]],
        ]])
        ->assertHasNoErrors()
        ->assertSee(['Proposed', 'Shop', '/shop', 'proposed sections (not saved)', 'desktop screenshot (1440px wide)', 'mobile screenshot (390px wide)']);

    $captured = app(Screenshotter::class)->captured;

    expect($captured)->toHaveCount(2)
        ->and($captured[0][0])->toContain('signature=')
        ->and($this->page->sections()->first()->settings)->toBe(['heading' => 'Saved']);

    $this->get($captured[0][0])->assertOk()->assertSee('Proposed')->assertDontSee('Saved');
});

it('creates nothing when the initial sections of a new page are invalid', function () {
    InlayServer::actingAs($this->user)
        ->tool(CreatePageTool::class, ['title' => 'Autumn', 'path' => 'autumn', 'sections' => [['block' => 'nope']]])
        ->assertHasErrors(['sections.0.block']);

    expect(Page::where('path', 'autumn')->exists())->toBeFalse();
});

it('creates a draft page with its sections', function () {
    InlayServer::actingAs($this->user)
        ->tool(CreatePageTool::class, ['title' => 'Autumn', 'path' => 'autumn', 'sections' => [['block' => 'promo', 'settings' => ['heading' => 'Autumn']]]])
        ->assertHasNoErrors();

    $page = Page::where('path', 'autumn')->firstOrFail();

    expect($page->isPublished())->toBeFalse()
        ->and($page->sections()->pluck('settings')->all())->toBe([['heading' => 'Autumn']]);
});
