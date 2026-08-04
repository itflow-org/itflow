<?php

namespace DirectoryTree\ImapEngine\Connection\Tokens;

use Stringable;

abstract class Token implements Stringable
{
    /**
     * Constructor.
     */
    public function __construct(
        public string $value,
    ) {}

    /**
     * Determine if the token is the given value.
     */
    public function is(string $value): bool
    {
        return $this->value === $value;
    }

    /**
     * Determine if the token is not the given value.
     */
    public function isNot(string $value): bool
    {
        return ! $this->is($value);
    }

    /**
     * Get the token's value.
     */
    public function __toString(): string
    {
        return $this->value;
    }
}
