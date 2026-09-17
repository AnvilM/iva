<?php

declare(strict_types=1);

namespace Iva\Input\Exception;

final class MissingRequiredArgumentException extends InputValidationException
{
    public function __construct(string $argumentName)
    {
        parent::__construct([
            new FieldError($argumentName, null, sprintf('Missing required argument "%s".', $argumentName)),
        ]);
    }
}
