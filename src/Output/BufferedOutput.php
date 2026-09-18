<?php

declare(strict_types=1);

namespace Iva\Output;

use Iva\Output\Formatter\OutputFormatter;
use Iva\Output\Terminal\ColorSupport;

/**
 * In-memory sink for the Testing kit. Verbosity defaults to Normal and color
 * support to None, so assertions on captured output stay stable regardless
 * of the environment the test suite runs in.
 */
final class BufferedOutput extends AbstractOutput
{
    private string $buffer = '';

    public function __construct(
        Verbosity $verbosityLevel = Verbosity::Normal,
        ColorSupport $colorSupport = ColorSupport::None,
        ?OutputFormatter $formatter = null,
        ?SectionManager $sectionManager = null,
    ) {
        // Never interactive: tests get deterministic, escape-code-free output
        // regardless of the environment the test suite happens to run in.
        parent::__construct($verbosityLevel, $colorSupport, $formatter, interactive: false, sectionManager: $sectionManager);
    }

    protected function sink(string $text): void
    {
        $this->buffer .= $text;
    }

    protected function withConfig(Verbosity $verbosity, ColorSupport $colorSupport): static
    {
        $clone = new self($verbosity, $colorSupport, $this->formatter, $this->sectionManager());
        $clone->buffer = $this->buffer;

        return $clone;
    }

    public function fetch(): string
    {
        return $this->buffer;
    }

    public function clear(): void
    {
        $this->buffer = '';
    }
}
