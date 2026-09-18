<?php

declare(strict_types=1);

namespace src\Input;

use src\Input\Exception\FieldError;
use src\Input\Exception\InputValidationException;
use src\Input\Exception\ValueCoercionException;
use src\Input\Type\InputType;

/**
 * Last stage of the pipeline: ParseResult (raw strings) + Definition (target
 * PHP types, declared imperatively via addArgument()/addOption()) -> Input.
 *
 * Coercion failures never abort the pass; they accumulate as FieldErrors and
 * surface together in a single InputValidationException, same as before.
 */
final class InputBinder
{
    public function bind(Definition $definition, ParseResult $result): Input
    {
        /** @var list<FieldError> $errors */
        $errors = [...$result->errors()];

        $arguments = [];

        foreach ($definition->arguments() as $argument) {
            $arguments[$argument->name] = $this->resolveArgument($argument, $result, $errors);
        }

        $options = [];

        foreach ($definition->options() as $option) {
            $options[$option->name] = $this->resolveOption($option, $result, $errors);
        }

        if ($errors !== []) {
            throw new InputValidationException($errors);
        }

        return new Input($arguments, $options);
    }

    /**
     * @param list<FieldError> $errors
     */
    private function resolveArgument(Argument $argument, ParseResult $result, array &$errors): mixed
    {
        $provided = $result->hasArgument($argument->name);
        $emptyValue = $argument->variadic ? [] : null;

        if (!$provided) {
            // The Parser already recorded a "missing required argument" error
            // for this field when applicable; nothing more to do here.
            return $argument->default ?? $emptyValue;
        }

        $raw = $result->argument($argument->name);

        try {
            return $this->coerce($argument->type, $raw);
        } catch (ValueCoercionException $e) {
            $errors[] = new FieldError(
                $argument->name,
                $raw,
                sprintf(
                    'Invalid value for argument "%s": %s (expected %s).',
                    $argument->name,
                    $this->describeRaw($raw),
                    $argument->type->describe(),
                ),
            );

            return $argument->default ?? $emptyValue;
        }
    }

    /**
     * @param list<FieldError> $errors
     */
    private function resolveOption(Option $option, ParseResult $result, array &$errors): mixed
    {
        $provided = $result->wasProvided($option->name);
        $emptyValue = match ($option->mode) {
            OptionMode::None => false,
            OptionMode::ArrayValue => [],
            OptionMode::RequiredValue, OptionMode::OptionalValue => null,
        };

        if (!$provided) {
            return $option->default ?? $emptyValue;
        }

        // Parser always fills every declared option (defaults applied), so
        // this is only ever null for an OptionalValue given without a value.
        $raw = $result->option($option->name);

        if ($raw === null) {
            return $option->default ?? $emptyValue;
        }

        try {
            return $this->coerce($option->type, $raw);
        } catch (ValueCoercionException $e) {
            $errors[] = new FieldError(
                $option->name,
                $raw,
                sprintf(
                    'Invalid value for option "--%s": %s (expected %s).',
                    $option->name,
                    $this->describeRaw($raw),
                    $option->type->describe(),
                ),
            );

            return $option->default ?? $emptyValue;
        }
    }

    /**
     * @param string|array<int, string>|bool|null $raw
     */
    private function coerce(InputType $type, string|array|bool|null $raw): mixed
    {
        return $type->coerce($raw);
    }

    private function describeRaw(mixed $raw): string
    {
        if (is_array($raw)) {
            return '"' . implode('", "', array_map(static fn(mixed $v): string => (string) $v, $raw)) . '"';
        }

        if (is_bool($raw)) {
            return $raw ? 'true' : 'false';
        }

        if ($raw === null) {
            return 'none';
        }

        return sprintf('"%s"', (string) $raw);
    }
}
