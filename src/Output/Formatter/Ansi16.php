<?php

declare(strict_types=1);

namespace src\Output\Formatter;

/**
 * Nearest-neighbour reduction from an arbitrary RGB triple down to one of the
 * sixteen standard ANSI colors, for terminals that only support ColorSupport::Ansi16.
 */
final class Ansi16
{
    /**
     * @return array{0: StandardColor, 1: bool}
     */
    public static function nearest(int $red, int $green, int $blue): array
    {
        $best = null;
        $bestDistance = PHP_INT_MAX;

        foreach (StandardColor::cases() as $color) {
            foreach ([false, true] as $bright) {
                [$r, $g, $b] = $color->rgb($bright);
                $distance = ($r - $red) ** 2 + ($g - $green) ** 2 + ($b - $blue) ** 2;

                if ($distance < $bestDistance) {
                    $bestDistance = $distance;
                    $best = [$color, $bright];
                }
            }
        }

        /** @var array{0: StandardColor, 1: bool} $best */
        return $best;
    }
}
