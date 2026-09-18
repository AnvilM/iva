<?php

declare(strict_types=1);

namespace Iva\Input\Type;

final class ScalarTypes
{
    public static function string(): StringType
    {
        return new StringType();
    }

    public static function int(): IntType
    {
        return new IntType();
    }

    public static function float(): FloatType
    {
        return new FloatType();
    }

    public static function bool(): BoolType
    {
        return new BoolType();
    }

    /**
     * @return InputType<mixed>|null
     */
    public static function fromName(string $name): ?InputType
    {
        /** @var InputType<mixed>|null */
        return match (strtolower($name)) {
            'string' => self::string(),
            'int', 'integer' => self::int(),
            'float', 'double' => self::float(),
            'bool', 'boolean' => self::bool(),
            default => null,
        };
    }
}
