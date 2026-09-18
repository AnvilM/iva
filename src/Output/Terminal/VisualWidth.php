<?php

declare(strict_types=1);

namespace Iva\Output\Terminal;

use Psl\Str;

/**
 * "How many terminal columns does this string occupy" — used anywhere layout
 * math matters (progress bar fill, table column widths, centering). Per the
 * architecture spec (§9.3) this always means: strip ANSI escapes first, then
 * measure with a unicode-aware length function, never strlen()/mb_strlen().
 */
final class VisualWidth
{
    private const string ANSI_PATTERN = '/\x1b\[[0-9;?]*[a-zA-Z]/';

    public static function of(string $text): int
    {
        $plain = preg_replace(self::ANSI_PATTERN, '', $text);
        $plain ??= $text;

        return Str\length($plain);
    }

    /**
     * Strips ANSI escapes only, leaving the visible text untouched. Useful
     * when a caller needs the plain string itself, not just its width.
     */
    public static function strip(string $text): string
    {
        return preg_replace(self::ANSI_PATTERN, '', $text) ?? $text;
    }

    public static function pad(string $text, int $width, string $padChar = ' ', int $padType = STR_PAD_RIGHT): string
    {
        $currentWidth = self::of($text);

        if ($currentWidth >= $width) {
            return $text;
        }

        $padding = str_repeat($padChar, $width - $currentWidth);

        return match ($padType) {
            STR_PAD_LEFT => $padding . $text,
            STR_PAD_BOTH => str_repeat($padChar, intdiv($width - $currentWidth, 2))
                . $text
                . str_repeat($padChar, $width - $currentWidth - intdiv($width - $currentWidth, 2)),
            default => $text . $padding,
        };
    }
}
