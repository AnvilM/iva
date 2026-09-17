<?php

declare(strict_types=1);

namespace Iva\Output;

use Iva\Output\Formatter\OutputFormatter;
use Iva\Output\Terminal\ColorSupport;
use Iva\Output\Terminal\TerminalInfo;
use Psl\IO;

final class ConsoleOutput extends AbstractOutput
{
    private readonly IO\WriteHandleInterface $handle;

    public function __construct(
        Verbosity $verbosityLevel = Verbosity::Normal,
        ?ColorSupport $colorSupport = null,
        ?IO\WriteHandleInterface $handle = null,
        ?OutputFormatter $formatter = null,
        ?SectionManager $sectionManager = null,
    ) {
        $this->handle = $handle ?? IO\output_handle();

        $info = self::detectTerminalInfo();

        parent::__construct(
            $verbosityLevel,
            $colorSupport ?? $info->colorSupport,
            $formatter,
            $info->isInteractive,
            $sectionManager,
        );
    }

    protected function sink(string $text): void
    {
        $this->handle->writeAll($text);
    }

    protected function withConfig(Verbosity $verbosity, ColorSupport $colorSupport): static
    {
        return new self($verbosity, $colorSupport, $this->handle, $this->formatter, $this->sectionManager());
    }

    private static function detectTerminalInfo(): TerminalInfo
    {
        $stream = defined('STDOUT') ? STDOUT : fopen('php://stdout', 'wb');

        return TerminalInfo::detect($stream);
    }
}
