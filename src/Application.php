<?php

declare(strict_types=1);

namespace Iva;

use Iva\Command\Command;
use Iva\Command\CommandBuilder;
use Iva\Command\CommandNode;
use Iva\Command\CommandResolver;
use Iva\Command\CommandTree;
use Iva\Command\Lifecycle\PersistentPostRun;
use Iva\Command\Lifecycle\PersistentPreRun;
use Iva\Command\Lifecycle\PostRun;
use Iva\Command\Lifecycle\PreRun;
use Iva\Exception\CliException;
use Iva\Help\HelpGenerator;
use Iva\Help\UsageLineBuilder;
use Iva\Input\Definition;
use Iva\Input\DefinitionFactory;
use Iva\Input\Exception\InputValidationException;
use Iva\Input\GlobalOptions;
use Iva\Input\Input;
use Iva\Input\InputBinder;
use Iva\Input\Lexer;
use Iva\Input\ParseResult;
use Iva\Input\Parser;
use Iva\Output\ConsoleOutput;
use Iva\Output\ErrorConsoleOutput;
use Iva\Output\GlobalOutput;
use Iva\Output\Output;
use Iva\Output\Terminal\ColorSupport;
use Iva\Output\Verbosity;

/**
 * Zero-magic-global-state entry point: an ordinary object, several instances
 * can coexist in one process.
 *
 * Commands are registered explicitly, as already-constructed instances
 * (command()/CommandBuilder::subcommand()). There is no reflection-based
 * instantiation, no command factory, and no service resolver to configure
 * here anymore: nothing builds a Command from argv — the developer builds
 * it (with whatever dependencies it needs) before handing it to the tree,
 * and Application only builds the Input that execute() receives alongside
 * it. GlobalOutput is kept in sync for code that needs the current Output
 * without having it passed in.
 */
final class Application
{
    private readonly CommandTree $tree;
    private readonly CommandResolver $resolver;
    private readonly HelpGenerator $helpGenerator;
    private readonly UsageLineBuilder $usageLineBuilder;
    private readonly Lexer $lexer;
    private readonly Parser $parser;
    private readonly DefinitionFactory $definitionFactory;
    private readonly InputBinder $inputBinder;

    private ?Output $output = null;
    private ?Output $errorOutput = null;

    private bool $optionAbbreviation = false;

    public function __construct(
        private readonly string $name,
        private readonly string $version = '0.0.0',
    ) {
        $this->tree = new CommandTree();
        $this->resolver = new CommandResolver($this->tree);
        $this->usageLineBuilder = new UsageLineBuilder();
        $this->helpGenerator = new HelpGenerator($this->usageLineBuilder);
        $this->lexer = new Lexer();
        $this->parser = new Parser();
        $this->definitionFactory = new DefinitionFactory();
        $this->inputBinder = new InputBinder();
    }

    public function allowOptionAbbreviation(bool $allow = true): void
    {
        $this->optionAbbreviation = $allow;
    }

    public function setOutput(Output $output): void
    {
        $this->output = $output;
    }

    public function setErrorOutput(Output $output): void
    {
        $this->errorOutput = $output;
    }

    public function name(): string
    {
        return $this->name;
    }

    public function version(): string
    {
        return $this->version;
    }

    public function tree(): CommandTree
    {
        return $this->tree;
    }

    /**
     * Registers an already-built Command instance as a top-level command.
     * The instance's configure() has already run by the time it gets here
     * (it runs from Command's own constructor), so its name/description/
     * arguments/options are already known.
     */
    public function command(Command $command): CommandBuilder
    {
        $node = new CommandNode($command);
        $this->tree->addRoot($node);

        return new CommandBuilder($node, null);
    }

    /**
     * @param list<string> $argv the raw argv, including the script name at index 0
     */
    public function run(array $argv): int
    {
        $output = $this->output ??= new ConsoleOutput();
        $errorOutput = $this->errorOutput ??= new ErrorConsoleOutput();

        GlobalOutput::set($output);
        GlobalOutput::setError($errorOutput);

        $tokens = array_values(array_slice($argv, 1));

        return $this->dispatch($tokens, $output, $errorOutput);
    }

