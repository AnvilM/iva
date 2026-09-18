<?php

declare(strict_types=1);

namespace src\Output\Formatter;

enum ColorMode
{
    case Default;
    case Standard;
    case Indexed;
    case TrueColor;
}
