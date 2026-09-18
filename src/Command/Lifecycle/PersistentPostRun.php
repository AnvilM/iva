<?php

declare(strict_types=1);

namespace src\Command\Lifecycle;

use src\Output\Output;

interface PersistentPostRun
{
    public function persistentPostRun(Output $output, int $exitCode): void;
}