    /**
     * @param list<string> $tokens
     */
    private function dispatch(array $tokens, Output $output, Output $errorOutput): int
    {
        if ($tokens === []) {
            $this->helpGenerator->renderApplicationRoot(
                $output,
                $this->name,
                $this->version,
                $this->tree,
                $this->definitionFactory->create([], allowAbbreviation: $this->optionAbbreviation),
            );

            return ExitCode::Ok->value;
        }

        if ($this->isRootLevelFlag($tokens, [GlobalOptions::HELP => 'h'])) {
            $this->helpGenerator->renderApplicationRoot(
                $output,
                $this->name,
                $this->version,
                $this->tree,
                $this->definitionFactory->create([], allowAbbreviation: $this->optionAbbreviation),
            );

            return ExitCode::Ok->value;
        }

        if ($this->isRootLevelFlag($tokens, [GlobalOptions::VERSION => 'V'])) {
            $output->writeln(sprintf('%s %s', $this->name, $this->version));

            return ExitCode::Ok->value;
        }

        // Errors the framework itself raises while turning argv into a
        // command invocation (unknown command, unknown/ambiguous option, ...)
        // are the caller's typos, not bugs: report them and return their exit
        // code. Nothing past this point in dispatch() ever catches what a
        // command throws from its own execute().
        try {
            [$path, $remaining] = $this->resolver->resolve($tokens);
            $node = $path[count($path) - 1];

            $definition = $this->definitionFactory->create($path, allowAbbreviation: $this->optionAbbreviation);
            $parseResult = $this->parser->parse($this->lexer->tokenize($remaining), $definition);
        } catch (CliException $e) {
            $this->renderException($errorOutput, $e);

            return $e->exitCode->value;
        }

        [$output, $errorOutput] = $this->applyGlobalOutputFlags($parseResult, $output, $errorOutput);

        if ($parseResult->flag(GlobalOptions::HELP)) {
            $this->renderHelpFor($output, $node, $definition);

            return ExitCode::Ok->value;
        }

        if ($parseResult->flag(GlobalOptions::VERSION)) {
            $output->writeln(sprintf('%s %s', $this->name, $this->version));

            return ExitCode::Ok->value;
        }

        // A pure grouping node has nothing to execute: list its subcommands.
        if ($node->isGroup()) {
            $this->helpGenerator->renderGroup($output, $this->name, $node, $definition);

            return ExitCode::Ok->value;
        }

        try {
            $input = $this->inputBinder->bind($definition, $parseResult);
        } catch (InputValidationException $e) {
            $this->renderInputValidation($errorOutput, $e, $node, $definition);

            return $e->exitCode->value;
        }

        return $this->executeLifecycle($path, $node, $input, $output);
    }

    /**
     * @return array{0: Output, 1: Output}
     */
    private function applyGlobalOutputFlags(ParseResult $parseResult, Output $output, Output $errorOutput): array
    {
        $verbosity = match (true) {
            $parseResult->occurrences(GlobalOptions::QUIET) > 0 => Verbosity::Quiet,
            $parseResult->occurrences(GlobalOptions::VERBOSE) >= 3 => Verbosity::Debug,
            $parseResult->occurrences(GlobalOptions::VERBOSE) === 2 => Verbosity::VeryVerbose,
            $parseResult->occurrences(GlobalOptions::VERBOSE) === 1 => Verbosity::Verbose,
            default => $output->verbosity(),
        };

        $output = $output->withVerbosity($verbosity);
        $errorOutput = $errorOutput->withVerbosity($verbosity);

        // An explicit --ansi/--no-ansi always wins over auto-detection.
        if ($parseResult->occurrences(GlobalOptions::ANSI) > 0) {
            $forced = $parseResult->option(GlobalOptions::ANSI) === true
                ? ColorSupport::TrueColor
                : ColorSupport::None;
            $output = $output->withColorSupport($forced);
            $errorOutput = $errorOutput->withColorSupport($forced);
        }

        $this->output = $output;
        $this->errorOutput = $errorOutput;

        GlobalOutput::set($output);
        GlobalOutput::setError($errorOutput);

        return [$output, $errorOutput];
    }

