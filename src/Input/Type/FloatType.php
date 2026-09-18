<?php

declare(strict_types=1);

namespace src\Input\Type;

use Psl\Type;
use Psl\Type\Exception\AssertException;
use Psl\Type\Exception\CoercionException;
use src\Input\Exception\ValueCoercionException;
use function Psl\Type;

/**
 * @implements InputType<float>
 */
final class FloatType implements InputType
{
    public function coerce(string|array|bool|null $raw): float
    {
        $value = RawValue::scalar($raw, 'float');

        if (is_string($value) && !is_numeric($value)) {
            throw new ValueCoercionException(sprintf('"%s" is not a valid number', $value), 'float');
        }

        try {
            return Type\float()->coerce($value);
        } catch (CoercionException | AssertException $e) {
            throw new ValueCoercionException($e->getMessage(), 'float');
        }
    }

    public function describe(): string
    {
        return 'float';
    }
}
