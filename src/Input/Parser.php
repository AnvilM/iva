<?php

declare(strict_types=1);

namespace Iva\Input;

use Iva\Command\SuggestionEngine;
use Iva\Input\Exception\FieldError;
use Iva\Input\Invocation\ArgvEntry;
use Iva\Input\Invocation\EntryForm;
use Iva\Input\Invocation\EntryKind;
use Iva\Input\Invocation\InvocationLog;

/**
 * Turns the tail of argv (after CommandResolver stripped the command path)
 * into raw, untyped values shaped by the Definition.
 *
 * Knows nothing about PHP types: that is InputBinder's job. Every problem is
 * appended to an error list instead of thrown, so one pass reports all of
 * them at once.
 */
final class Parser
{
    public function __construct(
        private readonly SuggestionEngine $suggestions = new SuggestionEngine(),
    ) {}

    /**
     * @param list<RawToken> $rawTokens
     */
    public function parse(array $rawTokens, Definition $definition): ParseResult
    {
        $state = new ParserState();
        $rawTokens = array_values($rawTokens);
        $tokens = array_map(static fn(RawToken $token): string => $token->value, $rawTokens);
        $state->positions = array_map(static fn(RawToken $token): int => $token->position, $rawTokens);

        $count = count($tokens);
        $index = 0;

        while ($index < $count) {
            $token = $tokens[$index];

            $state->position = $state->positions[$index];
            $state->token = $token;
            $state->form = EntryForm::Long;
            $state->cluster = 0;
            $state->valuePosition = null;

            if ($state->terminated) {
                $this->addPositional($token, EntryForm::Terminated, $state);
                $index++;
                continue;
            }

            if ($token === '--') {
                $state->terminated = true;
                $this->addTerminator($state);
                $index++;
                continue;
            }

            if ($this->isLongOption($token)) {
                $index = $this->consumeLongOption($tokens, $index, $definition, $state);
                continue;
            }

            if ($this->isShortCluster($token, $definition)) {
                $index = $this->consumeShortCluster($tokens, $index, $definition, $state);
                continue;
            }

            $this->addPositional($token, EntryForm::Positional, $state);
            $index++;
        }

        $arguments = $this->bindPositionals($state, $definition);
        $options = $state->options;
        $this->applyOptionDefaults($definition, $options);

        return new ParseResult(
            $arguments,
            $options,
            $state->occurrences,
            $state->errors,
            array_keys($state->occurrences),
            new InvocationLog($this->finalizeEntries($state), $tokens, $state->positions[0] ?? 0),
        );
    }

    private function addPositional(string $token, EntryForm $form, ParserState $state): void
    {
        $ordinal = $state->argumentOrdinal++;

        $state->positionals[] = $token;
        $state->positionalEntries[$ordinal] = count($state->entries);
        $state->entries[] = new ArgvEntry(
            EntryKind::Argument,
            '',
            $token,
            $token,
            $state->position,
            count($state->entries),
            $ordinal,
            $form,
        );
    }

    private function addTerminator(ParserState $state): void
    {
        $state->entries[] = new ArgvEntry(
            EntryKind::Terminator,
            '--',
            '--',
            null,
            $state->position,
            count($state->entries),
            0,
            EntryForm::Terminator,
        );
    }

    /**
     * Positional entries are recorded before the Parser knows which declared
     * Argument will claim them; once bindPositionals() has decided, the owner's
     * name is written back so the log can be queried by Argument handle.
     *
     * @return list<ArgvEntry>
     */
    private function finalizeEntries(ParserState $state): array
    {
        $entries = $state->entries;

        foreach ($state->positionalOwners as $ordinal => $name) {
            $at = $state->positionalEntries[$ordinal] ?? null;

            if ($at !== null) {
                $entries[$at] = $entries[$at]->withName($name);
            }
        }

        return $entries;
    }

    private function isLongOption(string $token): bool
    {
        return strlen($token) > 2 && str_starts_with($token, '--');
    }

    /**
     * A `-`-prefixed token is a short cluster unless it is a bare `-`, or a
     * negative number whose leading digit is not a declared shortcut (short
     * options are letters, so `-5` and `-3.14` stay positional).
     */
    private function isShortCluster(string $token, Definition $definition): bool
    {
        if (strlen($token) < 2 || $token[0] !== '-') {
            return false;
        }

        if (preg_match('/^-\d/', $token) === 1 && !$definition->hasShortcut($token[1])) {
            return false;
        }

        return true;
    }

