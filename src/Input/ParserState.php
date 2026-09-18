<?php

declare(strict_types=1);

namespace src\Input;

use src\Input\Exception\FieldError;

/**
 * Mutable scratch space for a single Parser::parse() pass.
 */
final class ParserState
{
    /** @var array<string, string|array<int, string>|bool|null> */
    public array $options = [];

    /** @var array<string, int> */
    public array $occurrences = [];

    /** @var list<string> */
    public array $positionals = [];

    /** @var list<FieldError> */
    public array $errors = [];

    public bool $terminated = false;
}
