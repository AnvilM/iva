<?php

declare(strict_types=1);

namespace Iva\Input\Type;

use Iva\Input\Exception\ValueCoercionException;

/**
 * @implements InputType<mixed>
 */
final class UnionType implements InputType
{
    /** @var non-empty-list<InputType<mixed>> */
    private readonly array $members;

    /**
     * @param non-empty-list<InputType<mixed>> $members
     */
    public function __construct(array $members)
    {
        $this->members = $members;
    }

    public function coerce(string|array|bool|null $raw): mixed
    {
        foreach ($this->members as $member) {
            try {
                return $member->coerce($raw);
            } catch (ValueCoercionException) {
                continue;
            }
        }

        throw new ValueCoercionException(
            sprintf('value does not match any of: %s', $this->describe()),
            $this->describe(),
        );
    }

    public function describe(): string
    {
        return implode('|', array_map(
            static fn(InputType $type): string => $type->describe(),
            $this->members,
        ));
    }
}
