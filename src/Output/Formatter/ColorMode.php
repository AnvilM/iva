<?php

declare(strict_types=1);

namespace Iva\Output\Formatter;

enum ColorMode
{
    case Default;
    case Standard;
    case Indexed;
    case TrueColor;
}
