<?php

declare(strict_types=1);

namespace Iva\Input\Type;

use Iva\Input\Exception\ValueCoercionException;
use Psl\Type;
use Psl\Type\Exception\AssertException;
use Psl\Type\Exception\CoercionException;

/**
 * @template T of \BackedEnum
 * @implements InputType<T>
 */
final class EnumType implements InputType
{
    /**
     * @param class-string<T> $enumClass
     */
    public function __construct(
        private readonly string $enumClass,
    ) {}

    public function coerce(string|array|bool|null $raw): \BackedEnum
    {
        $value = RawValue::scalar($raw, $this->describe());

        if (is_bool($value)) {
            throw new ValueCoercionException('expected a value, got a flag', $this->describe());
        }

        try {
            return Type\backed_enum($this->enumClass)->coerce($value);
        } catch (CoercionException | AssertException) {
            throw new ValueCoercionException(
                sprintf('"%s" is not one of: %s', $value, $this->describe()),
                $this->describe(),
            );
        }
    }

    public function describe(): string
    {
        return implode('|', array_map(
            static fn(\BackedEnum $case): string => (string) $case->value,
            ($this->enumClass)::cases(),
        ));
    }
}
