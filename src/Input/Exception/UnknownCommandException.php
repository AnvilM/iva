<?php

declare(strict_types=1);

namespace src\Input\Exception;

use src\Exception\CliException;
use src\ExitCode;

final class UnknownCommandException extends CliException
{
    public function __construct(string $message)
    {
        parent::__construct($message, ExitCode::CommandNotFound);
    }
}
