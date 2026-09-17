<?php

declare(strict_types=1);

namespace Iva\Output;

interface ProgressBar
{
    /**
     * @param int|null $max pass to (re)set the total; null/0 switches to
     *     indeterminate mode (an activity indicator instead of a percentage)
     */
    public function start(?int $max = null): void;

    public function advance(int $step = 1): void;

    public function setProgress(int $current): void;

    public function setMessage(string $message, string $name = 'message'): void;

    /** Sets the render template; see ProgressBarFormat for the placeholder syntax. */
    public function setFormat(string $format): void;

    /** Locks in the final (100%) frame, prints a trailing newline, and releases the section. */
    public function finish(): void;

    /**
     * Runs $work while a background ticker keeps the bar's elapsed/ETA
     * display animating, for the case where progress can't be advanced
     * discretely (e.g. waiting on one long request). Ordinary discrete
     * progress — advance() in a foreach loop — never needs this.
     *
     * @template T
     * @param \Closure(): T $work
     * @return T
     */
    public function runIndeterminate(\Closure $work): mixed;
}
