<?php

declare(strict_types=1);

namespace Iva\Output;

use Psl\Async;
use Psl\DateTime;
use Iva\Output\Formatter\OutputFormatter;
use Iva\Output\Terminal\ColorSupport;
use function Psl\Async;

final class DefaultProgressBar implements ProgressBar
{
    private const int BAR_WIDTH = 28;
    private const int THROTTLE_INTERVAL_NS = 80_000_000; // ~80ms, per §9.5
    private const float MIN_PERCENT_STEP = 1.0;
    private const int INDETERMINATE_TICK_SECONDS_DIVISOR = 10; // 10 fps ticker

    private int $max;
    private int $current = 0;
    private ?int $startedAtNs = null;
    private ?int $lastRenderNs = null;
    private ?float $lastRenderedPercent = null;

    /** @var array<string, string> */
    private array $messages = ['message' => ''];

    private string $format;

    /** @var list<float> recent (elapsed, current) samples, oldest first, for a moving-average rate */
    private array $rateSamples = [];

    private int $bounceFrame = 0;

    public function __construct(
        private readonly Section $section,
        int $max,
        private readonly Verbosity $verbosity,
        private readonly OutputFormatter $formatter,
        private readonly ColorSupport $colorSupport,
    ) {
        $this->max = max(0, $max);
        $this->format = match (true) {
            $this->verbosity->atLeast(Verbosity::Debug) => ProgressBarFormat::DEBUG,
            $this->verbosity->atLeast(Verbosity::Verbose) => ProgressBarFormat::VERBOSE,
            default => ProgressBarFormat::NORMAL,
        };
    }

    public function start(?int $max = null): void
    {
        if ($max !== null) {
            $this->max = max(0, $max);
        }

        $this->current = 0;
        $this->startedAtNs = hrtime(true);
        $this->lastRenderNs = null;
        $this->lastRenderedPercent = null;
        $this->rateSamples = [];
        $this->render(force: true);
    }

    public function advance(int $step = 1): void
    {
        $this->setProgress($this->current + $step);
    }

    public function setProgress(int $current): void
    {
        $this->current = $this->max > 0 ? max(0, min($this->max, $current)) : max(0, $current);
        $this->sampleRate();
        $this->render(force: false);
    }

    public function setMessage(string $message, string $name = 'message'): void
    {
        $this->messages[$name] = $message;
        $this->render(force: false);
    }

    public function setFormat(string $format): void
    {
        $this->format = $format;
    }

    public function finish(): void
    {
        if ($this->max > 0) {
            $this->current = $this->max;
        }

        $this->render(force: true);
        // After overwrite() the cursor already sits just below this
        // section's last line, so ordinary output can simply resume there;
        // releasing just stops treating this section as part of the live
        // stack that later section redraws must account for.
        if ($this->section instanceof AnsiSection) {
            $this->section->release();
        }
    }

