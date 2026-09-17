<?php

declare(strict_types=1);

namespace Iva\Output;

interface Spinner
{
    public function start(): void;

    public function setMessage(string $message): void;

    /**
     * Runs $work while the spinner animates in the background, then stops
     * it. This is the primary way to use a Spinner — start()/stop() are for
     * callers that need to drive the animation around code that can't be
     * expressed as a single closure.
     *
     * @template T
     * @param \Closure(): T $work
     * @return T
     */
    public function run(\Closure $work): mixed;

    public function succeed(?string $message = null): void;

    public function fail(?string $message = null): void;

    public function warn(?string $message = null): void;

    public function info(?string $message = null): void;

    /** Stops the animation without printing a final status icon. */
    public function stop(): void;
}
