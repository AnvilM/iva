<?php

declare(strict_types=1);

namespace src\Input\Type;

use Psl\Type\Exception\AssertException;
use Psl\Type\Exception\CoercionException;
use Psl\Type\TypeInterface;
use src\Input\Exception\ValueCoercionException;

/**
 * Escape hatch: wraps any user-supplied Psl\Type descriptor so that arbitrary
 * value objects can be produced straight from argv.
 *
 * @template T
 * @implements InputType<T>
 */
final class CustomType implements InputType
{
    /**
     * @param TypeInterface<T> $type
     */
    public function __construct(
        private readonly TypeInterface $type,
        private readonly string $description,
    ) {}

    /**
     * @return T
     */
    public function coerce(string|array|bool|null $raw): mixed
    {
        try {
            return $this->type->coerce($raw);
        } catch (CoercionException | AssertException $e) {
            throw new ValueCoercionException($e->getMessage(), $this->description);
        }
    }

    public function describe(): string
    {
        return $this->description;
    }
}
