<?php

declare(strict_types=1);

namespace src\Command;

/**
 * Plain value object built up by Command's protected setName()/
 * setDescription()/... setters during configure() — never read off a class
 * attribute.
 */
final readonly class CommandMetadata
{
    /**
     * @param string[] $aliases
     */
    public function __construct(
        public string $name,
        public string $description = '',
        public array $aliases = [],
        public bool $hidden = false,
        public ?string $group = null,
    ) {}
}
