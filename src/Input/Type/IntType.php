<?php

declare(strict_types=1);

namespace src\Input\Type;

use Psl\Type;
use Psl\Type\Exception\AssertException;
use Psl\Type\Exception\CoercionException;
use src\Input\Exception\ValueCoercionException;
use function Psl\Type;

/**
 * @implements InputType<int>
 */
final class IntType implements InputType
{
    public function coerce(string|array|bool|null $raw): int
    {
        $value = RawValue::scalar($raw, 'int');

        if (is_string($value) && !preg_match('/^[+-]?\d+$/', $value)) {
            throw new ValueCoercionException(sprintf('"%s" is not a valid integer', $value), 'int');
        }

        try {
            return Type\int()->coerce($value);
        } catch (CoercionException | AssertException $e) {
            throw new ValueCoercionException($e->getMessage(), 'int');
        }
    }

    public function describe(): string
    {
        return 'int';
    }
}
