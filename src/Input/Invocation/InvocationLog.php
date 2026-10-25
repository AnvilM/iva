<?php

declare(strict_types=1);

namespace Iva\Input\Invocation;

use Iva\Input\Argument;
use Iva\Input\Option;

/**
 * The command line as it was typed: every recognised option occurrence,
 * positional token and `--` marker, in order.
 *
 * Reached through `Input::invocation()`. It is immutable and every narrowing
 * method (options(), of(), before(), ...) returns a new log, so queries chain:
 *
 *   $log = $input->invocation();
 *
 *   $log->precedes($this->force, $this->dryRun);          // was --force typed before --dry-run?
 *   $log->ofAny($this->include, $this->exclude)->values();   // interleaved, in typed order
 *   $log->options()->labels();                            // ['--force', '--tag', '--tag']
 *   $log->after('--')->count();                           // tokens typed after `--`
 *
 * A "target" is always one of:
 *  - an Option or Argument handle (the typed, preferred way: it only matches
 *    entries of that kind);
 *  - a string, matched against the entry name or its label (`'tag'`,
 *    `'--tag'`, `'file'`, `'--'`); a string matches options and arguments alike.
 */
final readonly class InvocationLog implements \Countable, \IteratorAggregate
{
    /** @var list<ArgvEntry> */
    private array $entries;

    /**
     * @param list<ArgvEntry> $entries in typed order
     * @param list<string> $tokens the raw tokens the parser was given (the tail after the command path)
     * @param int $offset how many argv tokens (the command path) preceded $tokens
     */
    public function __construct(
        array $entries = [],
        private array $tokens = [],
        private int $offset = 0,
    ) {
        $this->entries = array_values($entries);
    }

    // ---------------------------------------------------------------- raw data

    /** @return list<ArgvEntry> */
    public function all(): array
    {
        return $this->entries;
    }

    /**
     * The raw tokens handed to the parser: the argv tail after the command
     * path, untouched, `--` and unrecognised tokens included.
     *
     * @return list<string>
     */
    public function tokens(): array
    {
        return $this->tokens;
    }

    /** Number of argv tokens consumed by the command path (`db migrate` = 2). */
    public function offset(): int
    {
        return $this->offset;
    }

    public function count(): int
    {
        return count($this->entries);
    }

    public function isEmpty(): bool
    {
        return $this->entries === [];
    }

    public function getIterator(): \ArrayIterator
    {
        return new \ArrayIterator($this->entries);
    }

    // --------------------------------------------------------------- narrowing

    /** Only the option occurrences. */
    public function options(): self
    {
        return $this->filter(static fn(ArgvEntry $entry): bool => $entry->kind === EntryKind::Option);
    }

    /** Only the positional tokens (including those typed after `--`). */
    public function arguments(): self
    {
        return $this->filter(static fn(ArgvEntry $entry): bool => $entry->kind === EntryKind::Argument);
    }

    /** Only the positional tokens typed after the `--` marker. */
    public function afterTerminator(): self
    {
        return $this->filter(static fn(ArgvEntry $entry): bool => $entry->form === EntryForm::Terminated);
    }

    /** Only the entries that belong to one option/argument. */
    public function of(Option|Argument|string $target): self
    {
        return $this->filter(static fn(ArgvEntry $entry): bool => self::matches($entry, $target));
    }

    /**
     * Only the entries that belong to any of the given options/arguments,
     * interleaved exactly as typed, which is the way to keep the relative
     * order of related options (`--include a --exclude b --include c`).
     */
    public function ofAny(Option|Argument|string ...$targets): self
    {
        return $this->filter(static function (ArgvEntry $entry) use ($targets): bool {
            foreach ($targets as $target) {
                if (self::matches($entry, $target)) {
                    return true;
                }
            }

            return false;
        });
    }

    /** Entries typed before the first occurrence of $target (empty if it was never typed). */
    public function before(Option|Argument|string $target): self
    {
        $at = $this->sequenceOf($target);

        if ($at === null) {
            return $this->filter(static fn(ArgvEntry $entry): bool => false);
        }

        return $this->filter(static fn(ArgvEntry $entry): bool => $entry->sequence < $at);
    }

    /** Entries typed after the first occurrence of $target (empty if it was never typed). */
    public function after(Option|Argument|string $target): self
    {
        $at = $this->sequenceOf($target);

        if ($at === null) {
            return $this->filter(static fn(ArgvEntry $entry): bool => false);
        }

        return $this->filter(static fn(ArgvEntry $entry): bool => $entry->sequence > $at);
    }

    /** Entries typed strictly between the first occurrences of $from and $to. */
    public function between(Option|Argument|string $from, Option|Argument|string $to): self
    {
        $start = $this->sequenceOf($from);
        $end = $this->sequenceOf($to);

        if ($start === null || $end === null) {
            return $this->filter(static fn(ArgvEntry $entry): bool => false);
        }

        return $this->filter(static fn(ArgvEntry $entry): bool => $entry->sequence > $start && $entry->sequence < $end);
    }

    /**
     * @param callable(ArgvEntry): bool $predicate
     */
    public function filter(callable $predicate): self
    {
        return new self(array_values(array_filter($this->entries, $predicate)), $this->tokens, $this->offset);
    }

    public function slice(int $offset, ?int $length = null): self
    {
        return new self(array_slice($this->entries, $offset, $length), $this->tokens, $this->offset);
    }

    // ----------------------------------------------------------------- lookups

    public function has(Option|Argument|string $target): bool
    {
        return $this->first($target) !== null;
    }

    /** First entry, or the first entry of $target. */
    public function first(Option|Argument|string|null $target = null): ?ArgvEntry
    {
        foreach ($this->entries as $entry) {
            if ($target === null || self::matches($entry, $target)) {
                return $entry;
            }
        }

        return null;
    }

    /** Last entry, or the last entry of $target (the one that wins in "last one wins" semantics). */
    public function last(Option|Argument|string|null $target = null): ?ArgvEntry
    {
        for ($i = count($this->entries) - 1; $i >= 0; $i--) {
            if ($target === null || self::matches($this->entries[$i], $target)) {
                return $this->entries[$i];
            }
        }

        return null;
    }

    public function at(int $sequence): ?ArgvEntry
    {
        foreach ($this->entries as $entry) {
            if ($entry->sequence === $sequence) {
                return $entry;
            }
        }

        return null;
    }

    /** How many times $target occurs (`-vvv` -> 3 for verbose). */
    public function times(Option|Argument|string $target): int
    {
        return $this->of($target)->count();
    }

    /**
     * Raw per-occurrence values of $target in typed order (flags give true/false,
     * not-yet-coerced strings otherwise).
     *
     * @return list<string|bool|null>
     */
    public function values(Option|Argument|string $target): array
    {
        return array_map(static fn(ArgvEntry $entry): string|bool|null => $entry->value, $this->of($target)->entries);
    }

    /** Argv position (see {@see ArgvEntry}) of the first occurrence of $target. */
    public function positionOf(Option|Argument|string $target): ?int
    {
        return $this->first($target)?->position;
    }

    /** Place among all entries of the first occurrence of $target. */
    public function sequenceOf(Option|Argument|string $target): ?int
    {
        return $this->first($target)?->sequence;
    }

    /** True when both were typed and the first occurrence of $a came before the first of $b. */
    public function precedes(Option|Argument|string $a, Option|Argument|string $b): bool
    {
        $first = $this->sequenceOf($a);
        $second = $this->sequenceOf($b);

        return $first !== null && $second !== null && $first < $second;
    }

    /** True when both were typed and the first occurrence of $a came after the first of $b. */
    public function follows(Option|Argument|string $a, Option|Argument|string $b): bool
    {
        return $this->precedes($b, $a);
    }

    // ------------------------------------------------------------- projections

    /**
     * Label of every entry in typed order (`['--force', 'file', '--tag', '--tag']`).
     *
     * @return list<string>
     */
    public function labels(): array
    {
        return array_map(static fn(ArgvEntry $entry): string => $entry->label(), $this->entries);
    }

    /**
     * Distinct labels in order of first appearance.
     *
     * @return list<string>
     */
    public function names(): array
    {
        return array_values(array_unique($this->labels()));
    }

    /**
     * @template R
     * @param callable(ArgvEntry): R $mapper
     * @return list<R>
     */
    public function map(callable $mapper): array
    {
        return array_values(array_map($mapper, $this->entries));
    }

    /**
     * Entries grouped by label, groups ordered by first appearance.
     *
     * @return array<string, self>
     */
    public function groupedByName(): array
    {
        $groups = [];

        foreach ($this->entries as $entry) {
            $groups[$entry->label()][] = $entry;
        }

        return array_map(fn(array $group): self => new self($group, $this->tokens, $this->offset), $groups);
    }

    /**
     * @return list<array{sequence: int, kind: string, name: string, token: string, value: string|bool|null, position: int, ordinal: int, form: string, cluster: int, valuePosition: int|null}>
     */
    public function toArray(): array
    {
        return array_map(static fn(ArgvEntry $entry): array => $entry->toArray(), $this->entries);
    }

    /** Human-readable table of the log, meant for debugging and test failures. */
    public function describe(): string
    {
        $lines = [];

        foreach ($this->entries as $entry) {
            $lines[] = sprintf(
                '#%-2d @%-2d %-10s %-18s %-16s %s',
                $entry->sequence,
                $entry->position,
                $entry->kind->name,
                $entry->label(),
                $entry->form->name,
                json_encode($entry->value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: 'null',
            );
        }

        return implode("\n", $lines);
    }

    private static function matches(ArgvEntry $entry, Option|Argument|string $target): bool
    {
        if ($target instanceof Option) {
            return $entry->kind === EntryKind::Option && $entry->name === $target->name;
        }

        if ($target instanceof Argument) {
            return $entry->kind === EntryKind::Argument && $entry->name === $target->name;
        }

        return $entry->name === $target || $entry->label() === $target;
    }
}
