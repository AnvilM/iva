<?php

declare(strict_types=1);

namespace src\Input;

use BackedEnum as E;
use src\Input\Type\ArrayType;
use src\Input\Type\BoolType;
use src\Input\Type\FloatType;
use src\Input\Type\InputType;
use src\Input\Type\IntType;
use src\Input\Type\StringType;
use src\Input\Type\TypeResolver;

/**
 * A named option (`--foo`, `-f`), declared once via one of the named
 * constructors below and handed to Command::addOption(). Just like
 * {@see Argument}, the same instance is the typed key used to read the
 * value back in execute() — see that class's docblock for the full
 * rationale.
 *
 * Each named constructor only exposes the parameters that make sense for
 * that kind of option, so invalid combinations (a negatable option that
 * also takes a value, a flag with a "required value" mode, ...) simply
 * cannot be constructed, instead of being validated at runtime.
 *
 * @template-covariant T
 */
final readonly class Option
{
    /**
     * @param InputType<T> $type
     * @param T $default
     */
    private function __construct(
        public string $name,
        public ?string $shortcut,
        public OptionMode $mode,
        public InputType $type,
        public string $description,
        public bool $negatable,
        public bool $persistent,
        public mixed $default,
        public ?string $valueName,
        public OptionOrigin $origin = OptionOrigin::Local,
        public bool $overridesInherited = false,
    ) {}

    /**
     * A boolean flag: `--foo` sets it to true; `--no-foo` (when $negatable)
     * sets it to false.
     *
     * @return self<bool>
     */
    public static function flag(
        string $name,
        ?string $shortcut = null,
        string $description = '',
        bool $negatable = true,
        bool $persistent = false,
        bool $default = false,
    ): self {
        self::assertValidShortcut($shortcut, $name);

        return new self(
            $name,
            $shortcut,
            OptionMode::None,
            new BoolType(),
            $description,
            $negatable,
            $persistent,
            $default,
            valueName: null,
        );
    }

    /**
     * @return self<?string>
     */
    public static function string(
        string $name,
        ?string $shortcut = null,
        string $description = '',
        bool $persistent = false,
        ?string $default = null,
        ?string $valueName = null,
        bool $valueRequired = true,
    ): self {
        self::assertValidShortcut($shortcut, $name);

        return new self(
            $name,
            $shortcut,
            $valueRequired ? OptionMode::RequiredValue : OptionMode::OptionalValue,
            new StringType(),
            $description,
            negatable: false,
            persistent: $persistent,
            default: $default,
            valueName: $valueName,
        );
    }

    /**
     * @return self<?int>
     */
    public static function int(
        string $name,
        ?string $shortcut = null,
        string $description = '',
        bool $persistent = false,
        ?int $default = null,
        ?string $valueName = null,
        bool $valueRequired = true,
    ): self {
        self::assertValidShortcut($shortcut, $name);

        return new self(
            $name,
            $shortcut,
            $valueRequired ? OptionMode::RequiredValue : OptionMode::OptionalValue,
            new IntType(),
            $description,
            negatable: false,
            persistent: $persistent,
            default: $default,
            valueName: $valueName,
        );
    }

    /**
     * @return self<?float>
     */
    public static function float(
        string $name,
        ?string $shortcut = null,
        string $description = '',
        bool $persistent = false,
        ?float $default = null,
        ?string $valueName = null,
        bool $valueRequired = true,
    ): self {
        self::assertValidShortcut($shortcut, $name);

        return new self(
            $name,
            $shortcut,
            $valueRequired ? OptionMode::RequiredValue : OptionMode::OptionalValue,
            new FloatType(),
            $description,
            negatable: false,
            persistent: $persistent,
            default: $default,
            valueName: $valueName,
        );
    }

    /**
     * @template E of \BackedEnum
     * @param class-string<E> $enum
     * @param E|null $default
     * @return self<E|null>
     */
    public static function enum(
        string $name,
        string $enum,
        ?string $shortcut = null,
        string $description = '',
        bool $persistent = false,
        ?\BackedEnum $default = null,
        ?string $valueName = null,
    ): self {
        self::assertValidShortcut($shortcut, $name);

        return new self(
            $name,
            $shortcut,
            OptionMode::RequiredValue,
            TypeResolver::enumType($enum),
            $description,
            negatable: false,
            persistent: $persistent,
            default: $default,
            valueName: $valueName,
        );
    }

    /**
     * Repeatable option: each occurrence (`--tag=a --tag=b`) appends one
     * element to the resulting list.
     *
     * @template U
     * @param InputType<U> $element the type each individual occurrence is coerced to
     * @return self<list<U>>
     */
    public static function list(
        string $name,
        InputType $element,
        ?string $shortcut = null,
        string $description = '',
        bool $persistent = false,
        ?string $valueName = null,
    ): self {
        self::assertValidShortcut($shortcut, $name);

        return new self(
            $name,
            $shortcut,
            OptionMode::ArrayValue,
            new ArrayType($element),
            $description,
            negatable: false,
            persistent: $persistent,
            default: [],
            valueName: $valueName,
        );
    }

    /**
     * Sugar for {@see self::list()} with a string element — the common case
     * (`--service=api --service=worker`).
     *
     * @return self<list<string>>
     */
    public static function strings(
        string $name,
        ?string $shortcut = null,
        string $description = '',
        bool $persistent = false,
        ?string $valueName = null,
    ): self {
        return self::list($name, new StringType(), $shortcut, $description, $persistent, $valueName);
    }

    /**
     * Escape hatch for anything the named constructors above don't cover:
     * a hand-rolled InputType together with an explicit OptionMode.
     *
     * @template U
     * @param InputType<U> $type
     * @param U $default
     * @return self<U>
     */
    public static function of(
        string $name,
        InputType $type,
        OptionMode $mode = OptionMode::RequiredValue,
        ?string $shortcut = null,
        string $description = '',
        bool $persistent = false,
        mixed $default = null,
        ?string $valueName = null,
    ): self {
        self::assertValidShortcut($shortcut, $name);

        return new self(
            $name,
            $shortcut,
            $mode,
            $type,
            $description,
            negatable: false,
            persistent: $persistent,
            default: $default,
            valueName: $valueName,
        );
    }

    /**
     * Later additions win only when at least as local as what is already
     * registered (see Definition::addOption()); this records that this
     * instance replaced one inherited from a parent, so `--help` can point
     * it out.
     */
    public function markingOverride(): self
    {
        return new self(
            $this->name,
            $this->shortcut,
            $this->mode,
            $this->type,
            $this->description,
            $this->negatable,
            $this->persistent,
            $this->default,
            $this->valueName,
            $this->origin,
            overridesInherited: true,
        );
    }

    public function withOrigin(OptionOrigin $origin): self
    {
        return new self(
            $this->name,
            $this->shortcut,
            $this->mode,
            $this->type,
            $this->description,
            $this->negatable,
            $this->persistent,
            $this->default,
            $this->valueName,
            $origin,
            $this->overridesInherited,
        );
    }

    public function acceptsValue(): bool
    {
        return $this->mode !== OptionMode::None;
    }

    public function displayValueName(): string
    {
        return $this->valueName ?? strtoupper(str_replace('-', '_', $this->name));
    }

    public function typeDescription(): string
    {
        return $this->type->describe();
    }

    private static function assertValidShortcut(?string $shortcut, string $name): void
    {
        if ($shortcut !== null && strlen($shortcut) !== 1) {
            throw new \LogicException(sprintf(
                'Shortcut "%s" for option "--%s" must be exactly one character.',
                $shortcut,
                $name,
            ));
        }
    }
}
