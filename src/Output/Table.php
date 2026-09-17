<?php

declare(strict_types=1);

namespace Iva\Output;

use Iva\Output\Formatter\OutputFormatter;
use Iva\Output\Terminal\ColorSupport;
use Iva\Output\Terminal\VisualWidth;

final class Table
{
    /** @var list<string> */
    private array $headers = [];

    /** @var list<list<string>> */
    private array $rows = [];

    /** @var array<int, ColumnAlign> */
    private array $alignments = [];

    private bool $unicodeBorders;

    public function __construct(
        private readonly Output $output,
        private readonly OutputFormatter $formatter,
        private readonly ColorSupport $colorSupport,
        bool $unicodeBorders = true,
    ) {
        $this->unicodeBorders = $unicodeBorders;
    }

    /**
     * @param list<string> $headers
     */
    public function headers(array $headers): static
    {
        $this->headers = $headers;

        return $this;
    }

    /**
     * @param list<string> $row
     */
    public function row(array $row): static
    {
        $this->rows[] = $row;

        return $this;
    }

    /**
     * @param list<list<string>> $rows
     */
    public function rows(array $rows): static
    {
        foreach ($rows as $row) {
            $this->row($row);
        }

        return $this;
    }

    public function align(int $columnIndex, ColumnAlign $alignment): static
    {
        $this->alignments[$columnIndex] = $alignment;

        return $this;
    }

    public function render(): void
    {
        foreach ($this->toLines() as $line) {
            $this->output->writeln($line);
        }
    }

    /**
     * @return list<string>
     */
    public function toLines(): array
    {
        $columnCount = $this->columnCount();
        $widths = $this->columnWidths($columnCount);
        $chars = $this->borderChars();

        $lines = [];
        $lines[] = $this->borderLine($widths, $chars['topLeft'], $chars['topMid'], $chars['topRight'], $chars['h']);

        if ($this->headers !== []) {
            $lines[] = $this->contentLine($this->headers, $widths, bold: true);
            $lines[] = $this->borderLine(
                $widths,
                $chars['midLeft'],
                $chars['midMid'],
                $chars['midRight'],
                $chars['h'],
            );
        }

        foreach ($this->rows as $row) {
            $lines[] = $this->contentLine($row, $widths, bold: false);
        }

        $lines[] = $this->borderLine(
            $widths,
            $chars['bottomLeft'],
            $chars['bottomMid'],
            $chars['bottomRight'],
            $chars['h'],
        );

        return $lines;
    }

    private function columnCount(): int
    {
        $count = count($this->headers);

        foreach ($this->rows as $row) {
            $count = max($count, count($row));
        }

        return $count;
    }

    /**
     * @return list<int>
     */
    private function columnWidths(int $columnCount): array
    {
        $widths = array_fill(0, $columnCount, 0);

        foreach ($this->headers as $index => $header) {
            $widths[$index] = max($widths[$index], VisualWidth::of($this->formatCell($header, bold: true)));
        }

        foreach ($this->rows as $row) {
            foreach ($row as $index => $cell) {
                $widths[$index] = max($widths[$index], VisualWidth::of($this->formatCell($cell, bold: false)));
            }
        }

        return $widths;
    }

    /**
     * @param list<string> $cells
     * @param list<int> $widths
     */
    private function contentLine(array $cells, array $widths, bool $bold): string
    {
        $v = $this->unicodeBorders ? '│' : '|';
        $parts = [];

        foreach ($widths as $index => $width) {
            $rendered = $this->formatCell($cells[$index] ?? '', $bold);
            $padded = VisualWidth::pad($rendered, $width, padType: $this->padTypeFor($index));

            $parts[] = ' ' . $padded . ' ';
        }

        return $v . implode($v, $parts) . $v;
    }

    /**
     * Runs a cell's raw content — which may itself contain <tag> markup —
     * through the formatter once, up front, so every later width
     * measurement and padding operation works on the same already-rendered
     * (ANSI or plain) string instead of on the pre-formatting tag markup,
     * which would otherwise count as visible width and break alignment.
     */
    private function formatCell(string $raw, bool $bold): string
    {
        $text = $bold ? "<info>{$raw}</info>" : $raw;

        return $this->formatter->format($text, $this->colorSupport);
    }

    private function padTypeFor(int $columnIndex): int
    {
        return match ($this->alignments[$columnIndex] ?? ColumnAlign::Left) {
            ColumnAlign::Left => STR_PAD_RIGHT,
            ColumnAlign::Right => STR_PAD_LEFT,
            ColumnAlign::Center => STR_PAD_BOTH,
        };
    }

    /**
     * @param list<int> $widths
     */
    private function borderLine(array $widths, string $left, string $mid, string $right, string $h): string
    {
        $segments = array_map(static fn(int $width): string => str_repeat($h, $width + 2), $widths);

        return $left . implode($mid, $segments) . $right;
    }

    /**
     * @return array<string, string>
     */
    private function borderChars(): array
    {
        if (!$this->unicodeBorders) {
            return [
                'h' => '-', 'topLeft' => '+', 'topMid' => '+', 'topRight' => '+',
                'midLeft' => '+', 'midMid' => '+', 'midRight' => '+',
                'bottomLeft' => '+', 'bottomMid' => '+', 'bottomRight' => '+',
            ];
        }

        return [
            'h' => '─', 'topLeft' => '┌', 'topMid' => '┬', 'topRight' => '┐',
            'midLeft' => '├', 'midMid' => '┼', 'midRight' => '┤',
            'bottomLeft' => '└', 'bottomMid' => '┴', 'bottomRight' => '┘',
        ];
    }
}
