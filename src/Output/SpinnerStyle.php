<?php

declare(strict_types=1);

namespace Iva\Output;

enum SpinnerStyle
{
    case Dots;
    case Line;
    case Arc;
    case Ascii;

    /**
     * @return list<string>
     */
    public function frames(): array
    {
        return match ($this) {
            self::Dots => ['⠋', '⠙', '⠹', '⠸', '⠼', '⠴', '⠦', '⠧', '⠇', '⠏'],
            self::Line => ['-', '\\', '|', '/'],
            self::Arc => ['◜', '◠', '◝', '◞', '◡', '◟'],
            self::Ascii => ['|', '/', '-', '\\'],
        };
    }

    /**
     * Braille dots need a unicode-capable locale/terminal; everything else
     * here is safe to fall back to plain ASCII for.
     */
    public static function default(bool $unicodeSupported): self
    {
        return $unicodeSupported ? self::Dots : self::Ascii;
    }
}
