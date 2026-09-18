<?php

declare(strict_types=1);

namespace src\Command;

/**
 * "Unknown command X, did you mean Y?" — Levenshtein distance combined with a
 * similarity ratio, so both short typos and long near-misses are caught.
 */
final class SuggestionEngine
{
    public function __construct(
        private readonly int $maxDistance = 2,
        private readonly float $minSimilarity = 0.6,
        private readonly int $limit = 3,
    ) {}

    /**
     * @param list<string> $candidates
     * @return list<string>
     */
    public function suggest(string $input, array $candidates): array
    {
        if ($input === '') {
            return [];
        }

        $scored = [];

        foreach ($candidates as $candidate) {
            if ($candidate === $input) {
                continue;
            }

            $distance = levenshtein(strtolower($input), strtolower($candidate));
            $longest = max(strlen($input), strlen($candidate));
            $similarity = $longest === 0 ? 0.0 : 1.0 - ($distance / $longest);

            $prefix = str_starts_with(strtolower($candidate), strtolower($input));

            if (!$prefix && $distance > $this->maxDistance && $similarity < $this->minSimilarity) {
                continue;
            }

            $scored[$candidate] = $prefix ? -1 : $distance;
        }

        asort($scored);

        return array_slice(array_keys($scored), 0, $this->limit);
    }

    /**
     * @param list<CommandNode> $nodes
     * @return list<string>
     */
    public function suggestCommands(string $input, array $nodes): array
    {
        $names = [];

        foreach ($nodes as $node) {
            if ($node->metadata()->hidden) {
                continue;
            }

            $names[] = $node->metadata()->name;

            foreach ($node->metadata()->aliases as $alias) {
                $names[] = $alias;
            }
        }

        return $this->suggest($input, $names);
    }

    /**
     * @param list<string> $suggestions
     */
    public function format(array $suggestions): string
    {
        if ($suggestions === []) {
            return '';
        }

        return sprintf(' Did you mean: %s?', implode(', ', $suggestions));
    }
}
