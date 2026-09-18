<?php

declare(strict_types=1);

namespace Iva\Output\Formatter;

/**
 * Parses the inline style syntax used inside a tag body, e.g.
 * "fg=green;bg=black;options=bold,underline".
 */
final class InlineStyleParser
{
    public function parse(string $spec): ?Style
    {
        $foreground = null;
        $background = null;
        $options = [];
        $matchedAnything = false;

        foreach (explode(';', $spec) as $segment) {
            $segment = trim($segment);

            if ($segment === '') {
                continue;
            }

            $equals = strpos($segment, '=');

            if ($equals === false) {
                continue;
            }

            $key = strtolower(trim(substr($segment, 0, $equals)));
            $value = trim(substr($segment, $equals + 1));

            switch ($key) {
                case 'fg':
                    $foreground = Color::parse($value);
                    $matchedAnything = true;
                    break;
                case 'bg':
                    $background = Color::parse($value);
                    $matchedAnything = true;
                    break;
                case 'options':
                    $options = array_map(
                        static fn(string $option): string => strtolower(trim($option)),
                        explode(',', $value),
                    );
                    $matchedAnything = true;
                    break;
            }
        }

        if (!$matchedAnything) {
            return null;
        }

        return new Style(
            foreground: $foreground,
            background: $background,
            bold: in_array('bold', $options, true),
            dim: in_array('dim', $options, true) || in_array('faint', $options, true),
            italic: in_array('italic', $options, true),
            underline: in_array('underline', $options, true),
            blink: in_array('blink', $options, true),
            reverse: in_array('reverse', $options, true),
            hidden: in_array('hidden', $options, true) || in_array('conceal', $options, true),
            strikethrough: in_array('strikethrough', $options, true) || in_array('strike', $options, true),
        );
    }

    public function looksLikeInlineSpec(string $tagBody): bool
    {
        return str_starts_with($tagBody, 'fg=')
            || str_starts_with($tagBody, 'bg=')
            || str_starts_with($tagBody, 'options=');
    }
}
