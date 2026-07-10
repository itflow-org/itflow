<?php

namespace DirectoryTree\ImapEngine\Connection\Responses;

use DirectoryTree\ImapEngine\Connection\Responses\Data\Data;
use DirectoryTree\ImapEngine\Connection\Tokens\Token;
use Illuminate\Contracts\Support\Arrayable;
use Stringable;

class Response implements Arrayable, Stringable
{
    use HasTokens;

    /**
     * Constructor.
     */
    public function __construct(
        protected array $tokens
    ) {}

    /**
     * Get the response tokens.
     *
     * @return Token[]|Data[]
     */
    public function tokens(): array
    {
        return $this->tokens;
    }

    /**
     * Get the instance as an array.
     */
    public function toArray(): array
    {
        return array_map(function (Token|Data $token) {
            return $token instanceof Data
                ? $token->values()
                : $token->value;
        }, $this->tokens);
    }

    /**
     * Get a JSON representation of the response tokens.
     */
    public function __toString(): string
    {
        return implode(' ', $this->tokens);
    }
}
