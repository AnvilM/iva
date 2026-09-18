<?php

declare(strict_types=1);

namespace src\Input;

final class Lexer
{
    /**
     * @param string[] $tokens
     * @return RawToken[]
     */
    public function tokenize(array $tokens): array
    {
        $result = [];

        foreach (array_values($tokens) as $position => $value) {
            $result[] = new RawToken($value, $position);
        }

        return $result;
    }
}
