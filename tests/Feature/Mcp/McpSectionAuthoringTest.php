<?php

declare(strict_types=1);

use Illuminate\Foundation\Auth\User;
use Illuminate\Support\Facades\Gate;
use WeArePixel\Inlay\Actions\PublishPage;
use WeArePixel\Inlay\Facades\Inlay;
use WeArePixel\Inlay\Mcp\InlayServer;
use WeArePixel\Inlay\Mcp\Tools\DeleteSectionTool;
use WeArePixel\Inlay\Mcp\Tools\GetStylingTool;
use WeArePixel\Inlay\Mcp\Tools\ReplaceSectionsTool;
use WeArePixel\Inlay\Mcp\Tools\SaveSectionTool;
use WeArePixel\Inlay\Models\DeclarativeSection;
use WeArePixel\Inlay\Models\Page;
use WeArePixel\Inlay\Support\SectionRegistry;

beforeEach(function () {
    config([
        'inlay.agent_sections.enabled' => true,
        'inlay.styling.stylesheets' => [__DIR__.'/../../Fixtures/css/app.css'],
        'inlay.styling.vite_manifest' => '/nope',
    ]);
    Inlay::previewView('host-preview');
    Inlay::previewLinkRoutes();
    Gate::define('inlay.manage-pages', fn (User $user, ?Page $page = null) => true);

    $this->user = User::forceCreate(['name' => 'Agent', 'email' => 'agent@example.com', 'password' => 'secret']);
});

function bandSection(string $class = 'py-20'): array
{
    return [
        'handle' => 'figma-band',
        'name' => 'Figma band',
        'description' => 'A band from the Figma file.',
        'settings' => [['name' => 'heading', 'type' => 'text', 'default' => 'From Figma']],
        'template' => ['tag' => 'section', 'class' => $class, 'children' => [['tag' => 'h2', 'text' => ['setting' => 'heading']]]],
    ];
}

it('refuses to author sections without the author ability, and stores nothing', function () {
    InlayServer::actingAs($this->user)
        ->tool(SaveSectionTool::class, ['section' => bandSection()])
        ->assertHasErrors(['inlay.author-sections']);

    expect(DeclarativeSection::count())->toBe(0);
});

it('refuses to author sections while the feature is switched off, even with the ability', function () {
    Gate::define('inlay.author-sections', fn (User $user) => true);
    config(['inlay.agent_sections.enabled' => false]);

    InlayServer::actingAs($this->user)
        ->tool(SaveSectionTool::class, ['section' => bandSection()])
        ->assertHasErrors(['switched off']);

    expect(DeclarativeSection::count())->toBe(0);
});

it('previews a dry-run section through a working link without saving it', function () {
    Gate::define('inlay.author-sections', fn (User $user) => true);
    $url = null;

    InlayServer::actingAs($this->user)
        ->tool(SaveSectionTool::class, ['section' => bandSection(), 'dry_run' => true])
        ->assertHasNoErrors()
        ->assertStructuredContent(function ($json) use (&$url) {
            $json->where('saved', false)->etc();
            $url = $json->toArray()['preview_url'];
        });

    expect(DeclarativeSection::count())->toBe(0);

    $this->get($url)->assertOk()->assertSee('<section class="py-20"><h2>From Figma</h2></section>', false);
});

it('rejects a class outside the site CSS and saves nothing', function () {
    Gate::define('inlay.author-sections', fn (User $user) => true);

    InlayServer::actingAs($this->user)
        ->tool(SaveSectionTool::class, ['section' => bandSection('py-20 bg-[#ff00ff]')])
        ->assertHasErrors(['template.class', 'bg-[#ff00ff]']);

    expect(DeclarativeSection::count())->toBe(0);
});

it('saves a section an agent can then place on a page, and deletes it only when unused', function () {
    Gate::define('inlay.author-sections', fn (User $user) => true);
    $page = Page::create(['title' => 'Landing']);

    InlayServer::actingAs($this->user)->tool(SaveSectionTool::class, ['section' => bandSection()])->assertHasNoErrors();

    InlayServer::actingAs($this->user)
        ->tool(ReplaceSectionsTool::class, ['page_id' => $page->id, 'sections' => [['section' => 'figma-band', 'settings' => ['heading' => 'Spring']]]])
        ->assertHasNoErrors()
        ->assertSee('Spring');

    InlayServer::actingAs($this->user)->tool(DeleteSectionTool::class, ['handle' => 'figma-band'])->assertHasErrors(['used by 1 section']);

    expect(DeclarativeSection::count())->toBe(1);
});

it('lists the site classes grouped by utility, and its tokens', function () {
    InlayServer::actingAs($this->user)
        ->tool(GetStylingTool::class)
        ->assertHasNoErrors()
        ->assertSee(['"framework":"tailwind"', 'bg-brand-600', 'lg:grid-cols-2', '--color-brand-600']);
});

it('does not leave a dry-run draft resolvable after the request', function () {
    Gate::define('inlay.author-sections', fn (User $user) => true);

    InlayServer::actingAs($this->user)->tool(SaveSectionTool::class, ['section' => bandSection(), 'dry_run' => true])->assertHasNoErrors();

    expect(app(SectionRegistry::class)->hasHandle('figma-band'))->toBeFalse();
});

it('needs publish rights to change a section that a published page uses', function () {
    Gate::define('inlay.author-sections', fn (User $user) => true);
    InlayServer::actingAs($this->user)->tool(SaveSectionTool::class, ['section' => bandSection()])->assertHasNoErrors();

    $page = Page::create(['title' => 'Live']);
    $page->sections()->create(['handle' => 'figma-band', 'order' => 0, 'settings' => []]);
    app(PublishPage::class)->execute($page);
    $page->sections()->delete();

    InlayServer::actingAs($this->user)
        ->tool(SaveSectionTool::class, ['section' => bandSection('flex')])
        ->assertHasErrors(['published page']);

    expect(DeclarativeSection::first()->definition['template']['class'])->toBe('py-20');

    Gate::define('inlay.publish-pages', fn (User $user) => true);
    config(['inlay.mcp.allow_publish' => true]);

    InlayServer::actingAs($this->user)->tool(SaveSectionTool::class, ['section' => bandSection('flex')])->assertHasNoErrors();

    expect(DeclarativeSection::first()->definition['template']['class'])->toBe('flex');
});
