<?php

declare(strict_types=1);

namespace Iva\Input;

use Iva\Command\CommandNode;

/**
 * Builds the Definition used for both parsing and `--help`, by walking the
 * resolved command path: built-in globals first, then persistent options
 * inherited from every ancestor, then the leaf command's own input.
 *
 * Every argument/option here comes straight from a Command instance's own
 * Definition (built imperatively in configure()) — no reflection involved.
 */
final class DefinitionFactory
{
    /**
     * @param list<CommandNode> $path root -> leaf
     */
    public function create(array $path, bool $includeGlobals = true, bool $allowAbbreviation = false): Definition
    {
        $definition = new Definition();
        $definition->allowAbbreviation($allowAbbreviation);

        if ($includeGlobals) {
            GlobalOptions::register($definition);
        }

        if ($path === []) {
            return $definition;
        }

        $leaf = $path[count($path) - 1];

        foreach (array_slice($path, 0, -1) as $ancestor) {
            foreach ($ancestor->command()->ownDefinition()->options() as $option) {
                if ($option->persistent) {
                    $definition->addOption($option->withOrigin(OptionOrigin::Inherited));
                }
            }
        }

        $leafDefinition = $leaf->command()->ownDefinition();

        foreach ($leafDefinition->arguments() as $argument) {
            $definition->addArgument($argument);
        }

        foreach ($leafDefinition->options() as $option) {
            $definition->addOption($option);
        }

        return $definition;
    }
}
