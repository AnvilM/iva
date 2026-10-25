<?php

declare(strict_types=1);

namespace Iva\Input\Invocation;

/**
 * The syntactic shape an {@see ArgvEntry} had on the command line.
 */
enum EntryForm
{
    /** `--name`, `--name=value` or `--name value`. */
    case Long;

    /** `--no-name` on a negatable flag. */
    case LongNegated;

    /** A long option spelled as an unambiguous prefix (`--verb` for `--verbose`). */
    case LongAbbreviated;

    /** One letter of a short cluster: `-f`, `-vvv`, `-abc`, `-c5`. */
    case Short;

    /** A positional token before any `--`. */
    case Positional;

    /** A positional token that followed the `--` marker. */
    case Terminated;

    /** The `--` marker itself. */
    case Terminator;
}
