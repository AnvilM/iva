<?php

declare(strict_types=1);

namespace Iva\Input\Exception;

final readonly class FieldError
{
    public function __construct(
        public string $field,
        public mixed $rawValue,
        public string $message,
    ) {}
}
