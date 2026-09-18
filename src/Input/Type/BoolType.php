<?php

declare(strict_types=1);

namespace src\Input\Type;

use src\Input\Exception\ValueCoercionException;

/**
 * @implements InputType<bool>
 */
final class BoolType implements InputType
{
    private const array TRUTHY = ['1', 'true', 'yes', 'y', 'on'];
    private const array FALSY = ['0', 'false', 'no', 'n', 'off', ''];

    public function coerce(string|array|bool|null $raw): bool
    {
        if ($raw === null) {
            return false;
        }

        $value = RawValue::scalar($raw, 'bool');

        if (is_bool($value)) {
            return $value;
        }

        $normalized = strtolower(trim($value));

        if (in_array($normalized, self::TRUTHY, true)) {
            return true;
        }

        if (in_array($normalized, self::FALSY, true)) {
            return false;
        }

        throw new ValueCoercionException(sprintf('"%s" is not a valid boolean', $value), 'bool');
    }

    public function describe(): string
    {
        return 'bool';
    }
}
