<?php

declare(strict_types=1);

namespace src\Output\Formatter;

/**
 * Tracks nested `<tag>...</tag>` spans while OutputFormatter walks a message,
 * merging each pushed style with whatever is already active so a tag can
 * override just the fields it declares.
 */
final class StyleStack
{
    /** @var list<array{name: string, style: Style}> */
    private array $frames = [];

    public function push(string $name, Style $style): void
    {
        $current = $this->current();
        $this->frames[] = ['name' => $name, 'style' => $current?->mergedWith($style) ?? $style];
    }

    public function pop(): void
    {
        array_pop($this->frames);
    }

    /**
     * Closes the most recent frame with this name. A stray or mismatched
     * closing tag (e.g. `</error>` with nothing of that name open) is
     * tolerated and simply ignored, matching a terminal-friendly formatter
     * rather than failing the whole render over a typo.
     */
    public function popNamed(string $name): void
    {
        for ($i = count($this->frames) - 1; $i >= 0; $i--) {
            if ($this->frames[$i]['name'] === $name) {
                array_splice($this->frames, $i);

                return;
            }
        }
    }

    public function current(): ?Style
    {
        return $this->frames === [] ? null : $this->frames[count($this->frames) - 1]['style'];
    }
}
