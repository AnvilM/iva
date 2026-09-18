<?php

declare(strict_types=1);

namespace src\Output;

use Psl\IO;
use src\Output\Formatter\OutputFormatter;
use src\Output\Terminal\ColorSupport;
use src\Output\Terminal\TerminalInfo;
use function Psl\IO;

final class ErrorConsoleOutput extends AbstractOutput
{
    private readonly IO\WriteHandleInterface $handle;

    public function __construct(
        Verbosity $verbosityLevel = Verbosity::Normal,
        ?ColorSupport $colorSupport = null,
        ?IO\WriteHandleInterface $handle = null,
        ?OutputFormatter $formatter = null,
        ?SectionManager $sectionManager = null,
    ) {
        $this->handle = $handle ?? IO\error_handle();

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
        $stream = defined('STDERR') ? STDERR : fopen('php://stderr', 'wb');

        return TerminalInfo::detect($stream);
    }
}
