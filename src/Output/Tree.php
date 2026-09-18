<?php

declare(strict_types=1);

namespace Iva\Output;

final class Tree
{
    /**
     * @param list<TreeNode> $roots
     */
    public function __construct(
        private readonly Output $output,
        private array $roots = [],
    ) {
    }

    public function addRoot(TreeNode $node): static
    {
        $this->roots[] = $node;

        return $this;
    }

    public function render(): void
    {
        foreach ($this->toLines() as $line) {
            $this->output->writeln($line);
        }
    }

    /**
     * @return list<string>
     */
    public function toLines(): array
    {
        $lines = [];

        foreach ($this->roots as $index => $root) {
            $this->renderNode($root, '', $index === count($this->roots) - 1, $lines);
        }

        return $lines;
    }

    /**
     * @param list<string> $lines
     */
    private function renderNode(TreeNode $node, string $prefix, bool $isLast, array &$lines): void
    {
        $connector = $prefix === '' ? '' : ($isLast ? '└── ' : '├── ');
        $lines[] = $prefix . $connector . $node->label;

        $childPrefix = $prefix === '' ? '' : $prefix . ($isLast ? '    ' : '│   ');
        $children = $node->children;

        foreach ($children as $childIndex => $child) {
            $this->renderNode($child, $childPrefix, $childIndex === count($children) - 1, $lines);
        }
    }
}
