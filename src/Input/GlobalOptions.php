<?php

declare(strict_types=1);

namespace Iva\Input;

/**
 * Application-level persistent options. Registered with Global origin, so any
 * command declaring an option of the same name transparently overrides them.
 *
 * These constants are strictly an internal wiring detail between Application
 * and the parser (Application inspects the raw ParseResult for `--help` /
 * `--version` before a Definition/Input round-trip even makes sense — there
 * is no command yet to own a typed Option for them). They never reach a
 * Command's execute(): global flags surface there as Output's verbosity /
 * color support instead, never as an Input value to read by name.
 */
final class GlobalOptions
{
    public const string HELP = 'help';
    public const string VERSION = 'version';
    public const string VERBOSE = 'verbose';
    public const string QUIET = 'quiet';
    public const string ANSI = 'ansi';

    public static function register(Definition $definition): void
    {
        foreach (self::all() as $option) {
            $definition->addOption($option);
        }
    }

    /**
     * @return list<Option>
     */
    public static function all(): array
    {
        return [
            Option::flag(
                name: self::HELP,
                shortcut: 'h',
                description: 'Show help for this command',
                negatable: false,
                persistent: true,
                default: false,
            )->withOrigin(OptionOrigin::Global),
            Option::flag(
                name: self::VERSION,
                shortcut: 'V',
                description: 'Show the application version',
                negatable: false,
                persistent: true,
                default: false,
            )->withOrigin(OptionOrigin::Global),
            Option::flag(
                name: self::VERBOSE,
                shortcut: 'v',
                description: 'Increase verbosity (-v, -vv, -vvv)',
                negatable: false,
                persistent: true,
                default: false,
            )->withOrigin(OptionOrigin::Global),
            Option::flag(
                name: self::QUIET,
                shortcut: 'q',
                description: 'Suppress all output',
                negatable: false,
                persistent: true,
                default: false,
            )->withOrigin(OptionOrigin::Global),
            Option::flag(
                name: self::ANSI,
                description: 'Force ANSI output (--no-ansi disables it)',
                negatable: true,
                persistent: true,
                default: false,
            )->withOrigin(OptionOrigin::Global),
        ];
    }

    /**
     * @return list<string>
     */
    public static function names(): array
    {
        return [self::HELP, self::VERSION, self::VERBOSE, self::QUIET, self::ANSI];
    }
}
