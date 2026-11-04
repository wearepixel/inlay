<?php

declare(strict_types=1);

namespace WeArePixel\Inlay\Actions;

use Illuminate\Support\Facades\DB;
use WeArePixel\Inlay\Models\Page;
use WeArePixel\Inlay\Pages\ResolvedPageDefinition;

final class CreatePage
{
    public function __construct(private readonly SetPageAsHomepage $setPageAsHomepage) {}

    /**
     * The Page Definition is fixed at creation and never changes afterwards.
     * New sections are seeded from its default blocks with no stored
     * overrides, the same shape as adding a block from the picker.
     */
    public function execute(
        ResolvedPageDefinition $definition,
        string $title,
        ?string $path = null,
        ?string $metaTitle = null,
        ?string $metaDescription = null,
        bool $isHomepage = false,
    ): Page {
        $page = DB::transaction(function () use ($definition, $title, $path, $metaTitle, $metaDescription) {
            $page = Page::create([
                'title' => $title,
                'path' => $path,
                'meta_title' => $metaTitle,
                'meta_description' => $metaDescription,
                'definition' => $definition->handle,
            ]);

            foreach ($definition->defaultBlocks as $index => $handle) {
                $page->sections()->create([
                    'handle' => $handle,
                    'order' => $index,
                    'settings' => [],
                ]);
            }

            return $page;
        });

        if ($isHomepage) {
            $this->setPageAsHomepage->execute($page);
        }

        return $page;
    }
}
