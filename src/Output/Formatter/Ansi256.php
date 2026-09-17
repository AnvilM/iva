<?php

declare(strict_types=1);

namespace Iva\Output\Formatter;

/**
 * Conversions to/from the standard xterm 256-color palette: a 6x6x6 RGB cube
 * (indices 16-231) plus a 24-step grayscale ramp (indices 232-255).
 */
final class Ansi256
{
    public static function fromRgb(int $red, int $green, int $blue): int
    {
        if ($red === $green && $green === $blue) {
            if ($red < 8) {
                return 16;
            }

            if ($red > 248) {
                return 231;
            }

            return (int) round(((($red - 8) / 247) * 24)) + 232;
        }

        $r = self::toCubeStep($red);
        $g = self::toCubeStep($green);
        $b = self::toCubeStep($blue);

        return 16 + (36 * $r) + (6 * $g) + $b;
    }

    /**
     * @return array{0: int, 1: int, 2: int}
     */
    public static function toRgb(int $index): array
    {
        $index = max(0, min(255, $index));

        if ($index < 16) {
            // The first 16 entries mirror the standard/bright 16-color palette;
            // callers needing that palette use Color::to16() instead.
            $level = $index < 8 ? 0 : 95;
            $step = $index < 8 ? 40 : 40;

            return [$level, $level, $level]; // coarse fallback, rarely hit
        }

        if ($index >= 232) {
            $level = 8 + (($index - 232) * 10);

            return [$level, $level, $level];
        }

        $offset = $index - 16;
        $r = intdiv($offset, 36);
        $g = intdiv($offset % 36, 6);
        $b = $offset % 6;

        return [self::cubeStepToLevel($r), self::cubeStepToLevel($g), self::cubeStepToLevel($b)];
    }

    private static function toCubeStep(int $channel): int
    {
        return (int) round($channel / 255 * 5);
    }

    private static function cubeStepToLevel(int $step): int
    {
        return $step === 0 ? 0 : 55 + ($step * 40);
    }
}
