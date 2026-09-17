<?php

declare(strict_types=1);

namespace Iva\Exception;

use Iva\ExitCode;

abstract class CliException extends \RuntimeException
{
    public function __construct(
        string $message,
        public readonly ExitCode $exitCode,
        ?\Throwable $previous = null,
    ) {
        parent::__construct($message, $this->exitCode->value, $previous);
    }
}
