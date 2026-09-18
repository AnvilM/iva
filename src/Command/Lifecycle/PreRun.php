<?php

declare(strict_types=1);

namespace Iva\Command\Lifecycle;

use Iva\Output\Output;

interface PreRun
{
    public function preRun(Output $output): void;
}
