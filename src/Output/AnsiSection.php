<?php

declare(strict_types=1);

namespace src\Output;

use src\Output\Terminal\Cursor;

/**
 * Cursor-controlled Section for interactive terminals (§9.4).
 *
 * Model: after a render, the cursor always sits at column 0 of the row
 * immediately below the section's last line (and below every section
 * rendered after it, since sections stack top-to-bottom). To redraw this
 * section in place we move the cursor up past this section's own current
 * height *and* every section below it, repaint this section's new content,
 * erase anything stale left over from a shrink with a single "erase to end
 * of screen", then repaint the untouched sections below so they land back
 * exactly where they were.
 */
final class AnsiSection implements Section
{
    /** @var list<string> current rendered content, one entry per row, no trailing newline */
    private array $lines = [];

    private bool $released = false;

    /**
     * @param \Closure(string): void $sink writes raw bytes straight to the stream (no tag formatting)
     */
    public function __construct(
        private readonly SectionManager $manager,
        private readonly \Closure $sink,
    ) {
        $this->manager->register($this);
    }

    public function overwrite(string|array $lines): void
    {
        $lines = is_array($lines) ? array_values($lines) : [$lines];
        $below = $this->manager->below($this);

        $this->moveToTopOf($below);
        $this->paintRows($lines);
        ($this->sink)(Cursor::eraseToEndOfScreen());
        $this->repaint($below);

        $this->lines = $lines;
    }

    public function prependAbove(string $line): void
    {
        $below = $this->manager->below($this);

        $this->moveToTopOf($below);
        $this->paintRows([$line]);
        $this->paintRows($this->lines);
        $this->repaint($below);
    }

    public function clear(): void
    {
        $this->overwrite([]);
    }

    public function lineCount(): int
    {
        return count($this->lines);
    }

    /**
     * Detaches this section from the manager once its owner (ProgressBar,
     * Spinner, ...) is done with it, so later sections no longer treat it
     * as part of the live stack. Not part of the public Section contract —
     * called internally by the widgets built on top of a Section.
     */
    public function release(): void
    {
        if (!$this->released) {
            $this->manager->release($this);
            $this->released = true;
        }
    }

    /**
     * @param list<AnsiSection> $below
     */
    private function moveToTopOf(array $below): void
    {
        $rowsBelow = array_sum(array_map(static fn(self $s): int => $s->lineCount(), $below));
        $rowsToClimb = $this->lineCount() + $rowsBelow;

        if ($rowsToClimb > 0) {
            ($this->sink)(Cursor::up($rowsToClimb));
        }

        ($this->sink)(Cursor::toLineStart());
    }

    /**
     * @param list<AnsiSection> $below
     */
    private function repaint(array $below): void
    {
        foreach ($below as $section) {
            $this->paintRows($section->lines);
        }
    }

    /**
     * @param list<string> $rows
     */
    private function paintRows(array $rows): void
    {
        foreach ($rows as $row) {
            ($this->sink)(Cursor::eraseLine() . $row . "\n");
        }
    }
}
