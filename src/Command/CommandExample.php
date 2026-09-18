<?php

declare(strict_types=1);

namespace Iva\Command;

final readonly class CommandExample
{
    public function __construct(
        public string $command,
        public string $description = '',
    ) {}
}