    /**
     * @param list<string> $tokens
     */
    private function consumeLongOption(array $tokens, int $index, Definition $definition, ParserState $state): int
    {
        $token = $tokens[$index];
        $body = substr($token, 2);

        $name = $body;
        $inlineValue = null;
        $equals = strpos($body, '=');

        if ($equals !== false) {
            $name = substr($body, 0, $equals);
            $inlineValue = substr($body, $equals + 1);
        }

        $negated = false;
        $option = $definition->findOptionByLongName($name);

        if ($option === null && str_starts_with($name, 'no-')) {
            $candidate = $definition->findOptionByLongName(substr($name, 3));

            if ($candidate !== null && $candidate->negatable) {
                $option = $candidate;
                $name = $candidate->name;
                $negated = true;
                $state->form = EntryForm::LongNegated;
            }
        }

        if ($option === null) {
            $matches = $definition->matchAbbreviation($name);

            if (count($matches) === 1) {
                $option = $definition->findOptionByLongName($matches[0]);
                $name = $matches[0];
                $state->form = EntryForm::LongAbbreviated;
            } elseif (count($matches) > 1) {
                $state->errors[] = new FieldError(
                    $name,
                    $token,
                    sprintf('Ambiguous option "--%s", candidates: %s.', $name, implode(', ', array_map(
                        static fn(string $candidate): string => '--' . $candidate,
                        $matches,
                    ))),
                );

                return $index + 1;
            }
        }

        if ($option === null) {
            $state->errors[] = new FieldError(
                $name,
                $token,
                $this->unknownOptionMessage('--' . $name, $definition),
            );

            return $index + 1;
        }

        return $this->assign($option, $negated, $inlineValue, $tokens, $index + 1, $state, '--' . $name);
    }

    /**
     * Walks the cluster character by character: `-abc` sets three flags, and
     * the first character needing a value swallows the rest of the cluster
     * (`-c5abc` gives c the value "5abc").
     *
     * @param list<string> $tokens
     */
    private function consumeShortCluster(array $tokens, int $index, Definition $definition, ParserState $state): int
    {
        $token = $tokens[$index];
        $body = substr($token, 1);
        $length = strlen($body);
        $next = $index + 1;

        $state->form = EntryForm::Short;

        for ($position = 0; $position < $length; $position++) {
            $state->cluster = $position;
            $character = $body[$position];
            $option = $definition->findOptionByShortcut($character);

            if ($option === null) {
                $state->errors[] = new FieldError(
                    $character,
                    '-' . $character,
                    $this->unknownOptionMessage('-' . $character, $definition),
                );

                continue;
            }

            if ($option->mode === OptionMode::None) {
                $this->record($option, true, $state, true);

                continue;
            }

            $rest = substr($body, $position + 1);

            if ($rest !== '') {
                $value = str_starts_with($rest, '=') ? substr($rest, 1) : $rest;

                return $this->store($option, $value, $state, '-' . $character, $next);
            }

            return $this->assign($option, false, null, $tokens, $next, $state, '-' . $character);
        }

        return $next;
    }

    /**
     * @param list<string> $tokens
     */
    private function assign(
        Option $option,
        bool $negated,
        ?string $inlineValue,
        array $tokens,
        int $next,
        ParserState $state,
        string $label,
    ): int {
        if ($option->mode === OptionMode::None) {
            if ($inlineValue !== null) {
                $state->errors[] = new FieldError(
                    $option->name,
                    $inlineValue,
                    sprintf('Option "%s" is a flag and does not accept a value.', $label),
                );
            }

            $this->record($option, !$negated, $state, !$negated);

            return $next;
        }

        $value = $inlineValue;

        if ($value === null && isset($tokens[$next]) && !$this->looksLikeOption($tokens[$next])) {
            $value = $tokens[$next];
            $state->valuePosition = $state->positions[$next];
            $next++;
        }

        if ($value === null) {
            if ($option->mode === OptionMode::RequiredValue) {
                $state->errors[] = new FieldError(
                    $option->name,
                    null,
                    sprintf('Option "%s" requires a value.', $label),
                );
            } else {
                $this->record($option, null, $state, null);
            }

            return $next;
        }

        return $this->store($option, $value, $state, $label, $next);
    }

