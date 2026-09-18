<?php

declare(strict_types=1);

namespace src\Output\Terminal;

/**
 * Raw ANSI CSI sequences for cursor movement and line/screen erasure.
 *
 * These are the same standard sequences a Psl\Ansi-based implementation
 * would emit; they are written out directly here (rather than through
 * Psl\Ansi) so this low-level, call-heavy piece of the redraw path doesn't
 * depend on Psl\Ansi's exact public call signatures, which the architecture
 * document itself flags as unverified pending a check against
 * https://php-standard-library.dev/ at integration time. Swapping the
 * bodies below for Psl\Ansi calls later is a localized, behavior-preserving
 * change — every other class talks to Cursor, never to raw escape codes.
 */
final class Cursor
{
    private const string CSI = "\x1b[";

    /** Moves the cursor up n rows without changing its column. */
    public static function up(int $rows): string
    {
        return $rows > 0 ? self::CSI . $rows . 'A' : '';
    }

    /** Moves the cursor down n rows without changing its column. */
    public static function down(int $rows): string
    {
        return $rows > 0 ? self::CSI . $rows . 'B' : '';
    }

    /** Returns the cursor to column 0 of the current row. */
    public static function toLineStart(): string
    {
        return "\r";
    }

    /** Erases the entire current line, cursor position unchanged. */
    public static function eraseLine(): string
    {
        return self::CSI . '2K';
    }

    /** Erases from the cursor to the end of the current screen (scroll buffer position). */
    public static function eraseToEndOfScreen(): string
    {
        return self::CSI . '0J';
    }

    public static function hide(): string
    {
        return self::CSI . '?25l';
    }

    public static function show(): string
    {
        return self::CSI . '?25h';
    }
}
