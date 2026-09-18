<?php

declare(strict_types=1);

namespace src\Output;

use src\Output\Formatter\Style;
use src\Output\Terminal\ColorSupport;

interface Output
{
    public function write(string $message, bool $newline = false, Verbosity $minVerbosity = Verbosity::Normal): void;

    public function writeln(string $message, Verbosity $minVerbosity = Verbosity::Normal): void;

    /** Shortcut for writeln($message, Verbosity::Verbose). */
    public function verbose(string $message): void;

    /** Shortcut for writeln($message, Verbosity::VeryVerbose). */
    public function veryVerbose(string $message): void;

    /** Shortcut for writeln($message, Verbosity::Debug). */
    public function debug(string $message): void;

    public function verbosity(): Verbosity;

    public function isVerbose(): bool;

    public function isDebug(): bool;

    public function isDecorated(): bool;

    public function colorSupport(): ColorSupport;

    public function withVerbosity(Verbosity $verbosity): static;

    public function withDecorated(bool $decorated): static;

    public function withColorSupport(ColorSupport $support): static;

    public function defineStyle(string $name, Style $style): void;

    /** Creates an independently redrawable region of the terminal (see Section). */
    public function section(): Section;

    /**
     * @param int $max total steps; 0 (the default) starts in indeterminate mode
     */
    public function progressBar(int $max = 0): ProgressBar;

    public function spinner(string $message = ''): Spinner;

    public function table(): Table;

    public function tree(): Tree;
}
