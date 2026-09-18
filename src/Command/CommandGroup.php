<?php

declare(strict_types=1);

namespace src\Command;

use src\ExitCode;
use src\Input\Input;
use src\Output\Output;

/**
 * A node that only groups subcommands (like `git remote`): it has no input
 * and no behaviour of its own, and running it prints the list of
 * subcommands.
 *
 * Application renders the group help before ever reaching execute(); the
 * implementation here only keeps the class concrete. A subclass still must
 * call setName() (and, typically, setDescription()/setGroup()) from
 * configure().
 */
abstract class CommandGroup extends Command
{
    final public function execute(Input $input, Output $output): int
    {
        return ExitCode::Ok->value;
    }
}
