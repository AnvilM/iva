<?php

declare(strict_types=1);

namespace src\Input\Exception;

final class AmbiguousOptionException extends InputValidationException
{
    /**
     * @param string[] $candidates
     */
    public function __construct(string $optionToken, array $candidates)
    {
        parent::__construct([
            new FieldError(
                $optionToken,
                $optionToken,
                sprintf('Ambiguous option "%s", candidates: %s.', $optionToken, implode(', ', $candidates)),
            ),
        ]);
    }
}
