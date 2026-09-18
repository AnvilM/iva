<?php

declare(strict_types=1);

namespace src\Input;

enum OptionOrigin
{
    /** Declared by the command being executed. */
    case Local;

    /** Declared `persistent: true` by an ancestor command in the path. */
    case Inherited;

    /** Built into the Application (--help, --version, -v, -q, --ansi). */
    case Global;

    public function precedence(): int
    {
        return match ($this) {
            self::Global => 0,
            self::Inherited => 1,
            self::Local => 2,
        };
    }
}
