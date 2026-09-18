<?php

declare(strict_types=1);

namespace src\Output;

use src\Output\Formatter\OutputFormatter;
use src\Output\Formatter\Style;
use src\Output\Terminal\ColorSupport;

/**
 * Shared plumbing for the three Output implementations: verbosity gating,
 * <tag> formatting, and the style registry. Each subclass only has to say
 * how a fully rendered chunk of text reaches its sink (stdout, stderr, or an
 * in-memory buffer) and how to rebuild itself with different config for the
 * immutable with*() methods.
 */
abstract class AbstractOutput implements Output
{
    protected readonly OutputFormatter $formatter;
    private readonly SectionManager $sectionManager;

    public function __construct(
        protected readonly Verbosity $verbosityLevel,
        protected readonly ColorSupport $colorSupportLevel,
        ?OutputFormatter $formatter = null,
        protected readonly bool $interactive = false,
        ?SectionManager $sectionManager = null,
    ) {
        $this->formatter = $formatter ?? new OutputFormatter();
        $this->sectionManager = $sectionManager ?? new SectionManager();
    }

    abstract protected function sink(string $text): void;

    /**
     * Rebuilds the concrete subclass with new config, carrying over whatever
     * sink-specific state (a handle, an in-memory buffer, ...) it holds.
     */
    abstract protected function withConfig(Verbosity $verbosity, ColorSupport $colorSupport): static;

    public function write(string $message, bool $newline = false, Verbosity $minVerbosity = Verbosity::Normal): void
    {
        if (!$this->passesVerbosity($minVerbosity)) {
            return;
        }

        $rendered = $this->formatter->format($message, $this->colorSupportLevel);
        $this->sink($newline ? $rendered . "\n" : $rendered);
    }

    public function writeln(string $message, Verbosity $minVerbosity = Verbosity::Normal): void
    {
        $this->write($message, true, $minVerbosity);
    }

    public function verbose(string $message): void
    {
        $this->writeln($message, Verbosity::Verbose);
    }

    public function veryVerbose(string $message): void
    {
        $this->writeln($message, Verbosity::VeryVerbose);
    }

    public function debug(string $message): void
    {
        $this->writeln($message, Verbosity::Debug);
    }

    /**
     * Quiet suppresses everything except an explicit Verbosity::Quiet write
     * (used by Application for messages that must survive -q, e.g. fatal
     * errors on ErrorConsoleOutput).
     */
    private function passesVerbosity(Verbosity $minVerbosity): bool
    {
        if ($this->verbosityLevel === Verbosity::Quiet) {
            return $minVerbosity === Verbosity::Quiet;
        }

        return $this->verbosityLevel->atLeast($minVerbosity);
    }

    public function verbosity(): Verbosity
    {
        return $this->verbosityLevel;
    }

    public function isVerbose(): bool
    {
        return $this->verbosityLevel->atLeast(Verbosity::Verbose);
    }

    public function isDebug(): bool
    {
        return $this->verbosityLevel->atLeast(Verbosity::Debug);
    }

    public function isDecorated(): bool
    {
        return $this->colorSupportLevel !== ColorSupport::None;
    }

    public function colorSupport(): ColorSupport
    {
        return $this->colorSupportLevel;
    }

    public function withVerbosity(Verbosity $verbosity): static
    {
        return $this->withConfig($verbosity, $this->colorSupportLevel);
    }

    public function withDecorated(bool $decorated): static
    {
        $colorSupport = $decorated
            ? ($this->colorSupportLevel === ColorSupport::None ? ColorSupport::TrueColor : $this->colorSupportLevel)
            : ColorSupport::None;

        return $this->withConfig($this->verbosityLevel, $colorSupport);
    }

    public function withColorSupport(ColorSupport $support): static
    {
        return $this->withConfig($this->verbosityLevel, $support);
    }

    public function defineStyle(string $name, Style $style): void
    {
        $this->formatter->registry()->define($name, $style);
    }

    /**
     * Carried over verbatim by subclasses' withConfig() so the same manager
     * (and thus the same live section stack) survives immutable "with"
     * calls — it reflects real terminal state, not per-instance config.
     */
    protected function sectionManager(): SectionManager
    {
        return $this->sectionManager;
    }

    public function section(): Section
    {
        // Section (and everything built on it — ProgressBar, Spinner) writes
        // straight to the sink rather than through write()/writeln(), so it
        // needs its own verbosity gate here: -q must suppress *all* output,
        // not just plain write()/writeln() calls.
        $sink = function (string $text): void {
            if ($this->verbosityLevel === Verbosity::Quiet) {
                return;
            }

            $this->sink($text);
        };

        if ($this->interactive) {
            return new AnsiSection($this->sectionManager, $sink);
        }

        return new PlainSection($sink);
    }

    public function progressBar(int $max = 0): ProgressBar
    {
        return new DefaultProgressBar(
            $this->section(),
            $max,
            $this->verbosityLevel,
            $this->formatter,
            $this->colorSupportLevel,
        );
    }

    public function spinner(string $message = ''): Spinner
    {
        return new DefaultSpinner(
            $this->section(),
            $message,
            SpinnerStyle::default(\src\Output\Terminal\UnicodeSupport::detect()),
            $this->formatter,
            $this->colorSupportLevel,
        );
    }

    public function table(): Table
    {
        return new Table($this, $this->formatter, $this->colorSupportLevel, \src\Output\Terminal\UnicodeSupport::detect());
    }

    public function tree(): Tree
    {
        return new Tree($this);
    }
}
