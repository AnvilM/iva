<?php

declare(strict_types=1);

namespace Iva\Output;

use Iva\Output\Terminal\VisualWidth;

/**
 * Named default templates (§9.5) plus the `{placeholder}` / `{placeholder:3s}`
 * substitution engine shared by ProgressBar and Spinner-as-indeterminate-bar.
 */
final class ProgressBarFormat
{
    public const string NORMAL = "{message}\n{bar} {percent}%";
    public const string VERBOSE = "{message}\n{bar} {percent}% ({current}/{max}, ETA: {remaining})";
    public const string DEBUG = "{message}\n{bar} {percent}% ({current}/{max}, ETA: {remaining}) {memory}";

    private const string PLACEHOLDER_PATTERN = '/\{([a-zA-Z]+)(?::(\d+)(s))?\}/';

    /**
     * @param array<string, string> $values placeholder name => already-formatted text
     */
    public static function render(string $template, array $values): string
    {
        $rendered = preg_replace_callback(
            self::PLACEHOLDER_PATTERN,
            static function (array $match) use ($values): string {
                $name = $match[1];
                $value = $values[$name] ?? $match[0];

                if (isset($match[2]) && $match[2] !== '') {
                    $value = VisualWidth::pad($value, (int) $match[2], padType: STR_PAD_LEFT);
                }

                return $value;
            },
            $template,
        );

        return $rendered ?? $template;
    }
}
