<div align="center">
    <h1 align="center">
        <br>
<svg xmlns="http://www.w3.org/2000/svg" height="48px" viewBox="0 -960 960 960" width="48px" fill="#80ef80"><path d="M450-130v-309h-20q-64 0-120.5-24.5T209-533q-44-45-66.5-104T120-760v-80h78.32Q260-840 317-815.5 374-791 419-746q33 34 54.5 76t30.5 89q7.65-11.9 16.82-22.95Q530-615 540-626q45-45 102-69.5T761.67-720H840v80q0 64-23.98 123T748-413q-45 45-101.56 69T528-320h-18v190h-60Zm1-370q0-61-20-113.5t-55-89q-35-36.5-86-57T180-780q0 63 18.5 115.5T252-575q42 45 90.5 60T451-500Zm59 120q60 0 111-19.5t86-56q35-36.5 54-89T780-660q-60 0-111 20.5T583-583q-43 45-58 94t-15 109Zm0 0Zm-59-120Z"/></svg>        <br>
        Iva
    </h1>
    <p align="center">A modern, strongly-typed CLI framework for PHP 8.4+</p>


[![php-version-shield]][php-version-link]
[![][github-release-shield]][github-release-link]
[![license-shield]][license-link]

[![status-shield]][status-link]
[![ai-written-shield]][ai-written-link]

</div>

> [!IMPORTANT]
> **This entire library — every line of source code, every docblock, and this README — was written by an AI.**
> No human has hand-written the implementation. Review the code carefully before relying on it in production, and please
> open an issue if you spot a bug or a design flaw.

## Overview

**Iva** is a command-line application framework for PHP. It gives you a real, nestable **command tree** (think
`git remote add`), **strongly-typed input DTOs** instead of stringly-typed `$input->getOption('name')` lookups, a
GNU/POSIX-compliant option parser, and a rich **output model** with sections, progress bars, spinners, tables and
trees — all without reflection, attributes, or hidden global state.

Two instances of `Application` can coexist in the same process, commands are built and registered explicitly, and
every value you read back in `execute()` is read through the very same typed `Argument`/`Option` object you declared
in `configure()` — so a typo or a type mismatch is a static-analysis error, not a runtime surprise.

## Features

- **Real command tree** — unlimited nesting (`app db migrate up`), grouping nodes that just list their children
  (like `git remote`), aliases, hidden commands, and automatic "did you mean...?" suggestions for typos.
- **Statically-typed input** — `Argument::string()`, `Option::int()`, `Option::flag()`, etc. return a typed handle
  that you keep as a property and read back with `$input->argument($this->name)` / `$input->option($this->loud)` —
  no magic strings, no `mixed`.
- **GNU/POSIX-style parsing** — long options (`--verbose`), short flags and their bundling (`-vvv`), `--opt=value` /
  `--opt value`, negatable flags (`--no-foo`), variadic arguments, the `--` end-of-options marker, and optional
  option-name abbreviation.
- **Rich output model** — colored/styled text (`<error>`, `<comment>`, custom styles), verbosity levels
  (`-q`, `-v`, `-vv`, `-vvv`), redrawable **sections**, **progress bars** (determinate & indeterminate), **spinners**,
  **tables**, and **trees**, with automatic ANSI/color-support detection.
- **Command lifecycle hooks** — `PreRun` / `PostRun` on a command, and `PersistentPreRun` / `PersistentPostRun` on any
  ancestor group, run like a `finally` block even when the command throws.
- **Auto-generated help** — usage lines, argument/option tables and examples are generated from the same definitions
  used to parse input, for the whole app, a group, or a single command (`--help` / `-h`).
- **First-class testing utilities** — `CommandTester` and `ApplicationTester` run a command through the real
  lexer → parser → binder pipeline against a `BufferedOutput`, with no real process or `exec` involved.
- **Persistent (inherited) options** — declare an option once on a parent group and every subcommand can read it
  back through the same typed handle.

## Requirements

| Requirement | Version |
|-------------|---------|
| PHP         | `^8.4`  |

Iva depends on the [`php-standard-library`](https://github.com/php-standard-library) packages (`foundation`, `type`,
`str`, `io`, `ansi`, `async`, `os`, `date-time`) — installed automatically via Composer.

## Installation

```bash
composer require anvilm/iva
```

## Quick Start

```php
<?php

declare(strict_types=1);

require __DIR__ . '/vendor/autoload.php';

use Iva\Application;
use Iva\Command\Command;
use Iva\Input\Argument;
use Iva\Input\Input;
use Iva\Input\Option;
use Iva\Output\Output;

final class GreetCommand extends Command
{
    private Argument $name;
    private Option $loud;

    protected function configure(): void
    {
        $this->setName('greet');
        $this->setDescription('Greets someone by name');

        $this->name = $this->addArgument(Argument::string('name', 'Who to greet'));
        $this->loud = $this->addOption(Option::flag('loud', shortcut: 'l', description: 'Shout the greeting'));

        $this->addExample('greet Ada --loud', 'Greets Ada, loudly');
    }

    public function execute(Input $input, Output $output): int
    {
        $name = $input->argument($this->name);
        $greeting = sprintf('Hello, %s!', $name);

        $output->writeln($input->option($this->loud) ? strtoupper($greeting) : $greeting);

        return 0;
    }
}

$app = new Application('greet-app', '1.0.0');
$app->command(new GreetCommand());

exit($app->run($argv));
```

