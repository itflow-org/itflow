<?php

namespace DirectoryTree\ImapEngine\Connection;

use Stringable;

class RawQueryValue
{
    /**
     * Constructor.
     */
    public function __construct(
        public readonly Stringable|string $value
    ) {}
}
