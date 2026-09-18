<?php

declare(strict_types=1);

namespace Iva\Testing;

use Iva\Application;
use Iva\Output\BufferedOutput;

/**
 * Swaps Application's stdout/stderr for BufferedOutput before every run(),
 * so assertions never depend on the environment's TTY/color detection —
 * BufferedOutput is never interactive and defaults to no color support.
 */
final class ApplicationTester
{
    private BufferedOutput $output;
    private BufferedOutput $errorOutput;

    public function __construct(private readonly Application $app)
    {
        $this->output = new BufferedOutput();
        $this->errorOutput = new BufferedOutput();
    }

    /**
     * @param list<string> $argv the raw argv, including the script name at index 0 — same convention as Application::run()
     */
    public function run(array $argv): int
    {
        $this->output = new BufferedOutput();
        $this->errorOutput = new BufferedOutput();

        $this->app->setOutput($this->output);
        $this->app->setErrorOutput($this->errorOutput);

        return $this->app->run($argv);
    }

    public function getOutput(): string
    {
        return $this->output->fetch();
    }

    public function getErrorOutput(): string
    {
        return $this->errorOutput->fetch();
    }
}
