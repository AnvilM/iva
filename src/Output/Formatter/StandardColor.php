<?php

declare(strict_types=1);

namespace src\Output\Formatter;

/**
 * The eight base ANSI colors (SGR 30-37 / 40-47), doubled to sixteen through
 * the separate `bright` flag carried alongside a StandardColor on Color.
 */
enum StandardColor: int
{
    case Black = 0;
    case Red = 1;
    case Green = 2;
    case Yellow = 3;
    case Blue = 4;
    case Magenta = 5;
    case Cyan = 6;
    case White = 7;

    public static function fromName(string $name): ?self
    {
        return match ($name) {
            'black' => self::Black,
            'red' => self::Red,
            'green' => self::Green,
            'yellow' => self::Yellow,
            'blue' => self::Blue,
            'magenta' => self::Magenta,
            'cyan' => self::Cyan,
            'white' => self::White,
            default => null,
        };
    }

    /**
     * Approximate RGB of the "normal" (non-bright) intensity, used to map
     * 256-color/truecolor requests down to the 16-color palette.
     *
     * @return array{0: int, 1: int, 2: int}
     */
    public function rgb(bool $bright): array
    {
        $palette = $bright
            ? [
                self::Black->value => [85, 85, 85],
                self::Red->value => [255, 85, 85],
                self::Green->value => [85, 255, 85],
                self::Yellow->value => [255, 255, 85],
                self::Blue->value => [85, 85, 255],
                self::Magenta->value => [255, 85, 255],
                self::Cyan->value => [85, 255, 255],
                self::White->value => [255, 255, 255],
            ]
            : [
                self::Black->value => [0, 0, 0],
                self::Red->value => [170, 0, 0],
                self::Green->value => [0, 170, 0],
                self::Yellow->value => [170, 85, 0],
                self::Blue->value => [0, 0, 170],
                self::Magenta->value => [170, 0, 170],
                self::Cyan->value => [0, 170, 170],
                self::White->value => [170, 170, 170],
            ];

        return $palette[$this->value];
    }
}
