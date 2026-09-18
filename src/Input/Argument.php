<?php

declare(strict_types=1);

namespace Iva\Input;

use BackedEnum as E;
use Iva\Input\Type\ArrayType;
use Iva\Input\Type\BoolType;
use Iva\Input\Type\FloatType;
use Iva\Input\Type\InputType;
use Iva\Input\Type\IntType;
use Iva\Input\Type\StringType;
use Iva\Input\Type\TypeResolver;

/**
 * A positional argument, declared once (via one of the named constructors
 * below) and handed to Command::addArgument(). The very same instance is
 * then the key used to read the value back in execute():
 *
 *  final class GreetCommand extends Command
 *  {
 *      private Argument $name;
 *
 *      protected function configure(): void
 *      {
 *          $this->setName('greet');
 *          $this->name = $this->addArgument(Argument::string('name'));
 *      }
 *
 *      public function execute(Input $input, Output $output): int
 *      {
 *          $name = $input->argument($this->name); // string, statically known
 *          // ...
 *      }
 *  }
 *
 * There is no `addArgument('name')` / `$input->argument('name')` pair of
 * stringly-typed calls to keep in sync by hand: the object created in
 * configure() *is* the handle used in execute(), so a typo or a mismatched
 * type is caught by PHPStan/Psalm (via the @template below) instead of
 * surfacing at runtime as a missing key or a wrong `mixed`.
 *
 * @template-covariant T
 */
final readonly class Argument
{
    /**
     * @param InputType<T> $type
     * @param T $default
     */
    private function __construct(
        public string $name,
        public InputType $type,
        public string $description,
        public bool $variadic,
        public bool $optional,
        public mixed $default,
    ) {}

    /**
     * @return self<string>
     */
    public static function string(
        string $name,
        string $description = '',
        bool $optional = false,
        ?string $default = null,
    ): self {
        return new self($name, new StringType(), $description, false, $optional || $default !== null, $default);
    }

    /**
     * @return self<int>
     */
    public static function int(
        string $name,
        string $description = '',
        bool $optional = false,
        ?int $default = null,
    ): self {
        return new self($name, new IntType(), $description, false, $optional || $default !== null, $default);
    }

    /**
     * @return self<float>
     */
    public static function float(
        string $name,
        string $description = '',
        bool $optional = false,
        ?float $default = null,
    ): self {
        return new self($name, new FloatType(), $description, false, $optional || $default !== null, $default);
    }

    /**
     * @return self<bool>
     */
    public static function bool(
        string $name,
        string $description = '',
        bool $optional = false,
        ?bool $default = null,
    ): self {
        return new self($name, new BoolType(), $description, false, $optional || $default !== null, $default);
    }

    /**
     * @template E of \BackedEnum
     * @param class-string<E> $enum
     * @param E|null $default
     * @return self<E>
     */
    public static function enum(
        string $name,
        string $enum,
        string $description = '',
        bool $optional = false,
        ?\BackedEnum $default = null,
    ): self {
        return new self(
            $name,
            TypeResolver::enumType($enum),
            $description,
            false,
            $optional || $default !== null,
            $default,
        );
    }

    /**
     * Escape hatch for anything the named constructors above don't cover:
     * a hand-rolled InputType (CustomType, UnionType, NullableType, ...).
     *
     * @template U
     * @param InputType<U> $type
     * @param U $default
     * @return self<U>
     */
    public static function of(
        string $name,
        InputType $type,
        string $description = '',
        bool $optional = false,
        mixed $default = null,
    ): self {
        return new self($name, $type, $description, false, $optional || $default !== null, $default);
    }

    /**
     * Captures every remaining positional token as a list. Must be the last
     * argument registered on the command (Definition enforces this).
     *
     * @template U
     * @param InputType<U> $element the type each individual token is coerced to
     * @return self<list<U>>
     */
    public static function variadic(
        string $name,
        InputType $element = new StringType(),
        string $description = '',
    ): self {
        return new self($name, new ArrayType($element), $description, true, true, []);
    }

    public function typeDescription(): string
    {
        return $this->type->describe();
    }
}
