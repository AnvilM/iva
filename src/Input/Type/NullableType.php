<?php

declare(strict_types=1);

namespace src\Input\Type;

/**
 * @template T
 * @implements InputType<T|null>
 */
final class NullableType implements InputType
{
    /**
     * @param InputType<T> $inner
     */
    public function __construct(
        private readonly InputType $inner,
    ) {}

    /**
     * @return T|null
     */
    public function coerce(string|array|bool|null $raw): mixed
    {
        if ($raw === null || $raw === '' || $raw === []) {
            return null;
        }

        return $this->inner->coerce($raw);
    }

    /**
     * @return InputType<T>
     */
    public function inner(): InputType
    {
        return $this->inner;
    }

    public function describe(): string
    {
        return '?' . $this->inner->describe();
    }
}
