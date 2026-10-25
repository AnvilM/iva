<?php

declare(strict_types=1);

namespace Iva\Input\Invocation;

/**
 * What a single {@see ArgvEntry} represents.
 */
enum EntryKind
{
    /** An option occurrence: `--force`, `-f`, `--tag=a`, one letter of `-vvv`, ... */
    case Option;

    /** A positional token (bound to a declared Argument, or unexpected). */
    case Argument;

    /** The bare `--` end-of-options marker. */
    case Terminator;
}
