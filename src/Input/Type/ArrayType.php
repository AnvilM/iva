<?php

declare(strict_types=1);

namespace src\Input\Type;

/**
 * @template T
 * @implements InputType<list<T>>
 */
final class ArrayType implements InputType
{
    /**
     * @param InputType<T> $element
     */
    public function __construct(
        private readonly InputType $element,
    ) {}

    /**
     * @return list<T>
     */
    public function coerce(string|array|bool|null $raw): array
    {
        $values = RawValue::list($raw);
        $result = [];

        foreach ($values as $value) {
            $result[] = $this->element->coerce($value);
        }

        return $result;
    }

    /**
     * @return InputType<T>
     */
    public function element(): InputType
    {
        return $this->element;
    }

    public function describe(): string
    {
        return $this->element->describe() . '[]';
    }
}
