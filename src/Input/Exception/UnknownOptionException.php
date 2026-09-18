<?php

declare(strict_types=1);

namespace Iva\Input\Exception;

final class UnknownOptionException extends InputValidationException
{
    public function __construct(string $optionToken)
    {
        parent::__construct([
            new FieldError($optionToken, $optionToken, sprintf('Unknown option "%s".', $optionToken)),
        ]);
    }
}
