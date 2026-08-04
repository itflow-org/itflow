<?php

namespace DirectoryTree\ImapEngine\Connection\Responses\Data;

use DirectoryTree\ImapEngine\Connection\Responses\HasTokens;
use DirectoryTree\ImapEngine\Connection\Tokens\Token;
use Stringable;

abstract class Data implements Stringable
{
    use HasTokens;

    /**
     * Constructor.
     */
    public function __construct(
        protected array $tokens
    ) {}

    /**
     * Get the tokens.
     *
     * @return Token[]|Data[]
     */
    public function tokens(): array
    {
        return $this->tokens;
    }

    /**
     * Get the first token.
     */
    public function first(): Token|Data|null
    {
        return $this->tokens[0] ?? null;
    }

    /**
     * Get the last token.
     */
    public function last(): Token|Data|null
    {
        return $this->tokens[count($this->tokens) - 1] ?? null;
    }

    /**
     * Determine if the data contains a specific value.
     */
    public function contains(array|string $needles): bool
    {
        $haystack = $this->values();

        foreach ((array) $needles as $needle) {
            if (! in_array($needle, $haystack)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Get all the token's values.
     */
    public function values(): array
    {
        return array_map(function (Token|Data $token) {
            return $token instanceof Data
                ? $token->values()
                : $token->value;
        }, $this->tokens);
    }
}
