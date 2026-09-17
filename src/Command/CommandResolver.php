<?php

declare(strict_types=1);

namespace Iva\Command;

use Iva\Input\Exception\UnknownCommandException;

final readonly class CommandResolver
{
    public function __construct(
        private CommandTree $tree,
        private SuggestionEngine $suggestions = new SuggestionEngine(),
    ) {}

    /**
     * @param list<string> $tokens raw argv tokens, after the script name
     * @return array{0: list<CommandNode>, 1: list<string>} matched path (root -> leaf)
     *         and the tokens left for the Parser
     */
    public function resolve(array $tokens): array
    {
        /** @var list<CommandNode> $matchedPath */
        $matchedPath = [];
        $count = count($tokens);
        $index = 0;

        while ($index < $count) {
            $token = $tokens[$index];

            if ($token === '--') {
                break;
            }

            if ($token !== '' && $token[0] === '-') {
                break;
            }

            $current = $matchedPath === [] ? null : $matchedPath[count($matchedPath) - 1];
            $child = $current === null
                ? $this->tree->findRoot($token)
                : $current->findChild($token);

            if ($child === null) {
                if ($current === null) {
                    throw new UnknownCommandException(sprintf(
                        'Unknown command "%s".%s',
                        $token,
                        $this->suggestions->format($this->suggestions->suggestCommands($token, $this->tree->roots())),
                    ));
                }

                // A group node has no input of its own, so an unmatched token
                // here is a mistyped subcommand rather than an argument.
                if ($current->isGroup()) {
                    throw new UnknownCommandException(sprintf(
                        'Unknown subcommand "%s" for "%s".%s',
                        $token,
                        $current->path(),
                        $this->suggestions->format($this->suggestions->suggestCommands($token, $current->children())),
                    ));
                }

                break;
            }

            $matchedPath[] = $child;
            $index++;
        }

        if ($matchedPath === []) {
            $first = $tokens[0] ?? '';

            if ($first === '' || $first === '--' || $first[0] === '-') {
                throw new UnknownCommandException('No command given.');
            }

            throw new UnknownCommandException(sprintf('Unknown command "%s".', $first));
        }

        return [$matchedPath, array_values(array_slice($tokens, $index))];
    }
}
