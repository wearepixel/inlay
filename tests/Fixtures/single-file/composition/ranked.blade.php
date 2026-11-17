<?php

use WeArePixel\Inlay\Schema\Settings;
use WeArePixel\Inlay\SingleFile\Section;
use WeArePixel\Inlay\Tests\Fixtures\Composition\Priority;

section(
    Section::make('ranked')
        ->settings(Settings::make()->select('priority')->label('Priority')->options(Priority::class)->default(Priority::Low))
);
?>
<p>{{ $priority->name }}</p>
