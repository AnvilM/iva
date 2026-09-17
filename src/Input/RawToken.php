<?php

declare(strict_types=1);

namespace Iva\Input;

final readonly class RawToken
{
    public function __construct(
        public string $value,
        public int $position,
    ) {}
}
