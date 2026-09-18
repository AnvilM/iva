<?php

declare(strict_types=1);

namespace Iva\Command;

final class CommandNode
{
    /** @var array<string, CommandNode> */
    private array $children = [];

    /** @var array<string, string> alias => primary child name */
    private array $childAliases = [];

    private ?CommandNode $parent = null;

    public function __construct(
        private readonly Command $command,
    ) {}

    public function command(): Command
    {
        return $this->command;
    }

    public function metadata(): CommandMetadata
    {
        return $this->command->metadata();
    }

    public function parent(): ?self
    {
        return $this->parent;
    }

    public function addChild(self $child): self
    {
        $child->parent = $this;
        $this->children[$child->metadata()->name] = $child;

        foreach ($child->metadata()->aliases as $alias) {
            $this->childAliases[$alias] = $child->metadata()->name;
        }

        return $child;
    }

    public function findChild(string $name): ?self
    {
        if (isset($this->children[$name])) {
            return $this->children[$name];
        }

        $primary = $this->childAliases[$name] ?? null;

        return $primary !== null ? $this->children[$primary] : null;
    }

    /**
     * @return CommandNode[]
     */
    public function children(): array
    {
        return array_values($this->children);
    }

    public function hasChildren(): bool
    {
        return $this->children !== [];
    }

    /**
     * True when the command is a pure grouping node, i.e. it declares no
     * behaviour of its own and exists to host subcommands.
     */
    public function isGroup(): bool
    {
        return $this->command instanceof CommandGroup;
    }

    /**
     * Full dotted path from the tree root to this node, e.g. "example sub subsub".
     */
    public function path(): string
    {
        $segments = [];
        $node = $this;
        while ($node !== null) {
            $segments[] = $node->metadata()->name;
            $node = $node->parent;
        }

        return implode(' ', array_reverse($segments));
    }
}
