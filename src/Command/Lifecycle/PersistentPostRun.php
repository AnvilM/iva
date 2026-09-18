<?php

declare(strict_types=1);

namespace Iva\Command\Lifecycle;

use Iva\Output\Output;

interface PersistentPostRun
{
    public function persistentPostRun(Output $output, int $exitCode): void;
}
