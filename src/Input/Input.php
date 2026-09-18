<?php

declare(strict_types=1);

namespace src\Input;

/**
 * Fully parsed, type-coerced input handed to Command::execute(), built by
 * InputBinder from a ParseResult against the resolved Definition. Every
 * value has already gone through the InputType of the Argument/Option
 * registered via addArgument()/addOption() — execute() never touches raw
 * strings.
 *
 * There is no `argument(string $name)` / `option(string $name)` pair here:
 * a value is read back through the very Argument/Option instance that
 * declared it (see Command's docblock), so the return type is genuinely
 * known at the call site — an `Argument<int>` gives you an `int`, an
 * `Option<DeployStrategy>` gives you a `DeployStrategy`, statically, not by
 * convention or a `@var` docblock the type checker can't verify.
 */
final readonly class Input
{
    /**
     * @param array<string, mixed> $arguments value per Argument::$name
     * @param array<string, mixed> $options value per Option::$name
     */
    public function __construct(
        private array $arguments,
        private array $options,
    ) {}

    /**
     * @template T
     * @param Argument<T> $argument the very instance returned by addArgument()
     * @return T
     */
    public function argument(Argument $argument): mixed
    {
        if (!array_key_exists($argument->name, $this->arguments)) {
            throw new \InvalidArgumentException(sprintf(
                'Argument "%s" was not registered on this command\'s Definition.',
                $argument->name,
            ));
        }

        /** @var T */
        return $this->arguments[$argument->name];
    }

    /**
     * @template T
     * @param Option<T> $option the very instance returned by addOption()
     * @return T
     */
    public function option(Option $option): mixed
    {
        if (!array_key_exists($option->name, $this->options)) {
            throw new \InvalidArgumentException(sprintf(
                'Option "--%s" was not registered on this command\'s Definition.',
                $option->name,
            ));
        }

        /** @var T */
        return $this->options[$option->name];
    }

    /** Shortcut for a boolean flag option. */
    public function flag(Option $option): bool
    {
        return $this->option($option) === true;
    }
}
