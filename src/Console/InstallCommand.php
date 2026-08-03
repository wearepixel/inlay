<?php

declare(strict_types=1);

namespace WeArePixel\Inlay\Console;

use Illuminate\Console\Command;

final class InstallCommand extends Command
{
    protected $signature = 'inlay:install';

    protected $description = "Publish Inlay's configuration and run its migrations";

    public function handle(): int
    {
        $this->components->info('Installing Inlay...');

        $this->call('vendor:publish', ['--tag' => 'inlay-config']);
        $this->call('migrate');

        $this->newLine();
        $this->components->info('Inlay is installed. Next steps:');
        $this->components->bulletList([
            'Call Inlay::adminRoutes() from your own routes/web.php to register the admin.',
            "Set config('inlay.admin.middleware') to guard it - the default ['web'] alone leaves it public.",
            "Register your first block in config('inlay.blocks').",
            'Call Inlay::previewView() so the preview iframe renders using your real frontend.',
            'Call Inlay::pageRoutes() if you want Inlay to serve published Pages publicly - see docs/routing.md.',
        ]);

        return self::SUCCESS;
    }
}
