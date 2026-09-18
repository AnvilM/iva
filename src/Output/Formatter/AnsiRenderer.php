<?php

declare(strict_types=1);

namespace Iva\Output\Formatter;

use Iva\Output\Terminal\ColorSupport;

/**
 * Turns a Style into ANSI SGR escape sequences, degrading colors to whatever
 * the target terminal actually supports (truecolor -> 256 -> 16 -> none).
 */
final readonly class AnsiRenderer
{
    private const string ESCAPE = "\x1b[";
    private const string RESET = "\x1b[0m";

    public function apply(Style $style, string $text, ColorSupport $support): string
    {
        if ($text === '' || $support === ColorSupport::None || $style->isEmpty()) {
            return $text;
        }

        $codes = $this->codes($style, $support);

        if ($codes === []) {
            return $text;
        }

        return self::ESCAPE . implode(';', $codes) . 'm' . $text . self::RESET;
    }

    /**
     * @return list<int|string>
     */
    private function codes(Style $style, ColorSupport $support): array
    {
        $codes = [];

        if ($style->bold) {
            $codes[] = 1;
        }

        if ($style->dim) {
            $codes[] = 2;
        }

        if ($style->italic) {
            $codes[] = 3;
        }

        if ($style->underline) {
            $codes[] = 4;
        }

        if ($style->blink) {
            $codes[] = 5;
        }

        if ($style->reverse) {
            $codes[] = 7;
        }

        if ($style->hidden) {
            $codes[] = 8;
        }

        if ($style->strikethrough) {
            $codes[] = 9;
        }

        if ($style->foreground !== null) {
            array_push($codes, ...$this->colorCodes($style->foreground, false, $support));
        }

        if ($style->background !== null) {
            array_push($codes, ...$this->colorCodes($style->background, true, $support));
        }

        return $codes;
    }

    /**
     * @return list<int|string>
     */
    private function colorCodes(Color $color, bool $background, ColorSupport $support): array
    {
        if ($color->mode === ColorMode::Default) {
            return [$background ? 49 : 39];
        }

        return match ($support) {
            ColorSupport::None => [],
            ColorSupport::Ansi16 => $this->ansi16Codes($color, $background),
            ColorSupport::Ansi256 => $this->ansi256Codes($color, $background),
            ColorSupport::TrueColor => $this->trueColorCodes($color, $background),
        };
    }

    /**
     * @return list<int>
     */
    private function ansi16Codes(Color $color, bool $background): array
    {
        [$standard, $bright] = $color->to16();
        $base = $background ? 40 : 30;
        $brightBase = $background ? 100 : 90;

        return [($bright ? $brightBase : $base) + $standard->value];
    }

    /**
     * @return list<int>
     */
    private function ansi256Codes(Color $color, bool $background): array
    {
        return [$background ? 48 : 38, 5, $color->to256()];
    }

    /**
     * @return list<int>
     */
    private function trueColorCodes(Color $color, bool $background): array
    {
        [$red, $green, $blue] = $color->toRgb();

        return [$background ? 48 : 38, 2, $red, $green, $blue];
    }
}
