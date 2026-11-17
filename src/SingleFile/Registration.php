<?php

declare(strict_types=1);

namespace WeArePixel\Inlay\SingleFile;

/**
 * Backs the global section() function. A single-file section's Blade
 * file calls section() twice in its lifetime, in two different contexts,
 * and it must behave differently in each:
 *
 * - At registration (SingleFileLoader requiring the raw file to read its
 *   declaration): capture the Section instance so the loader can read it
 *   back via stopCapturing().
 * - At real render time (the file compiled and executed as an ordinary
 *   Blade view, with $settings already extracted into scope by Laravel's
 *   view engine): section() is a no-op, since nothing is capturing.
 */
final class Registration
{
    private static bool $isCapturing = false;

    private static ?Section $captured = null;

    public static function startCapturing(): void
    {
        self::$isCapturing = true;
        self::$captured = null;
    }

    public static function stopCapturing(): ?Section
    {
        self::$isCapturing = false;

        return self::$captured;
    }

    public static function register(Section $sectionType): void
    {
        if (self::$isCapturing) {
            self::$captured = $sectionType;
        }
    }
}
