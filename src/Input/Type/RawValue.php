<?php

declare(strict_types=1);

namespace Iva\Input\Type;

use Iva\Input\Exception\ValueCoercionException;

/**
 * Narrows the raw parser output (string|string[]|bool|null) down to a single
 * scalar for the non-array InputType implementations.
 */
final class RawValue
{
    /**
     * @param string|array<int, string>|bool|null $raw
     */
    public static function scalar(string|array|bool|null $raw, string $expected): string|bool
    {
        if (is_array($raw)) {
            throw new ValueCoercionException(
                sprintf('expected a single %s value, got %d values', $expected, count($raw)),
                $expected,
            );
        }

        if ($raw === null) {
            throw new ValueCoercionException(sprintf('expected a %s value, got none', $expected), $expected);
        }

        return $raw;
    }

    /**
     * @param string|array<int, string>|bool|null $raw
     * @return array<int, string>
     */
    public static function list(string|array|bool|null $raw): array
    {
        if (is_array($raw)) {
            return array_values($raw);
        }

        if ($raw === null) {
            return [];
        }

        if (is_bool($raw)) {
            throw new ValueCoercionException('expected a list of values, got a flag', 'array');
        }

        return [$raw];
    }
}
