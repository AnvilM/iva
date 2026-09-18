<?php

declare(strict_types=1);

namespace src\Input;

enum OptionMode
{
    /** Boolean flag: presence = true, `--no-x` (if negatable) = false. */
    case None;

    /** Must be followed by a value, inline (`--opt=v`) or as the next token. */
    case RequiredValue;

    /** May or may not be followed by a value; absent value yields null/default. */
    case OptionalValue;

    /** Each repetition of the flag appends one element to an array. */
    case ArrayValue;
}