    private function store(Option $option, string $value, ParserState $state, string $label, int $next): int
    {
        if ($option->mode === OptionMode::ArrayValue) {
            $existing = $state->options[$option->name] ?? [];
            $existing = is_array($existing) ? $existing : [];
            $existing[] = $value;
            $this->record($option, $existing, $state, $value);

            return $next;
        }

        if (array_key_exists($option->name, $state->options)) {
            $state->errors[] = new FieldError(
                $option->name,
                $value,
                sprintf(
                    'Option "%s" was specified more than once but does not accept multiple values.',
                    $label,
                ),
            );

            return $next;
        }

        $this->record($option, $value, $state, $value);

        return $next;
    }

    /**
     * Stores the option's running value and appends this one occurrence to
     * the invocation trace.
     *
     * @param string|array<int, string>|bool|null $value the accumulated value kept in the options map
     * @param string|bool|null $entryValue the raw value of just this occurrence
     */
    private function record(Option $option, string|array|bool|null $value, ParserState $state, string|bool|null $entryValue): void
    {
        $state->options[$option->name] = $value;
        $state->occurrences[$option->name] = ($state->occurrences[$option->name] ?? 0) + 1;
        $state->entries[] = new ArgvEntry(
            EntryKind::Option,
            $option->name,
            $state->token,
            $entryValue,
            $state->position,
            count($state->entries),
            $state->optionOrdinal++,
            $state->form,
            $state->cluster,
            $state->valuePosition,
        );
    }

    private function looksLikeOption(string $token): bool
    {
        return strlen($token) > 1 && $token[0] === '-' && preg_match('/^-\d/', $token) !== 1;
    }

    private function unknownOptionMessage(string $token, Definition $definition): string
    {
        $bare = ltrim($token, '-');
        $suggestions = $this->suggestions->suggest($bare, $definition->optionNames());

        if ($suggestions === []) {
            return sprintf('Unknown option "%s".', $token);
        }

        return sprintf(
            'Unknown option "%s". Did you mean: %s?',
            $token,
            implode(', ', array_map(static fn(string $name): string => '--' . $name, $suggestions)),
        );
    }

    /**
     * @return array<string, string|array<int, string>|null>
     */
    private function bindPositionals(ParserState $state, Definition $definition): array
    {
        $arguments = $definition->arguments();
        $positionals = $state->positionals;

        if ($arguments === []) {
            if ($positionals !== []) {
                $state->errors[] = new FieldError(
                    '*',
                    $positionals,
                    sprintf('Unexpected argument(s): %s.', implode(' ', $positionals)),
                );
            }

            return [];
        }

        $values = [];
        $index = 0;
        $count = count($positionals);
        $lastIndex = count($arguments) - 1;

        foreach ($arguments as $position => $argument) {
            if ($argument->variadic && $position === $lastIndex) {
                $values[$argument->name] = array_slice($positionals, $index);

                for ($owned = $index; $owned < $count; $owned++) {
                    $state->positionalOwners[$owned] = $argument->name;
                }

                $index = $count;
                continue;
            }

            if ($index >= $count) {
                if ($argument->optional) {
                    continue;
                }

                $state->errors[] = new FieldError(
                    $argument->name,
                    null,
                    sprintf('Missing required argument "%s".', $argument->name),
                );

                continue;
            }

            $values[$argument->name] = $positionals[$index];
            $state->positionalOwners[$index] = $argument->name;
            $index++;
        }

        if ($index < $count) {
            $extra = array_slice($positionals, $index);
            $state->errors[] = new FieldError(
                '*',
                $extra,
                sprintf('Unexpected argument(s): %s.', implode(' ', $extra)),
            );
        }

        return $values;
    }

    /**
     * @param array<string, string|array<int, string>|bool|null> $options
     */
    private function applyOptionDefaults(Definition $definition, array &$options): void
    {
        foreach ($definition->options() as $name => $option) {
            if (array_key_exists($name, $options)) {
                continue;
            }

            $options[$name] = match ($option->mode) {
                OptionMode::None => false,
                OptionMode::ArrayValue => [],
                OptionMode::RequiredValue, OptionMode::OptionalValue => null,
            };
        }
    }
}
