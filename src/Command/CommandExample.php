<?php

declare(strict_types=1);

namespace src\Command;

final readonly class CommandExample
{
    public function __construct(
        public string $command,
        public string $description = '',
    ) {}
}
