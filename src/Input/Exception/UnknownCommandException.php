<?php

declare(strict_types=1);

namespace Iva\Input\Exception;

use Iva\Exception\CliException;
use Iva\ExitCode;

final class UnknownCommandException extends CliException
{
    public function __construct(string $message)
    {
        parent::__construct($message, ExitCode::CommandNotFound);
    }
}
