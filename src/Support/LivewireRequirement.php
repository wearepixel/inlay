<?php

declare(strict_types=1);

namespace WeArePixel\Inlay\Support;

use Livewire\Livewire;
use Livewire\LivewireManager;
use RuntimeException;

final class LivewireRequirement
{
    private const REQUIRED_VERSION = '^4.0';

    /**
     * Whether the installed Livewire (if any) can run the admin. Probes for
     * the method Inlay calls rather than a version string - class_exists()
     * alone would wave Livewire 3 through, which has a facade but no
     * namespaced component resolution.
     */
    public static function isSatisfied(): bool
    {
        return class_exists(Livewire::class)
            && method_exists(LivewireManager::class, 'addNamespace');
    }

    /**
     * $satisfied lets a test force either branch - a compatible Livewire is
     * always installed in this package's own dev environment.
     */
    public static function ensureInstalled(?bool $satisfied = null): void
    {
        $satisfied ??= self::isSatisfied();

        if ($satisfied) {
            return;
        }

        throw new RuntimeException(
            "Inlay's administration interface requires Livewire ".self::REQUIRED_VERSION.".\n\n"
            .(class_exists(Livewire::class)
                ? 'An incompatible version of livewire/livewire is installed. Upgrade it with:'
                : 'Install it with:')."\n\n"
            .'composer require livewire/livewire:'.self::REQUIRED_VERSION
        );
    }
}