    public function runIndeterminate(\Closure $work): mixed
    {
        $wasIndeterminate = $this->max === 0;
        $this->start($this->max === 0 ? null : $this->max);

        $done = false;
        $result = null;
        $error = null;

        $results = Async\concurrently([
            function () use (&$done): void {
                while (!$done) {
                    if ($wasIndeterminate) {
                        $this->bounceFrame++;
                    }

                    $this->render(force: true);
                    Async\sleep(DateTime\Duration::milliseconds((int) (1000 / self::INDETERMINATE_TICK_SECONDS_DIVISOR)));
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

        unset($results);

        if ($error !== null) {
            throw $error;
        }

        return $result;
    }

    private function sampleRate(): void
    {
        if ($this->startedAtNs === null) {
            return;
        }

        $elapsed = (hrtime(true) - $this->startedAtNs) / 1_000_000_000;
        $this->rateSamples[] = $elapsed;
        $this->rateSamples[] = (float) $this->current;

        // Keep only the last ~20 (elapsed, current) pairs for the moving average.
        if (count($this->rateSamples) > 40) {
            $this->rateSamples = array_slice($this->rateSamples, -40);
        }
    }

    private function render(bool $force): void
    {
        $now = hrtime(true);
        $percent = $this->max > 0 ? (100.0 * $this->current / max(1, $this->max)) : 0.0;

        if (!$force && $this->lastRenderNs !== null) {
            $elapsedSinceRender = $now - $this->lastRenderNs;
            $percentDelta = $this->lastRenderedPercent === null
                ? self::MIN_PERCENT_STEP
                : abs($percent - $this->lastRenderedPercent);

            if ($elapsedSinceRender < self::THROTTLE_INTERVAL_NS && $percentDelta < self::MIN_PERCENT_STEP) {
                return;
            }
        }

        $this->lastRenderNs = $now;
        $this->lastRenderedPercent = $percent;

        $values = [
            'bar' => $this->renderBar($percent),
            'percent' => sprintf('%3d', (int) round($percent)),
            'current' => (string) $this->current,
            'max' => (string) $this->max,
            'elapsed' => $this->formatDuration($this->elapsedSeconds()),
            'remaining' => $this->formatDuration($this->etaSeconds()),
            'memory' => $this->formatMemory(memory_get_usage(true)),
            'message' => $this->messages['message'] ?? '',
        ];

        foreach ($this->messages as $name => $message) {
            $values[$name] ??= $message;
        }

        $rendered = ProgressBarFormat::render($this->format, $values);
        $lines = array_map(
            fn(string $line): string => $this->formatter->format($line, $this->colorSupport),
            explode("\n", $rendered),
        );

        $this->section->overwrite($lines);
    }

    private function renderBar(float $percent): string
    {
        if ($this->max <= 0) {
            return $this->renderIndeterminateBar();
        }

        $filled = (int) round(self::BAR_WIDTH * min(100.0, max(0.0, $percent)) / 100);
        $filled = max(0, min(self::BAR_WIDTH, $filled));

        return '[' . str_repeat('=', $filled) . ($filled < self::BAR_WIDTH ? '>' : '')
            . str_repeat(' ', max(0, self::BAR_WIDTH - $filled - 1)) . ']';
    }

    private function renderIndeterminateBar(): string
    {
        $span = self::BAR_WIDTH - 2;
        $period = max(1, $span * 2);
        $position = $this->bounceFrame % $period;
        $offset = $position <= $span ? $position : $period - $position;

        return '[' . str_repeat(' ', $offset) . '<=>' . str_repeat(' ', max(0, $span - $offset)) . ']';
    }

    private function elapsedSeconds(): ?float
    {
        return $this->startedAtNs === null ? null : (hrtime(true) - $this->startedAtNs) / 1_000_000_000;
    }

    private function etaSeconds(): ?float
    {
        if ($this->max <= 0 || count($this->rateSamples) < 4) {
            return null;
        }

        $count = count($this->rateSamples);
        [$firstElapsed, $firstCurrent] = [$this->rateSamples[0], $this->rateSamples[1]];
        [$lastElapsed, $lastCurrent] = [$this->rateSamples[$count - 2], $this->rateSamples[$count - 1]];

        $deltaTime = $lastElapsed - $firstElapsed;
        $deltaCurrent = $lastCurrent - $firstCurrent;

        if ($deltaCurrent <= 0.0 || $deltaTime <= 0.0) {
            return null;
        }

        $rate = $deltaCurrent / $deltaTime;
        $remainingWork = max(0, $this->max - $this->current);

        return $rate > 0 ? $remainingWork / $rate : null;
    }

    private function formatDuration(?float $seconds): string
    {
        if ($seconds === null) {
            return '--:--';
        }

        $totalSeconds = (int) round($seconds);
        $minutes = intdiv($totalSeconds, 60);
        $remainingSeconds = $totalSeconds % 60;

        return sprintf('%02d:%02d', $minutes, $remainingSeconds);
    }

    private function formatMemory(int $bytes): string
    {
        $units = ['B', 'KiB', 'MiB', 'GiB'];
        $value = (float) $bytes;
        $unitIndex = 0;

        while ($value >= 1024.0 && $unitIndex < count($units) - 1) {
            $value /= 1024.0;
            $unitIndex++;
        }

        return sprintf('%.1f %s', $value, $units[$unitIndex]);
    }
}
