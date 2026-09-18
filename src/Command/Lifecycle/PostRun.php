<?php

declare(strict_types=1);

namespace src\Command\Lifecycle;

use src\Output\Output;

interface PostRun
{
    public function postRun(Output $output, int $exitCode): void;
}
