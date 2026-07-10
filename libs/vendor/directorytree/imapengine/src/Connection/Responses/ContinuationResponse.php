<?php

namespace DirectoryTree\ImapEngine\Connection\Responses;

use DirectoryTree\ImapEngine\Connection\Tokens\Token;

class ContinuationResponse extends Response
{
    /**
     * Get the data tokens.
     *
     * @return Token[]
     */
    public function data(): array
    {
        return array_slice($this->tokens, 1);
    }
}
