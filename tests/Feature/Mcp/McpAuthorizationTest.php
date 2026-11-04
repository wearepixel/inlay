<?php

declare(strict_types=1);

use Illuminate\Foundation\Auth\User;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use WeArePixel\Inlay\Enums\PageStatus;
use WeArePixel\Inlay\Facades\Inlay;
use WeArePixel\Inlay\Mcp\InlayServer;
use WeArePixel\Inlay\Mcp\InlayTools;
use WeArePixel\Inlay\Mcp\Tools\CreatePageTool;
use WeArePixel\Inlay\Mcp\Tools\GetPageTool;
use WeArePixel\Inlay\Mcp\Tools\ListBlocksTool;
use WeArePixel\Inlay\Mcp\Tools\PublishPageTool;
use WeArePixel\Inlay\Mcp\Tools\ReplaceSectionsTool;
use WeArePixel\Inlay\Mcp\Tools\UpdateSectionTool;
use WeArePixel\Inlay\Mcp\Tools\UploadImageTool;
use WeArePixel\Inlay\Models\Page;

beforeEach(function () {
    Storage::fake('public');
    Inlay::singleFileBlock(__DIR__.'/../../Fixtures/single-file/composition/promo.blade.php');

    $this->user = User::forceCreate(['name' => 'Agent', 'email' => 'agent@example.com', 'password' => 'secret']);
    $this->page = Page::create(['title' => 'Spring sale', 'path' => 'spring']);
    $this->section = $this->page->sections()->create(['handle' => 'promo', 'order' => 0, 'settings' => ['heading' => 'Original']]);
});

function grantInlay(string ...$abilities): void
{
    foreach ($abilities as $ability) {
        Gate::define($ability, fn (User $user) => true);
    }
}

it('exposes every tool under an inlay- prefixed name', function () {
    $names = array_map(fn (string $tool) => app($tool)->name(), InlayTools::all());

    expect($names)->each->toStartWith('inlay-')
        ->and($names)->toContain('inlay-list-blocks', 'inlay-replace-sections', 'inlay-preview-page', 'inlay-publish-page');
});

it('denies every tool to a user without the manage ability, and changes nothing', function (string $tool, array $arguments) {
    InlayServer::actingAs($this->user)
        ->tool($tool, $arguments)
        ->assertHasErrors(['inlay.manage-pages']);

    expect(Page::count())->toBe(1)
        ->and($this->section->fresh()->settings)->toBe(['heading' => 'Original'])
        ->and(Storage::disk('public')->allFiles())->toBe([]);
})->with([
    'list blocks' => [ListBlocksTool::class, []],
    'get page' => [GetPageTool::class, ['page_id' => 1]],
    'create page' => [CreatePageTool::class, ['title' => 'New']],
    'replace sections' => [ReplaceSectionsTool::class, ['page_id' => 1, 'sections' => [['block' => 'promo', 'settings' => ['heading' => 'Hacked']]]]],
    'update section' => [UpdateSectionTool::class, ['page_id' => 1, 'section_id' => 1, 'settings' => ['heading' => 'Hacked']]],
    'upload image' => [UploadImageTool::class, ['data' => base64_encode('x')]],
]);

it('denies a guest even when the host gate would let guests through', function () {
    Gate::define('inlay.manage-pages', fn (?User $user) => true);

    InlayServer::tool(UpdateSectionTool::class, ['page_id' => $this->page->id, 'section_id' => $this->section->id, 'settings' => ['heading' => 'Hacked']])
        ->assertHasErrors();

    expect($this->section->fresh()->settings)->toBe(['heading' => 'Original']);
});

it('passes the page to the ability so a host can scope access per page', function () {
    Gate::define('inlay.manage-pages', fn (User $user, ?Page $page = null) => $page === null || $page->path !== 'spring');

    InlayServer::actingAs($this->user)
        ->tool(UpdateSectionTool::class, ['page_id' => $this->page->id, 'section_id' => $this->section->id, 'settings' => ['heading' => 'Hacked']])
        ->assertHasErrors();

    expect($this->section->fresh()->settings)->toBe(['heading' => 'Original']);
});

it('refuses to publish while publishing over MCP is switched off, even with both abilities', function () {
    grantInlay('inlay.manage-pages', 'inlay.publish-pages');
    config(['inlay.mcp.allow_publish' => false]);

    InlayServer::actingAs($this->user)
        ->tool(PublishPageTool::class, ['page_id' => $this->page->id])
        ->assertHasErrors(['Publishing over MCP is switched off']);

    expect($this->page->fresh()->status)->toBe(PageStatus::Draft)
        ->and($this->page->revisions()->count())->toBe(0);
});

it('refuses to publish without the publish ability', function () {
    grantInlay('inlay.manage-pages');
    config(['inlay.mcp.allow_publish' => true]);

    InlayServer::actingAs($this->user)
        ->tool(PublishPageTool::class, ['page_id' => $this->page->id])
        ->assertHasErrors(['inlay.publish-pages']);

    expect($this->page->fresh()->status)->toBe(PageStatus::Draft)
        ->and($this->page->revisions()->count())->toBe(0);
});

it('publishes when switched on and the user holds both abilities', function () {
    grantInlay('inlay.manage-pages', 'inlay.publish-pages');
    config(['inlay.mcp.allow_publish' => true]);

    InlayServer::actingAs($this->user)
        ->tool(PublishPageTool::class, ['page_id' => $this->page->id])
        ->assertHasNoErrors();

    expect($this->page->fresh()->status)->toBe(PageStatus::Published)
        ->and($this->page->revisions()->count())->toBe(1);
});
