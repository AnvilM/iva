<?php

declare(strict_types=1);

namespace src\Input\Type;

use Psl\Type;
use Psl\Type\Exception\AssertException;
use Psl\Type\Exception\CoercionException;
use src\Input\Exception\ValueCoercionException;
use function Psl\Type;

/**
 * @implements InputType<string>
 */
final class StringType implements InputType
{
    public function coerce(string|array|bool|null $raw): string
    {
        $value = RawValue::scalar($raw, 'string');

        try {
            return Type\string()->coerce($value);
        } catch (CoercionException | AssertException $e) {
            throw new ValueCoercionException($e->getMessage(), 'string');
        }
    }

    public function describe(): string
    {
        return 'string';
    }
}
