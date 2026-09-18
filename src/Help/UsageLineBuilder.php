<?php

declare(strict_types=1);

namespace Iva\Help;

use Iva\Command\CommandNode;
use Iva\Input\Definition;

final readonly class UsageLineBuilder
{
    public function build(string $applicationName, CommandNode $node, Definition $definition): string
    {
        $parts = [$applicationName, $node->path()];

        if ($definition->options() !== []) {
            $parts[] = '[options]';
        }

        foreach ($definition->arguments() as $argument) {
            $name = $argument->variadic ? $argument->name . '...' : $argument->name;
            $parts[] = $argument->optional ? sprintf('[%s]', $name) : sprintf('<%s>', $name);
        }

        if ($node->hasChildren()) {
            $parts[] = '[command]';
        }

        return implode(' ', array_filter($parts, static fn(string $part): bool => $part !== ''));
    }
}
