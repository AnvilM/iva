<?php

declare(strict_types=1);

namespace src\Output;

/**
 * A region of the terminal that can be redrawn in place, without the
 * "scroll of duplicate lines" naive progress-bar implementations produce.
 *
 * ProgressBar and Spinner are both built on top of a Section rather than
 * writing directly through Output.
 */
interface Section
{
    /**
     * Replaces the section's entire content and repaints it on the spot.
     *
     * @param string|list<string> $lines
     */
    public function overwrite(string|array $lines): void;

    /**
     * Inserts a line above the section without disturbing its own content —
     * used for ordinary log messages that need to "prop up" a progress bar
     * or spinner pinned to the bottom of the screen.
     */
    public function prependAbove(string $line): void;

    /** Erases the section's visible content. */
    public function clear(): void;

    /** How many terminal rows the section currently occupies. */
    public function lineCount(): int;
}
