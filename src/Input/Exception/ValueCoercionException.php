<?php

declare(strict_types=1);

namespace Iva\Input\Exception;

/**
 * Thrown by an InputType when a raw argv value cannot be turned into the
 * target PHP type. Never escapes InputBinder: it is caught there and turned
 * into a FieldError so every problem in the input is reported at once.
 */
final class ValueCoercionException extends \RuntimeException
{
    public function __construct(string $message, public readonly string $expected)
    {
        parent::__construct($message);
    }
}
