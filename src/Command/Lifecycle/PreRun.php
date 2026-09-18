<?php

declare(strict_types=1);

namespace src\Command\Lifecycle;

use src\Output\Output;

interface PreRun
{
    public function preRun(Output $output): void;
}
