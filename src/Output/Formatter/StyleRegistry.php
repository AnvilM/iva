<?php

declare(strict_types=1);

namespace src\Output\Formatter;

/**
 * Named styles available as `<name>...</name>` tags. Ships with a sensible
 * default palette and lets the application register or override more via
 * Output::defineStyle().
 */
final class StyleRegistry
{
    /** @var array<string, Style> */
    private array $styles;

    public function __construct()
    {
        $this->styles = self::defaults();
    }

    public function define(string $name, Style $style): void
    {
        $this->styles[$name] = $style;
    }

    public function find(string $name): ?Style
    {
        return $this->styles[$name] ?? null;
    }

    public function clone(): self
    {
        $clone = new self();
        $clone->styles = $this->styles;

        return $clone;
    }

    /**
     * @return array<string, Style>
     */
    private static function defaults(): array
    {
        $error = new Style(
            foreground: Color::standard(StandardColor::White),
            background: Color::standard(StandardColor::Red),
            bold: true,
        );

        return [
            'info' => new Style(foreground: Color::standard(StandardColor::Cyan)),
            'comment' => new Style(foreground: Color::standard(StandardColor::Black, bright: true)),
            'question' => new Style(
                foreground: Color::standard(StandardColor::Black),
                background: Color::standard(StandardColor::Cyan),
            ),
            'error' => $error,
            // `<e>` is the terse alias used throughout the help/usage output.
            'e' => $error,
            'warning' => new Style(
                foreground: Color::standard(StandardColor::Black),
                background: Color::standard(StandardColor::Yellow),
            ),
            'success' => new Style(foreground: Color::standard(StandardColor::Green), bold: true),
        ];
    }
}
