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
        $this->running = true;
        $this->render();
    }

    public function setMessage(string $message): void
    {
        $this->message = $message;
        $this->render();
    }

    public function run(\Closure $work): mixed
    {
        $this->start();

        $done = false;
        $result = null;
        $error = null;

        Async\concurrently([
            function () use (&$done): void {
                while (!$done) {
                    $this->frameIndex++;
                    $this->render();
                    Async\sleep(DateTime\Duration::milliseconds((int) (1000 / self::FRAMES_PER_SECOND)));
                }
            },
            function () use ($work, &$done, &$result, &$error): void {
                try {
                    $result = $work();
                } catch (\Throwable $e) {
                    $error = $e;
                } finally {
                    $done = true;
                }
            },
        ]);

        if ($error !== null) {
            $this->fail();

            throw $error;
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
        $this->running = false;
        $this->release();
    }

    private function finish(string $icon, ?string $message): void
    {
        $this->running = false;
        $text = $message ?? $this->message;
        $this->paint($icon . ' ' . $text);
        $this->release();
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

        $this->paint('<info>' . $frame . '</info> ' . $this->message);
    }

    private function paint(string $line): void
    {
        $this->section->overwrite($this->formatter->format($line, $this->colorSupport));
    }
}
