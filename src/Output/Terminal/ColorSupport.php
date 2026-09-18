<?php

declare(strict_types=1);

namespace Iva\Output\Terminal;

enum ColorSupport
{
    case None;
    case Ansi16;
    case Ansi256;
    case TrueColor;

    /**
     * Priority order used by --ansi/--no-ansi/NO_COLOR/FORCE_COLOR, which
     * always outrank whatever auto-detection from the TTY would have said.
     */
    public static function detect(
        bool $isTty,
        ?string $term,
        ?string $colorTerm,
        bool $noColorEnv,
        ?string $forceColorEnv,
    ): self {
        if ($noColorEnv) {
            return self::None;
        }

        if ($forceColorEnv !== null && $forceColorEnv !== '' && $forceColorEnv !== '0') {
            return match ($forceColorEnv) {
                '1' => self::Ansi16,
                '2' => self::Ansi256,
                '3' => self::TrueColor,
                default => self::TrueColor,
            };
        }

        if (!$isTty) {
            return self::None;
        }

        if ($term === 'dumb' || $term === null) {
            return self::None;
        }

        if ($colorTerm === 'truecolor' || $colorTerm === '24bit') {
            return self::TrueColor;
        }

        if (str_contains($term, '256color')) {
            return self::Ansi256;
        }

        return self::Ansi16;
    }
}
