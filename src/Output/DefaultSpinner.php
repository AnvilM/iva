<?php

declare(strict_types=1);

namespace Iva\Output;

use Iva\Output\Formatter\OutputFormatter;
use Iva\Output\Terminal\ColorSupport;
use Psl\Async;
use Psl\DateTime;

final class DefaultSpinner implements Spinner
{
    private const int FRAMES_PER_SECOND = 10;

    private int $frameIndex = 0;
    private bool $running = false;

    /** Event-loop watcher that advances the frames while the spinner is running. */
    private ?string $ticker = null;

    public function __construct(
        private readonly Section $section,
        private string $message,
        private readonly SpinnerStyle $style,
        private readonly OutputFormatter $formatter,
        private readonly ColorSupport $colorSupport,
    ) {
    }

    public function start(): void
    {
        if ($this->running) {
            return;
        }

        $this->running = true;
        $this->render();

        $this->ticker = Async\Scheduler::repeat(
            DateTime\Duration::milliseconds((int) (1000 / self::FRAMES_PER_SECOND)),
            function (): void {
                $this->frameIndex++;
                $this->render();
            },
        );

        // The ticker must never be the thing that keeps the process alive:
        // a spinner nobody stopped shouldn't hang the event loop at exit.
        Async\Scheduler::unreference($this->ticker);
    }

    public function setMessage(string $message): void
    {
        $this->message = $message;
        $this->render();
    }

    public function run(\Closure $work): mixed
    {
        $this->start();

        try {
            $result = $work();
        } catch (\Throwable $e) {
            $this->fail();

            throw $e;
        }

        $this->succeed();

        return $result;
    }

    public function succeed(?string $message = null): void
    {
        $this->finish('<success>✔</success>', $message);
    }

    public function fail(?string $message = null): void
    {
        $this->finish('<error>✖</error>', $message);
    }

    public function warn(?string $message = null): void
    {
        $this->finish('<warning>!</warning>', $message);
    }

    public function info(?string $message = null): void
    {
        $this->finish('<info>i</info>', $message);
    }

    public function stop(): void
    {
        $this->halt();
        $this->release();
    }

    private function finish(string $icon, ?string $message): void
    {
        $this->halt();
        $text = $message ?? $this->message;
        $this->paint($icon . ' ' . $text);
        $this->release();
    }

    private function halt(): void
    {
        $this->running = false;

        if ($this->ticker !== null) {
            Async\Scheduler::cancel($this->ticker);
            $this->ticker = null;
        }
    }

    private function release(): void
    {
        if ($this->section instanceof AnsiSection) {
            $this->section->release();
        }
    }

    private function render(): void
    {
        if (!$this->running) {
            return;
        }

        $frames = $this->style->frames();
        $frame = $frames[$this->frameIndex % count($frames)];

        // The space goes *inside* the tag: the Line/Ascii frames contain a backslash,
        // and a backslash directly before `</info>` would escape the tag.
        $this->paint('<info>' . $frame . ' </info>' . $this->message);
    }

    private function paint(string $line): void
    {
        $this->section->overwrite($this->formatter->format($line, $this->colorSupport));
    }
}
