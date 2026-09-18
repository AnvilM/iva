<?php

declare(strict_types=1);

namespace Iva\Output;

interface Spinner
{
    /**
     * Starts the animation. Frames advance on the event loop, so the spinner
     * only moves while the surrounding code yields to it (Async\sleep(),
     * awaiting an Awaitable, async I/O, ...). PHP is single-threaded: a
     * blocking call such as sleep() or a long CPU-bound loop freezes the
     * current frame until it returns. Call stop() or succeed()/fail()/warn()/
     * info() to end it.
     */
    public function start(): void;

    public function setMessage(string $message): void;

    /**
     * Runs $work while the spinner animates, then reports success — or
     * failure, rethrowing whatever $work threw. Same animation rules as
     * start(). This is the primary way to use a Spinner — start()/stop() are
     * for callers that need to drive the animation around code that can't be
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
