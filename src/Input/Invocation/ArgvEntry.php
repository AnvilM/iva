<?php

declare(strict_types=1);

namespace Iva\Input\Invocation;

/**
 * One recognised piece of the command line, in the exact place it was typed.
 *
 * The parser emits one entry per option occurrence (a repeated `--tag a --tag b`
 * is two entries; `-vvv` is three), per positional token, and one for the `--`
 * marker. Entries are immutable value objects; {@see InvocationLog} is the
 * query API on top of them.
 *
 * Positions are 0-based indexes into the argv tokens *after* the script name
 * (so `$argv[$position + 1]` is the token in a real `$argv`). They include the
 * command path: for `app db migrate --force`, `--force` has position 2.
 */
final readonly class ArgvEntry
{
    /**
     * @param string $name Option::$name / Argument::$name this entry belongs to;
     *        `--` for the terminator; empty for a positional token no declared
     *        Argument claimed (an "unexpected argument")
     * @param string $token the raw token it came from (a whole `-vvv` cluster,
     *        the `--tag=a` token, ...)
     * @param string|bool|null $value raw, uncoerced value of *this* occurrence:
     *        a string for a value/positional, true/false for a flag (false for
     *        `--no-x`), null for an option given without its optional value
     * @param int $position argv position of $token
     * @param int $sequence 0-based place among all entries, i.e. the typed order
     * @param int $ordinal 0-based place among entries of the same kind
     *        (the Nth option, the Nth positional)
     * @param int $cluster index of the letter inside a short cluster; 0 otherwise
     * @param int|null $valuePosition argv position of the separate token that
     *        supplied the value (`--tag a`), or null when the value was inline
     *        (`--tag=a`, `-ca`) or the entry has no value
     */
    public function __construct(
        public EntryKind $kind,
        public string $name,
        public string $token,
        public string|bool|null $value,
        public int $position,
        public int $sequence,
        public int $ordinal,
        public EntryForm $form,
        public int $cluster = 0,
        public ?int $valuePosition = null,
    ) {}

    public function isOption(): bool
    {
        return $this->kind === EntryKind::Option;
    }

    public function isArgument(): bool
    {
        return $this->kind === EntryKind::Argument;
    }

    public function isTerminator(): bool
    {
        return $this->kind === EntryKind::Terminator;
    }

    /** True when it came from a short cluster (`-f`, `-vvv`). */
    public function isShort(): bool
    {
        return $this->form === EntryForm::Short;
    }

    /** True for `--no-x` on a negatable flag. */
    public function isNegated(): bool
    {
        return $this->form === EntryForm::LongNegated;
    }

    /** Display name: `--force` for options, the argument name, or `--`. */
    public function label(): string
    {
        return match ($this->kind) {
            EntryKind::Option => '--' . $this->name,
            EntryKind::Terminator => '--',
            EntryKind::Argument => $this->name !== '' ? $this->name : $this->token,
        };
    }

    public function withName(string $name): self
    {
        return new self(
            $this->kind,
            $name,
            $this->token,
            $this->value,
            $this->position,
            $this->sequence,
            $this->ordinal,
            $this->form,
            $this->cluster,
            $this->valuePosition,
        );
    }

    /**
     * @return array{sequence: int, kind: string, name: string, token: string, value: string|bool|null, position: int, ordinal: int, form: string, cluster: int, valuePosition: int|null}
     */
    public function toArray(): array
    {
        return [
            'sequence' => $this->sequence,
            'kind' => $this->kind->name,
            'name' => $this->name,
            'token' => $this->token,
            'value' => $this->value,
            'position' => $this->position,
            'ordinal' => $this->ordinal,
            'form' => $this->form->name,
            'cluster' => $this->cluster,
            'valuePosition' => $this->valuePosition,
        ];
    }
}
