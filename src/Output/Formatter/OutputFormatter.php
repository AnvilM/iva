<?php

declare(strict_types=1);

namespace Iva\Output\Formatter;

use Iva\Output\Terminal\ColorSupport;

/**
 * Parses `<info>...</info>` / `<fg=green;options=bold>...</>` markup.
 *
 * When the target does not support color (isDecorated() === false), tags are
 * cut out rather than left as escape-sequence noise, so redirected/logged
 * output stays plain, readable text -- never raw ANSI garbage.
 */
final class OutputFormatter
{
    /**
     * Matches an opening or closing tag, but not one preceded by a literal
     * backslash (`\<info>` stays as text, handled by the unescape pass below).
     */
    private const string TAG_PATTERN = '/(?<!\\\\)<(\/)?([a-zA-Z][a-zA-Z0-9_,;=#\-]*)?>/';

    public function __construct(
        private readonly StyleRegistry $registry = new StyleRegistry(),
        private readonly AnsiRenderer $renderer = new AnsiRenderer(),
        private readonly InlineStyleParser $inlineParser = new InlineStyleParser(),
    ) {}

    public function registry(): StyleRegistry
    {
        return $this->registry;
    }

    public function format(string $message, ColorSupport $colorSupport): string
    {
        $stack = new StyleStack();
        $result = '';
        $offset = 0;

        preg_match_all(self::TAG_PATTERN, $message, $matches, PREG_OFFSET_CAPTURE | PREG_SET_ORDER);

        foreach ($matches as $match) {
            [$full, $fullOffset] = $match[0];
            $isClosing = ($match[1][0] ?? '') === '/';
            $name = $match[2][0] ?? '';

            $result .= $this->renderChunk(
                substr($message, $offset, $fullOffset - $offset),
                $stack->current(),
                $colorSupport,
            );
            $offset = $fullOffset + strlen($full);

            if ($isClosing) {
                if ($name === '') {
                    $stack->pop();
                } else {
                    $stack->popNamed($name);
                }

                continue;
            }

            $style = $name === '' ? null : $this->resolveStyle($name);

            if ($style === null) {
                // Doesn't resolve to a real style (typo, stray '<', etc.):
                // treat the whole thing as literal text instead of eating it.
                $result .= $this->renderChunk($full, $stack->current(), $colorSupport);
                continue;
            }

            $stack->push($name, $style);
        }

        $result .= $this->renderChunk(substr($message, $offset), $stack->current(), $colorSupport);

        return str_replace('\\<', '<', $result);
    }

    private function resolveStyle(string $name): ?Style
    {
        if ($this->inlineParser->looksLikeInlineSpec($name)) {
            return $this->inlineParser->parse($name);
        }

        return $this->registry->find($name);
    }

    private function renderChunk(string $text, ?Style $style, ColorSupport $colorSupport): string
    {
        if ($text === '') {
            return '';
        }

        if ($style === null || $colorSupport === ColorSupport::None) {
            return $text;
        }

        return $this->renderer->apply($style, $text, $colorSupport);
    }
}