    private function renderHelpFor(Output $output, CommandNode $node, Definition $definition): void
    {
        if ($node->isGroup()) {
            $this->helpGenerator->renderGroup($output, $this->name, $node, $definition);

            return;
        }

        $this->helpGenerator->renderCommand($output, $this->name, $node, $definition);
    }

    /**
     * @param list<CommandNode> $path root -> leaf
     */
    private function executeLifecycle(
        array $path,
        CommandNode $leafNode,
        Input $input,
        Output $output,
    ): int {
        $ancestorNodes = array_slice($path, 0, -1);
        $leafCommand = $leafNode->command();

        // persistentPostRun behaves like a `finally`: ancestors get a
        // chance to release resources / close progress bars / print a
        // summary even when the run failed. The exception itself is not
        // handled here — it propagates to the caller untouched.
        $exitCode = ExitCode::Software->value;

        try {
            foreach ($ancestorNodes as $ancestorNode) {
                $instance = $ancestorNode->command();

                if ($instance instanceof PersistentPreRun) {
                    $instance->persistentPreRun($output);
                }
            }

            if ($leafCommand instanceof PreRun) {
                $leafCommand->preRun($output);
            }

            $exitCode = $leafCommand->execute($input, $output);

            if ($leafCommand instanceof PostRun) {
                $leafCommand->postRun($output, $exitCode);
            }
        } finally {
            $this->runPersistentPostRunChain($ancestorNodes, $output, $exitCode);
        }

        return $exitCode;
    }

    /**
     * @param list<CommandNode> $ancestorNodes root -> leaf's parent
     */
    private function runPersistentPostRunChain(array $ancestorNodes, Output $output, int $exitCode): void
    {
        foreach (array_reverse($ancestorNodes) as $ancestorNode) {
            $instance = $ancestorNode->command();

            if ($instance instanceof PersistentPostRun) {
                $instance->persistentPostRun($output, $exitCode);
            }
        }
    }

    private function renderInputValidation(
        Output $errorOutput,
        InputValidationException $exception,
        CommandNode $node,
        Definition $definition,
    ): void {
        $errors = $exception->errors();

        $errorOutput->writeln(sprintf(
            '<error>%s:</error>',
            count($errors) === 1 ? 'Invalid input' : sprintf('Invalid input (%d problems)', count($errors)),
        ), Verbosity::Quiet);

        foreach ($errors as $error) {
            $errorOutput->writeln('  - ' . $error->message, Verbosity::Quiet);
        }

        $errorOutput->writeln('', Verbosity::Quiet);
        $errorOutput->writeln('<comment>Usage:</comment>', Verbosity::Quiet);
        $errorOutput->writeln('  ' . $this->usageLineBuilder->build($this->name, $node, $definition), Verbosity::Quiet);
        $errorOutput->writeln('', Verbosity::Quiet);
        $errorOutput->writeln(
            sprintf('Run "%s %s --help" for more information.', $this->name, $node->path()),
            Verbosity::Quiet,
        );
    }

    private function renderException(Output $errorOutput, CliException $exception): void
    {
        $errorOutput->writeln(sprintf('<e>Error:</e> %s', $exception->getMessage()), Verbosity::Quiet);
    }

    /**
     * True when the very first token is a bare global flag, i.e. before any
     * command path has been given.
     *
     * @param list<string> $tokens
     * @param array<string, string> $flags long name => shortcut
     */
    private function isRootLevelFlag(array $tokens, array $flags): bool
    {
        $first = $tokens[0];

        foreach ($flags as $long => $short) {
            if ($first === '--' . $long || $first === '-' . $short) {
                return true;
            }
        }

        return false;
    }
}
