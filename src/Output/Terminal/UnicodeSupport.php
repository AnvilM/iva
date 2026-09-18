<?php

declare(strict_types=1);

namespace src\Output\Terminal;

final class UnicodeSupport
{
    public static function detect(): bool
    {
        if (PHP_OS_FAMILY === 'Windows' && getenv('WT_SESSION') === false && getenv('TERM') === false) {
            // Legacy cmd.exe with no Windows Terminal / ConEmu / TERM set:
            // assume the classic raster font, no braille/box-drawing glyphs.
            return false;
        }

        foreach (['LC_ALL', 'LC_CTYPE', 'LANG'] as $variable) {
            $value = getenv($variable);

            if ($value !== false && (stripos($value, 'utf-8') !== false || stripos($value, 'utf8') !== false)) {
                return true;
            }
        }

        return PHP_OS_FAMILY !== 'Windows';
    }
}
