<?php

declare(strict_types=1);

namespace Iva\Command\Exception;

use Iva\Exception\CliException;
use Iva\ExitCode;

final class CommandExecutionException extends CliException
{
    public function __construct(string $commandPath, \Throwable $previous)
    {
        parent::__construct(
            sprintf('Command "%s" failed: %s', $commandPath, $previous->getMessage()),
            ExitCode::Software,
            $previous,
        );
    }
}
