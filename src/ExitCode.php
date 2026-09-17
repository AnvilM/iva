<?php

declare(strict_types=1);

namespace Iva;

enum ExitCode: int
{
    case Ok = 0;
    case GeneralError = 1;
    case Usage = 64;
    case DataError = 65;
    case NoInput = 66;
    case Unavailable = 69;
    case Software = 70;
    case CommandNotFound = 127;
}
