<?php

declare(strict_types=1);

namespace Iva\Output\Formatter;

final readonly class Style
{
    public function __construct(
        public ?Color $foreground = null,
        public ?Color $background = null,
        public bool $bold = false,
        public bool $dim = false,
        public bool $italic = false,
        public bool $underline = false,
        public bool $blink = false,
        public bool $reverse = false,
        public bool $hidden = false,
        public bool $strikethrough = false,
    ) {}

    /**
     * Nested-tag semantics: the child's explicit color overrides the
     * parent's, and any attribute either one turns on stays on.
     */
    public function mergedWith(self $child): self
    {
        return new self(
            $child->foreground ?? $this->foreground,
            $child->background ?? $this->background,
            $this->bold || $child->bold,
            $this->dim || $child->dim,
            $this->italic || $child->italic,
            $this->underline || $child->underline,
            $this->blink || $child->blink,
            $this->reverse || $child->reverse,
            $this->hidden || $child->hidden,
            $this->strikethrough || $child->strikethrough,
        );
    }

    public function isEmpty(): bool
    {
        return $this->foreground === null
            && $this->background === null
            && !$this->bold
            && !$this->dim
            && !$this->italic
            && !$this->underline
            && !$this->blink
            && !$this->reverse
            && !$this->hidden
            && !$this->strikethrough;
    }
}
