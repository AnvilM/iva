<?php

declare(strict_types=1);

namespace src\Input;

use src\Input\Exception\FieldError;

final class ParseResult
{
    /**
     * @param array<string, string|array<int, string>|null> $arguments
     * @param array<string, string|array<int, string>|bool|null> $options
     * @param array<string, int> $occurrences how many times each option was seen
     * @param list<FieldError> $errors
     * @param list<string> $providedOptions options explicitly present in argv
     */
    public function __construct(
        private readonly array $arguments,
        private readonly array $options,
        private readonly array $occurrences = [],
        private readonly array $errors = [],
        private readonly array $providedOptions = [],
    ) {}

    /** @return array<string, string|array<int, string>|null> */
    public function arguments(): array
    {
        return $this->arguments;
    }

    /** @return array<string, string|array<int, string>|bool|null> */
    public function options(): array
    {
        return $this->options;
    }

    /** @return list<FieldError> */
    public function errors(): array
    {
        return $this->errors;
    }

    public function hasErrors(): bool
    {
        return $this->errors !== [];
    }

    public function hasArgument(string $name): bool
    {
        return array_key_exists($name, $this->arguments);
    }

    /**
     * @return string|array<int, string>|null
     */
    public function argument(string $name): string|array|null
    {
        return $this->arguments[$name] ?? null;
    }

    /**
     * @return string|array<int, string>|bool|null
     */
    public function option(string $name): string|array|bool|null
    {
        return $this->options[$name] ?? null;
    }

    public function wasProvided(string $optionName): bool
    {
        return in_array($optionName, $this->providedOptions, true);
    }

    /**
     * Number of times a flag was seen, so that `-vvv` can raise verbosity
     * without turning the option into a counter type.
     */
    public function occurrences(string $optionName): int
    {
        return $this->occurrences[$optionName] ?? 0;
    }

    public function flag(string $optionName): bool
    {
        return ($this->options[$optionName] ?? false) === true;
    }

    public function withAdditionalErrors(FieldError ...$errors): self
    {
        return new self(
            $this->arguments,
            $this->options,
            $this->occurrences,
            [...$this->errors, ...$errors],
            $this->providedOptions,
        );
    }
}
