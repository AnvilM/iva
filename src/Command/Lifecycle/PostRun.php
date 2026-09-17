<?php

declare(strict_types=1);

namespace Iva\Command\Lifecycle;

use Iva\Output\Output;

interface PostRun
{
    public function postRun(Output $output, int $exitCode): void;
}
