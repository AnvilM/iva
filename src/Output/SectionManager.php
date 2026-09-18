<?php

declare(strict_types=1);

namespace Iva\Output;

/**
 * Owned by one Output sink (one real stream). Every AnsiSection created from
 * that sink registers itself here in creation order — top of the screen
 * first, most recently opened section at the bottom — so that redrawing a
 * section from the middle of the stack knows how many rows of *other*
 * sections sit beneath it and must be moved past (and reprinted) as part of
 * the same cursor move.
 *
 * This object is carried over verbatim across AbstractOutput::withConfig()
 * calls (see the $formatter parallel in AbstractOutput) because it reflects
 * real, currently-drawn terminal state, not per-instance configuration.
 */
final class SectionManager
{
    /** @var list<AnsiSection> top of screen -> bottom of screen */
    private array $sections = [];

    public function register(AnsiSection $section): void
    {
        $this->sections[] = $section;
    }

    public function release(AnsiSection $section): void
    {
        $index = array_search($section, $this->sections, true);

        if ($index !== false) {
            array_splice($this->sections, $index, 1);
        }
    }

    /**
     * @return list<AnsiSection> the sections rendered below $section, in
     *     top-to-bottom order, i.e. the ones a redraw of $section must move
     *     past and reprint.
     */
    public function below(AnsiSection $section): array
    {
        $index = array_search($section, $this->sections, true);

        if ($index === false) {
            return [];
        }

        return array_values(array_slice($this->sections, $index + 1));
    }
}
