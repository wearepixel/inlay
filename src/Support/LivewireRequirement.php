<?php

declare(strict_types=1);

namespace WeArePixel\Inlay\Support;

use Livewire\Livewire;
use RuntimeException;

final class LivewireRequirement
{
    /**
     * $installed lets a test force either branch directly - Livewire is
     * always actually installed in this package's own dev environment, so
     * there's no other way to exercise the "not installed" branch.
     */
    public static function ensureInstalled(?bool $installed = null): void
    {
        $installed ??= class_exists(Livewire::class);

        if (! $installed) {
            throw new RuntimeException(
                "Inlay's administration interface requires Livewire.\n\n"
                .'Install it with:'."\n\n"
                .'composer require livewire/livewire'
            );
        }
    }
}
