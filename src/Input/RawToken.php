<?php

declare(strict_types=1);

namespace src\Input;

final readonly class RawToken
{
    public function __construct(
        public string $value,
        public int $position,
    ) {}
}
