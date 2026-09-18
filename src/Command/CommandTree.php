<?php

declare(strict_types=1);

namespace src\Command;

/**
 * Holds the root-level command nodes. Trees are always built explicitly,
 * through Application::command()/CommandBuilder::subcommand() — there is no
 * directory-scanning/attribute-discovery mode, since every command,
 * argument and option in this library is registered imperatively.
 */
final class CommandTree
{
    /** @var array<string, CommandNode> */
    private array $roots = [];

    /** @var array<string, string> alias => primary root name */
    private array $rootAliases = [];

    public function addRoot(CommandNode $node): CommandNode
    {
        $this->roots[$node->metadata()->name] = $node;

        foreach ($node->metadata()->aliases as $alias) {
            $this->rootAliases[$alias] = $node->metadata()->name;
        }

        return $node;
    }

    public function findRoot(string $name): ?CommandNode
    {
        if (isset($this->roots[$name])) {
            return $this->roots[$name];
        }

        $primary = $this->rootAliases[$name] ?? null;

        return $primary !== null ? $this->roots[$primary] : null;
    }

    /**
     * @return CommandNode[]
     */
    public function roots(): array
    {
        return array_values($this->roots);
    }
}
