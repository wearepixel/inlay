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
            'Call Inlay::routes() from your own routes/web.php to register the admin.',
            "Set config('inlay.middleware') to guard it - the default ['web'] alone leaves it public.",
            "Register your first component in config('inlay.sections').",
            'Call Inlay::previewView() so the preview iframe renders using your real frontend.',
        ]);

        return self::SUCCESS;
    }
}
