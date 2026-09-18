<?php

declare(strict_types=1);

namespace src\Input\Type;

/**
 * A couple of small helpers shared by Argument/Option's named constructors.
 * There is deliberately no "resolve string|InputType|null into an
 * InputType" entry point here anymore: every InputType a command declares
 * is either produced by a named constructor (Argument::string(), etc.) or
 * passed in explicitly and already typed — never picked at runtime out of
 * a magic string, so there is nothing left to resolve.
 */
final class TypeResolver
{
    public static function wrapArray(InputType $type): InputType
    {
        return $type instanceof ArrayType ? $type : new ArrayType($type);
    }

    /**
     * @template E of \BackedEnum
     * @param class-string<E> $enumClass
     * @return EnumType<E>
     */
    public static function enumType(string $enumClass): EnumType
    {
        if (!enum_exists($enumClass)) {
            throw new \LogicException(sprintf('"%s" is not an enum.', $enumClass));
        }

        $reflection = new \ReflectionEnum($enumClass);

        if (!$reflection->isBacked()) {
            throw new \LogicException(sprintf(
                'Enum "%s" is a pure enum; console input can only build backed enums.',
                $enumClass,
            ));
        }

        /** @var class-string<E> $enumClass */
        return new EnumType($enumClass);
    }
}
