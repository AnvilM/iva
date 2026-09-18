<?php

declare(strict_types=1);

namespace src\Command;

final class CommandBuilder
{
    public function __construct(
        private readonly CommandNode $node,
        private readonly ?self $parent,
    ) {}

    public function subcommand(Command $command): self
    {
        $child = new CommandNode($command);
        $this->node->addChild($child);

        return new self($child, $this);
    }

    public function end(): self
    {
        return $this->parent ?? $this;
    }

    public function node(): CommandNode
    {
        return $this->node;
    }
}
