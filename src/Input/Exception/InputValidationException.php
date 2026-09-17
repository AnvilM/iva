<?php

declare(strict_types=1);

namespace Iva\Input\Exception;

use Iva\Exception\CliException;
use Iva\ExitCode;

class InputValidationException extends CliException
{
    /**
     * @param FieldError[] $errors
     */
    public function __construct(
        private readonly array $errors,
    ) {
        parent::__construct(self::buildMessage($this->errors), ExitCode::Usage);
    }

    /** @return FieldError[] */
    public function errors(): array
    {
        return $this->errors;
    }

    /**
     * @param FieldError[] $errors
     */
    private static function buildMessage(array $errors): string
    {
        if ($errors === []) {
            return 'Invalid input.';
        }

        return implode("\n", array_map(
            static fn(FieldError $error): string => sprintf('%s: %s', $error->field, $error->message),
            $errors,
        ));
    }
}
