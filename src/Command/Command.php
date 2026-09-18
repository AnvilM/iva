<?php

declare(strict_types=1);

namespace src\Command;

use src\Input\Argument;
use src\Input\Definition;
use src\Input\Input;
use src\Input\Option;
use src\Output\Output;

/**
 * Base class for every command. Registration is entirely explicit and
 * imperative — no attributes, no constructor reflection:
 *
 *  - The command's name/description/aliases are declared inside configure(),
 *    using the protected setName()/setDescription()/... methods below.
 *  - Its arguments/options are declared in configure() too, by building an
 *    Argument/Option instance (Argument::string(), Option::int(), ...) and
 *    passing it to addArgument()/addOption(). Both methods return the same
 *    instance, meant to be stashed in a property:
 *
 *      final class GreetCommand extends Command
 *      {
 *          private Argument $name;
 *          private Option $loud;
 *
 *          protected function configure(): void
 *          {
 *              $this->setName('greet');
 *              $this->name = $this->addArgument(Argument::string('name'));
 *              $this->loud = $this->addOption(Option::flag('loud', shortcut: 'l'));
 *          }
 *
 *          public function execute(Input $input, Output $output): int
 *          {
 *              $name = $input->argument($this->name); // string
 *              $loud = $input->option($this->loud);   // bool
 *              // ...
 *          }
 *      }
 *
 *    execute() therefore never reads a value by its string name — it reads
 *    it through the very Argument/Option object configure() built, so a
 *    typo or a wrong type is a static-analysis error, not a runtime
 *    `mixed` that silently does the wrong thing.
 *  - configure() runs once, from the constructor. A subclass that needs its
 *    own constructor (e.g. for dependency injection) must call
 *    parent::__construct() so configure() still runs.
 *  - execute() receives an Input, already parsed and type-coerced according
 *    to what was registered in configure() — there is no more binding of
 *    constructor parameters from argv.
 */
abstract class Command
{
    private ?CommandMetadata $metadata = null;

    private readonly Definition $definition;

    /** @var list<CommandExample> */
    private array $examples = [];

    public function __construct()
    {
        $this->definition = new Definition();
        $this->configure();

        if ($this->metadata === null) {
            throw new \LogicException(sprintf(
                '%s::configure() must call $this->setName() to register the command.',
                static::class,
            ));
        }
    }

    /**
     * Register this command's metadata (setName()/setDescription()/...) and
     * its own arguments/options (addArgument()/addOption()) here.
     */
    abstract protected function configure(): void;

    abstract public function execute(Input $input, Output $output): int;

    protected function setName(string $name): void
    {
        $this->metadata = new CommandMetadata(
            name: $name,
            description: $this->metadata?->description ?? '',
            aliases: $this->metadata?->aliases ?? [],
            hidden: $this->metadata?->hidden ?? false,
            group: $this->metadata?->group ?? null,
        );
    }

    protected function setDescription(string $description): void
    {
        $this->metadata = new CommandMetadata(
            name: $this->requireName(),
            description: $description,
            aliases: $this->metadata?->aliases ?? [],
            hidden: $this->metadata?->hidden ?? false,
            group: $this->metadata?->group ?? null,
        );
    }

    /**
     * @param string[] $aliases
     */
    protected function setAliases(array $aliases): void
    {
        $this->metadata = new CommandMetadata(
            name: $this->requireName(),
            description: $this->metadata?->description ?? '',
            aliases: $aliases,
            hidden: $this->metadata?->hidden ?? false,
            group: $this->metadata?->group ?? null,
        );
    }

    protected function setHidden(bool $hidden = true): void
    {
        $this->metadata = new CommandMetadata(
            name: $this->requireName(),
            description: $this->metadata?->description ?? '',
            aliases: $this->metadata?->aliases ?? [],
            hidden: $hidden,
            group: $this->metadata?->group ?? null,
        );
    }

    protected function setGroup(?string $group): void
    {
        $this->metadata = new CommandMetadata(
            name: $this->requireName(),
            description: $this->metadata?->description ?? '',
            aliases: $this->metadata?->aliases ?? [],
            hidden: $this->metadata?->hidden ?? false,
            group: $group,
        );
    }

    protected function addExample(string $command, string $description = ''): void
    {
        $this->examples[] = new CommandExample($command, $description);
    }

    /**
     * Registers a positional argument built with one of Argument's named
     * constructors (Argument::string(), Argument::enum(), ...) and returns
     * the same instance, so it can be kept as a property and used to read
     * the value back in execute() — see the class docblock.
     *
     * @template T
     * @param Argument<T> $argument
     * @return Argument<T>
     */
    protected function addArgument(Argument $argument): Argument
    {
        $this->definition->addArgument($argument);

        return $argument;
    }

    /**
     * Registers an option built with one of Option's named constructors
     * (Option::flag(), Option::string(), Option::strings(), ...) and
     * returns the same instance, so it can be kept as a property and used
     * to read the value back in execute() — see the class docblock.
     *
     * A persistent option (declared with `persistent: true`) is also
     * inherited by every subcommand, which can read it back through the
     * very same Option instance — typically exposed via a static accessor
     * on the declaring command, e.g.:
     *
     *   final class DeployCommand extends CommandGroup
     *   {
     *       public static function timeoutOption(): Option
     *       {
     *           static $option = null;
     *
     *           return $option ??= Option::int('timeout', persistent: true, default: 30);
     *       }
     *
     *       protected function configure(): void
     *       {
     *           $this->setName('deploy');
     *           $this->addOption(self::timeoutOption());
     *       }
     *   }
     *
     *   // in a subcommand's execute():
     *   $timeout = $input->option(DeployCommand::timeoutOption()); // int
     *
     * @template T
     * @param Option<T> $option
     * @return Option<T>
     */
    protected function addOption(Option $option): Option
    {
        $this->definition->addOption($option);

        return $option;
    }

    private function requireName(): string
    {
        if ($this->metadata === null) {
            throw new \LogicException(sprintf(
                '%s::configure() must call $this->setName() before any other setter.',
                static::class,
            ));
        }

        return $this->metadata->name;
    }

    final public function metadata(): CommandMetadata
    {
        if ($this->metadata === null) {
            throw new \LogicException(sprintf(
                '%s::configure() must call $this->setName() to register the command.',
                static::class,
            ));
        }

        return $this->metadata;
    }

    /**
     * This command's own arguments/options only — no inherited persistent
     * options, no built-in globals. DefinitionFactory aggregates those
     * separately while walking the resolved command path.
     */
    final public function ownDefinition(): Definition
    {
        return $this->definition;
    }

    /** @return list<CommandExample> */
    final public function examples(): array
    {
        return $this->examples;
    }
}
