<?php

declare(strict_types=1);

namespace Iva\Help;

use Iva\Output\Formatter\OutputFormatter;
use Iva\Output\Terminal\ColorSupport;
use Iva\Output\Terminal\VisualWidth;

/**
 * Greedy word wrapper that measures what the user will actually see.
 *
 * Widths are taken from the formatted text with `<tag>` markup removed (the
 * very OutputFormatter that renders it decides what is a tag), so a styled or
 * escaped word never throws the layout off. Tags are kept in the returned
 * lines untouched; a style that spans a line break is therefore not carried
 * onto the next line, so keep styled spans inside a single line.
 *
 * Rules:
 *  - spaces and tabs separate words; a word longer than the width is never
 *    split (paths and URLs stay copy-pastable) and simply overflows;
 *  - "\n" starts a new paragraph, and empty paragraphs are preserved;
 *  - a no-break space (U+00A0) glues words together while wrapping and is
 *    turned into a regular space in the result.
 */
final readonly class TextWrapper
{
    public const string NO_BREAK_SPACE = "\u{00A0}";

    public function __construct(
        private OutputFormatter $formatter = new OutputFormatter(),
    ) {}

    /** Visible width of $text in terminal columns, markup excluded. */
    public function width(string $text): int
    {
        return VisualWidth::of($this->formatter->format($text, ColorSupport::None));
    }

    /**
     * @return list<string> at least one line (an empty string for empty text)
     */
    public function wrap(string $text, int $width): array
    {
        $width = max(1, $width);
        $lines = [];

        foreach (explode("\n", str_replace(["\r\n", "\r"], "\n", $text)) as $paragraph) {
            foreach ($this->wrapParagraph($paragraph, $width) as $line) {
                $lines[] = str_replace(self::NO_BREAK_SPACE, ' ', $line);
            }
        }

        return $lines;
    }

    /**
     * @return list<string>
     */
    private function wrapParagraph(string $paragraph, int $width): array
    {
        $words = preg_split('/[ \t]+/', trim($paragraph, " \t"), -1, PREG_SPLIT_NO_EMPTY);

        if ($words === false || $words === []) {
            return [''];
        }

        $lines = [];
        $current = '';
        $currentWidth = 0;

        foreach ($words as $word) {
            $wordWidth = $this->width($word);

            if ($current === '') {
                $current = $word;
                $currentWidth = $wordWidth;

                continue;
            }

            if ($currentWidth + 1 + $wordWidth <= $width) {
                $current .= ' ' . $word;
                $currentWidth += 1 + $wordWidth;

                continue;
            }

            $lines[] = $current;
            $current = $word;
            $currentWidth = $wordWidth;
        }

        $lines[] = $current;

        return $lines;
    }
}
