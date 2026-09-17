<?php

declare(strict_types=1);

namespace Iva\Input\Type;

/**
 * @template-covariant T
 */
interface InputType
{
    /**
     * @param string|array<int, string>|bool|null $raw
     * @return T
     */
    public function coerce(string|array|bool|null $raw): mixed;

    public function describe(): string;
}
