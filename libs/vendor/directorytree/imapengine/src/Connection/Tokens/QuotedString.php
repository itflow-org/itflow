<?php

namespace DirectoryTree\ImapEngine\Connection\Tokens;

class QuotedString extends Token
{
    /**
     * Get the token's value.
     */
    public function __toString(): string
    {
        return '"'.$this->value.'"';
    }
}
