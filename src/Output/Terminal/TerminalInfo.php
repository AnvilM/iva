<?php

declare(strict_types=1);

namespace Iva\Output\Terminal;

/**
 * Terminal size, interactivity, and color support for a given stream.
 *
 * TTY detection uses PHP's built-in stream_isatty() rather than posix_isatty()
 * so it works the same on platforms without ext-posix (including Windows).
 */
final readonly class TerminalInfo
{
    private const int DEFAULT_COLUMNS = 80;
    private const int DEFAULT_LINES = 24;

    public function __construct(
        public bool $isInteractive,
        public int $columns,
        public int $lines,
        public ColorSupport $colorSupport,
    ) {}

    /**
     * @param resource $stream
     */
    public static function detect($stream): self
    {
        $isTty = self::isatty($stream);

        $columns = self::envInt('COLUMNS') ?? self::DEFAULT_COLUMNS;
        $lines = self::envInt('LINES') ?? self::DEFAULT_LINES;

        $term = self::env('TERM');
        $colorTerm = self::env('COLORTERM');
        $noColor = self::env('NO_COLOR') !== null;
        $forceColor = self::env('FORCE_COLOR');

        $colorSupport = ColorSupport::detect($isTty, $term, $colorTerm, $noColor, $forceColor);

        return new self($isTty, $columns, $lines, $colorSupport);
    }

    /**
     * @param resource $stream
     */
    private static function isatty($stream): bool
    {
        if (!is_resource($stream)) {
            return false;
        }

        return @stream_isatty($stream) === true;
    }

    private static function env(string $name): ?string
    {
        $value = getenv($name);

        return $value === false ? null : $value;
    }

    private static function envInt(string $name): ?int
    {
        $value = self::env($name);

        return $value !== null && ctype_digit($value) ? (int) $value : null;
    }
}
