<?php

declare(strict_types=1);

namespace Iva\Input;

final class Lexer
{
    /**
     * @param string[] $tokens
     * @param int $offset argv position of the first token (the number of
     *        tokens already consumed by the command path), so RawToken::$position
     *        is an index into the whole argv tail rather than into $tokens
     * @return RawToken[]
     */
    public function tokenize(array $tokens, int $offset = 0): array
    {
        $result = [];

        foreach (array_values($tokens) as $position => $value) {
            $result[] = new RawToken($value, $offset + $position);
        }

        return $result;
    }
}
