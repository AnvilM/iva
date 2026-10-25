<?php

declare(strict_types=1);

namespace Iva\Input;

use Iva\Input\Exception\FieldError;
use Iva\Input\Invocation\ArgvEntry;
use Iva\Input\Invocation\EntryForm;

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

    /** @var list<ArgvEntry> every recognised option/positional/`--`, in typed order */
    public array $entries = [];

    /** @var list<int> argv position of each token handed to the parser */
    public array $positions = [];

    /** @var array<int, int> positional ordinal => index of its entry in $entries */
    public array $positionalEntries = [];

    /** @var array<int, string> positional ordinal => name of the Argument that claimed it */
    public array $positionalOwners = [];

    public int $optionOrdinal = 0;

    public int $argumentOrdinal = 0;

    // Context of the token currently being consumed, read by Parser::record().
    public int $position = 0;

    public string $token = '';

    public EntryForm $form = EntryForm::Long;

    public int $cluster = 0;

    public ?int $valuePosition = null;
}
