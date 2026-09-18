<?php

declare(strict_types=1);

namespace src\Input;

final class Definition
{
    /** @var list<Argument> */
    private array $arguments = [];

    /** @var array<string, Option> keyed by long option name */
    private array $options = [];

    /** @var array<string, string> shortcut char => long option name */
    private array $shortcuts = [];

    private bool $abbreviationAllowed = false;

    public function addArgument(Argument $argument): void
    {
        $last = $this->arguments === [] ? null : $this->arguments[count($this->arguments) - 1];

        if ($last !== null && $last->variadic) {
            throw new \LogicException(sprintf(
                'Cannot add argument "%s" after a variadic argument.',
                $argument->name,
            ));
        }

        if (!$argument->optional && $this->hasOptionalArgument()) {
            throw new \LogicException(sprintf(
                'Cannot add required argument "%s" after an optional argument.',
                $argument->name,
            ));
        }

        $this->arguments[] = $argument;
    }

    private function hasOptionalArgument(): bool
    {
        foreach ($this->arguments as $argument) {
            if ($argument->optional) {
                return true;
            }
        }

        return false;
    }

    /**
     * Later additions win only when they are at least as local as what is
     * already registered, so a command's own option can override one
     * inherited from a parent (or a built-in global), never the other way
     * round. An override is flagged so `--help` can point it out.
     */
    public function addOption(Option $option): void
    {
        $existing = $this->options[$option->name] ?? null;

        if ($existing !== null) {
            if ($option->origin->precedence() < $existing->origin->precedence()) {
                return;
            }

            if ($option->origin->precedence() > $existing->origin->precedence()) {
                $option = $option->markingOverride();
            }

            if ($existing->shortcut !== null && ($this->shortcuts[$existing->shortcut] ?? null) === $existing->name) {
                unset($this->shortcuts[$existing->shortcut]);
            }
        }

        $this->options[$option->name] = $option;

        if ($option->shortcut !== null) {
            $owner = $this->shortcuts[$option->shortcut] ?? null;

            if ($owner === null || $owner === $option->name) {
                $this->shortcuts[$option->shortcut] = $option->name;

                return;
            }

            if ($option->origin->precedence() >= $this->options[$owner]->origin->precedence()) {
                $this->shortcuts[$option->shortcut] = $option->name;
            }
        }
    }

    public function allowAbbreviation(bool $allow): void
    {
        $this->abbreviationAllowed = $allow;
    }

    public function abbreviationAllowed(): bool
    {
        return $this->abbreviationAllowed;
    }

    /** @return list<Argument> */
    public function arguments(): array
    {
        return $this->arguments;
    }

    /** @return array<string, Option> */
    public function options(): array
    {
        return $this->options;
    }

    /**
     * @return list<Option>
     */
    public function optionsWithOrigin(OptionOrigin $origin): array
    {
        return array_values(array_filter(
            $this->options,
            static fn(Option $option): bool => $option->origin === $origin,
        ));
    }

    public function findOptionByLongName(string $name): ?Option
    {
        return $this->options[$name] ?? null;
    }

    public function findOptionByShortcut(string $shortcut): ?Option
    {
        $long = $this->shortcuts[$shortcut] ?? null;

        return $long !== null ? $this->options[$long] : null;
    }

    public function hasShortcut(string $shortcut): bool
    {
        return isset($this->shortcuts[$shortcut]);
    }

    /**
     * Unambiguous-prefix resolution, off unless the application opted in.
     *
     * @return list<string> every long name the prefix matches; one entry means
     *         the abbreviation resolved, several mean it is ambiguous
     */
    public function matchAbbreviation(string $prefix): array
    {
        if (!$this->abbreviationAllowed || $prefix === '') {
            return [];
        }

        $matches = [];

        foreach (array_keys($this->options) as $name) {
            if (str_starts_with($name, $prefix)) {
                $matches[] = $name;
            }
        }

        return $matches;
    }

    /**
     * @return list<string>
     */
    public function optionNames(): array
    {
        return array_keys($this->options);
    }
}
