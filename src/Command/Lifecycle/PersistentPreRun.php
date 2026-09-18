<?php

declare(strict_types=1);

namespace src\Command\Lifecycle;

use src\Output\Output;

interface PersistentPreRun
{
    public function persistentPreRun(Output $output): void;
}