```bash
$ php greet.php Ada --loud
HELLO, ADA!
```

## Core Concepts

### Commands

Every command extends `Command`, declares itself in `configure()`, and receives an already-parsed, already-typed
`Input` in `execute()`:

```php
final class DeployCommand extends Command
{
    protected function configure(): void
    {
        $this->setName('deploy');
        $this->setDescription('Deploys the application');
        $this->setAliases(['d']);
    }

    public function execute(Input $input, Output $output): int
    {
        // ...
        return ExitCode::Ok->value;
    }
}
```

### Nested commands & groups

Build a subcommand tree with `CommandBuilder::subcommand()`. A `CommandGroup` has no behaviour of its own — running
it just lists its children, like `git remote`:

```php
final class DbGroup extends CommandGroup
{
    protected function configure(): void
    {
        $this->setName('db');
        $this->setDescription('Database commands');
    }
}

$app->command(new DbGroup())
    ->subcommand(new MigrateCommand())
    ->subcommand(new SeedCommand());
```

```bash
$ php app.php db migrate --help
```

### Arguments & Options

Arguments and options are built through named constructors and kept as typed handles:

```php
protected function configure(): void
{
    $this->setName('copy');

    $this->source = $this->addArgument(Argument::string('source'));
    $this->targets = $this->addArgument(Argument::string('targets', variadic: true));

    $this->force = $this->addOption(Option::flag('force', shortcut: 'f', negatable: true));
    $this->retries = $this->addOption(Option::int('retries', default: 3));
    $this->tags = $this->addOption(Option::strings('tag'));
}
```

Persistent options declared on a parent group are inherited by every subcommand and can be read back through the
same shared handle — see the docblock on `Command::addOption()` for the pattern.

### Output

```php
public function execute(Input $input, Output $output): int
{
    $output->writeln('<comment>Starting...</comment>');

    $bar = $output->progressBar(100);
    $bar->start();
    for ($i = 0; $i < 100; $i++) {
        $bar->advance();
    }
    $bar->finish();

    $output->table()
        ->headers(['Name', 'Status'])
        ->rows([['web', 'running'], ['worker', 'stopped']])
        ->render();

    $spinner = $output->spinner('Connecting...');
    $spinner->start();
    // ...
    $spinner->finish();

    return ExitCode::Ok->value;
}
```

Verbosity is controlled by the built-in `-q`, `-v`, `-vv`, `-vvv` global flags, and `$output->isVerbose()` /
`$output->isDebug()` let a command adapt its own logging.

### Lifecycle hooks

```php
final class DbGroup extends CommandGroup implements PersistentPreRun, PersistentPostRun
{
    public function persistentPreRun(Output $output): void { /* open a connection */ }
    public function persistentPostRun(Output $output, int $exitCode): void { /* always close it */ }
}
```

`PersistentPostRun` runs like a `finally` block: it fires even if the subcommand throws.

### Testing

```php
use Iva\Testing\CommandTester;

$tester = new CommandTester(new GreetCommand());
$tester->execute(['Ada', '--loud']);

$tester->assertSuccessful();
$tester->assertOutputContains('HELLO, ADA!');
```

`ApplicationTester` does the same at the whole-application level, resolving the command path exactly as `run()`
would.

## Supported input styles

| Style                  | Example                         |
|------------------------|---------------------------------|
| Long option            | `--verbose`                     |
| Long option with value | `--timeout=30` / `--timeout 30` |
| Short flag             | `-v`                            |
| Bundled short flags    | `-vvv`                          |
| Negatable flag         | `--no-force`                    |
| Variadic argument      | `cmd file1 file2 file3`         |
| End-of-options marker  | `cmd -- --not-an-option`        |

## Project Structure

```
src/
├── Application.php        # Entry point: parses argv, resolves & runs a command
├── ExitCode.php            # Standard sysexits-style exit codes
├── Command/                # Command tree: Command, CommandGroup, CommandBuilder, lifecycle hooks
├── Input/                  # Lexer, Parser, Definition, Argument, Option, typed InputType system
├── Output/                 # Output, ConsoleOutput, sections, ProgressBar, Spinner, Table, Tree, formatter
├── Help/                   # Auto-generated help & usage lines
└── Testing/                # CommandTester, ApplicationTester
```

## License

The project is distributed under the MIT License. For details, refer to the [LICENSE][license-link] file.

<!-- LINKS -->

[php-version-link]: https://github.com/anvilm/iva

[php-version-shield]: https://img.shields.io/badge/PHP-8.4-blue?logo=php&labelColor=black&style=flat-square

[github-release-link]: https://github.com/anvilm/iva/releases

[github-release-shield]: https://img.shields.io/github/v/release/anvilm/iva?style=flat-square&sort=semver&logo=github&labelColor=black&color=brightgreen

[license-link]: LICENSE

[license-shield]: https://img.shields.io/badge/license-MIT-white?labelColor=black&style=flat-square

[status-link]: https://github.com/anvilm/iva

[status-shield]: https://img.shields.io/badge/status-active-brightgreen?labelColor=black&style=flat-square

[ai-written-link]: #overview

[ai-written-shield]: https://img.shields.io/badge/written%20by-AI-9146FF?labelColor=black&style=flat-square