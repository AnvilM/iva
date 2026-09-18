<?php

declare(strict_types=1);

namespace src\Output;

final class TreeNode
{
    /**
     * @param list<TreeNode> $children
     */
    public function __construct(
        public readonly string $label,
        public readonly array $children = [],
    ) {
    }

    /**
     * @param list<string>|list<TreeNode> $children plain strings are wrapped as leaf nodes
     */
    public static function of(string $label, array $children = []): self
    {
        $normalized = array_map(
            static fn(string|self $child): self => $child instanceof self ? $child : new self($child),
            $children,
        );

        return new self($label, $normalized);
    }
}
