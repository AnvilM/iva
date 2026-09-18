<?php

declare(strict_types=1);

namespace Iva\Command\Lifecycle;

use Iva\Output\Output;

interface PersistentPreRun
{
    public function persistentPreRun(Output $output): void;
}
