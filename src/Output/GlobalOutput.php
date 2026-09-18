<?php

declare(strict_types=1);

namespace src\Output;

/**
 * Global accessor for the current Output/error Output.
 *
 * Application keeps this in sync automatically: it is set once run() picks a
 * concrete Output (stdout/stderr, or whatever was passed to setOutput()/
 * setErrorOutput()), and updated again once -q/-v/--ansi have been applied,
 * so code anywhere in the call stack — deep inside a helper that was never
 * handed an Output — can still reach the exact instance the running command
 * is using, via GlobalOutput::get()/error().
 *
 * This is an opt-in convenience on top of the ordinary, non-global Output
 * that Application passes into execute(); nothing requires using it, and
 * Application itself never reads from it, only writes to it.
 */
final class GlobalOutput
{
    private static ?Output $output = null;

    private static ?Output $errorOutput = null;

    private function __construct()
    {
        // Static registry only.
    }

    public static function set(Output $output): void
    {
        self::$output = $output;
    }

    public static function setError(Output $errorOutput): void
    {
        self::$errorOutput = $errorOutput;
    }

    /**
     * Falls back to a fresh ConsoleOutput when nothing has been registered
     * yet (e.g. code running outside of Application::run()).
     */
    public static function get(): Output
    {
        return self::$output ??= new ConsoleOutput();
    }

    public static function error(): Output
    {
        return self::$errorOutput ??= new ErrorConsoleOutput();
    }

    public static function isSet(): bool
    {
        return self::$output !== null;
    }

    /**
     * Mostly useful for tests: clears both registered instances so the next
     * get()/error() call falls back to a fresh default again.
     */
    public static function reset(): void
    {
        self::$output = null;
        self::$errorOutput = null;
    }
}
