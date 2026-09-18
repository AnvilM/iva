<?php

declare(strict_types=1);

namespace Iva\Testing;

use Iva\Command\Command;
use Iva\Command\CommandNode;
use Iva\Exception\CliException;
use Iva\ExitCode;
use Iva\Input\DefinitionFactory;
use Iva\Input\Exception\InputValidationException;
use Iva\Input\InputBinder;
use Iva\Input\Lexer;
use Iva\Input\Parser;
use Iva\Output\BufferedOutput;

/**
 * Exercises one command through the same Lexer -> Parser -> InputBinder
 * pipeline Application uses, but against BufferedOutput and without
 * touching a real process — no `exec`, no real stdout.
 *
 * Constructed from a CommandNode when persistent options inherited from
 * ancestors matter to the test; a bare, already-constructed Command instance
 * is enough for a leaf command tested in isolation — it is wrapped in a
 * throwaway root-level node for that case. The same instance is reused on
 * every execute() call, exactly like a real command registered on the tree.
 */
final class CommandTester
{
    private readonly CommandNode $node;
    private readonly Lexer $lexer;
    private readonly Parser $parser;
    private readonly DefinitionFactory $definitionFactory;
    private readonly InputBinder $inputBinder;

    private BufferedOutput $output;
    private BufferedOutput $errorOutput;
    private int $statusCode = 0;

    public function __construct(Command|CommandNode $command)
    {
        $this->node = $command instanceof CommandNode ? $command : new CommandNode($command);

        $this->lexer = new Lexer();
        $this->parser = new Parser();
        $this->definitionFactory = new DefinitionFactory();
        $this->inputBinder = new InputBinder();
        $this->output = new BufferedOutput();
        $this->errorOutput = new BufferedOutput();
    }

    /**
     * @param list<string>|array<string, mixed> $input either raw argv tokens
     *     in order (positional arguments MUST be given this way, since
     *     position is how the parser tells them apart), or a map of option
     *     name => value (bool for a flag, array for a multi-value option,
     *     null for a valueless flag, otherwise scalar) — the two styles can
     *     be mixed by putting positional tokens under integer keys.
     */
    public function execute(array $input): int
    {
        $this->output = new BufferedOutput();
        $this->errorOutput = new BufferedOutput();

        $definition = $this->definitionFactory->create($this->ancestorPath(), includeGlobals: false);
        $tokens = $this->tokensFrom($input);
        $parseResult = $this->parser->parse($this->lexer->tokenize($tokens), $definition);

        try {
            $commandInput = $this->inputBinder->bind($definition, $parseResult);
            $this->statusCode = $this->node->command()->execute($commandInput, $this->output);
        } catch (InputValidationException $e) {
            foreach ($e->errors() as $error) {
                $this->errorOutput->writeln('- ' . $error->message);
            }

            $this->statusCode = $e->exitCode->value;
        } catch (CliException $e) {
            $this->errorOutput->writeln($e->getMessage());
            $this->statusCode = $e->exitCode->value;
        } catch (\Throwable $e) {
            $this->errorOutput->writeln($e->getMessage());
            $this->statusCode = ExitCode::Software->value;
        }

        return $this->statusCode;
    }

    public function getOutput(): string
    {
        return $this->output->fetch();
    }

    public function getErrorOutput(): string
    {
        return $this->errorOutput->fetch();
    }

    public function getStatusCode(): int
    {
        return $this->statusCode;
    }

    /**
     * @return list<CommandNode> root -> leaf
     */
    private function ancestorPath(): array
    {
        $path = [];
        $node = $this->node;

        while ($node !== null) {
            $path[] = $node;
            $node = $node->parent();
        }

        return array_reverse($path);
    }

    /**
     * @param list<string>|array<string, mixed> $input
     * @return list<string>
     */
    private function tokensFrom(array $input): array
    {
        if (array_is_list($input)) {
            /** @var list<string> $input */
            return array_map(static fn(mixed $value): string => (string) $value, $input);
        }

        $tokens = [];

        foreach ($input as $key => $value) {
            if (is_int($key)) {
                $tokens[] = (string) $value;

                continue;
            }

            array_push($tokens, ...$this->optionTokens($key, $value));
        }

        return $tokens;
    }

    /**
     * @return list<string>
     */
    private function optionTokens(string $name, mixed $value): array
    {
        if (is_bool($value)) {
            return [$value ? "--{$name}" : "--no-{$name}"];
        }

        if (is_array($value)) {
            return array_map(static fn(mixed $item): string => "--{$name}=" . (string) $item, $value);
        }

        if ($value === null) {
            return ["--{$name}"];
        }

        return ["--{$name}=" . (string) $value];
    }
}
