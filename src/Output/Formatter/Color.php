<?php

declare(strict_types=1);

namespace Iva\Output\Formatter;

/**
 * A color request as written in a tag, independent of what the terminal can
 * actually display -- AnsiRenderer is the one that downgrades a TrueColor
 * request to 256 or 16 colors depending on ColorSupport.
 */
final readonly class Color
{
    private function __construct(
        public ColorMode $mode,
        public ?StandardColor $standard = null,
        public bool $bright = false,
        public ?int $index = null,
        public ?int $red = null,
        public ?int $green = null,
        public ?int $blue = null,
    ) {}

    public static function standard(StandardColor $color, bool $bright = false): self
    {
        return new self(ColorMode::Standard, standard: $color, bright: $bright);
    }

    public static function indexed(int $index): self
    {
        return new self(ColorMode::Indexed, index: max(0, min(255, $index)));
    }

    public static function rgb(int $red, int $green, int $blue): self
    {
        return new self(
            ColorMode::TrueColor,
            red: max(0, min(255, $red)),
            green: max(0, min(255, $green)),
            blue: max(0, min(255, $blue)),
        );
    }

    public static function default(): self
    {
        return new self(ColorMode::Default);
    }

    /**
     * Accepts 'red', 'bright-red'/'light-red', 'gray'/'grey', a 0-255 256-color
     * index, a '#rrggbb' hex triplet, or 'default'. Returns null when the name
     * is not recognised, so the caller can decide how to react.
     */
    public static function parse(string $name): ?self
    {
        $name = strtolower(trim($name));

        if ($name === '' || $name === 'default') {
            return self::default();
        }

        if ($name === 'gray' || $name === 'grey') {
            return self::standard(StandardColor::Black, bright: true);
        }

        if (preg_match('/^#?([0-9a-f]{6})$/', $name, $matches) === 1) {
            $hex = $matches[1];

            return self::rgb(
                (int) hexdec(substr($hex, 0, 2)),
                (int) hexdec(substr($hex, 2, 2)),
                (int) hexdec(substr($hex, 4, 2)),
            );
        }

        if (preg_match('/^\d{1,3}$/', $name) === 1 && (int) $name <= 255) {
            return self::indexed((int) $name);
        }

        $bright = str_starts_with($name, 'bright-') || str_starts_with($name, 'light-');
        $base = $bright ? substr($name, (int) strpos($name, '-') + 1) : $name;
        $standard = StandardColor::fromName($base);

        return $standard === null ? null : self::standard($standard, $bright);
    }

    /**
     * @return array{0: int, 1: int, 2: int}
     */
    public function toRgb(): array
    {
        return match ($this->mode) {
            ColorMode::TrueColor => [$this->red ?? 0, $this->green ?? 0, $this->blue ?? 0],
            ColorMode::Standard => $this->standard?->rgb($this->bright) ?? [0, 0, 0],
            ColorMode::Indexed => Ansi256::toRgb($this->index ?? 0),
            ColorMode::Default => [0, 0, 0],
        };
    }

    public function to256(): int
    {
        return $this->mode === ColorMode::Indexed
            ? ($this->index ?? 0)
            : Ansi256::fromRgb(...$this->toRgb());
    }

    /**
     * @return array{0: StandardColor, 1: bool}
     */
    public function to16(): array
    {
        if ($this->mode === ColorMode::Standard && $this->standard !== null) {
            return [$this->standard, $this->bright];
        }

        return Ansi16::nearest(...$this->toRgb());
    }
}
