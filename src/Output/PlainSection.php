<?php

declare(strict_types=1);

namespace src\Output;

/**
 * Used whenever the destination isn't an interactive terminal (redirected
 * to a file, piped, CI logs). Redrawing in place is meaningless there and
 * would only inject escape-code noise into a log, so this degrades to a
 * plain, append-only stream: overwrite() just prints the new content as new
 * lines, prependAbove() prints the line, clear() is a no-op. This is
 * mandatory behavior, not a configuration option (§9.4 point 5).
 */
final class PlainSection implements Section
{
    private int $lastLineCount = 0;

    /**
     * @param \Closure(string): void $sink
     */
    public function __construct(private readonly \Closure $sink)
    {
    }

    public function overwrite(string|array $lines): void
    {
        $lines = is_array($lines) ? $lines : [$lines];

        foreach ($lines as $line) {
            ($this->sink)($line . "\n");
        }

        $this->lastLineCount = count($lines);
    }

    public function prependAbove(string $line): void
    {
        ($this->sink)($line . "\n");
    }

    public function clear(): void
    {
        // Nothing was drawn "in place" to erase; there's nothing to do.
    }

    public function lineCount(): int
    {
        return $this->lastLineCount;
    }
}
