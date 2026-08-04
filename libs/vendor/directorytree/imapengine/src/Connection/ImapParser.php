<?php

namespace DirectoryTree\ImapEngine\Connection;

use DirectoryTree\ImapEngine\Connection\Responses\ContinuationResponse;
use DirectoryTree\ImapEngine\Connection\Responses\Data\Data;
use DirectoryTree\ImapEngine\Connection\Responses\Data\ListData;
use DirectoryTree\ImapEngine\Connection\Responses\Data\ResponseCodeData;
use DirectoryTree\ImapEngine\Connection\Responses\Response;
use DirectoryTree\ImapEngine\Connection\Responses\TaggedResponse;
use DirectoryTree\ImapEngine\Connection\Responses\UntaggedResponse;
use DirectoryTree\ImapEngine\Connection\Tokens\Atom;
use DirectoryTree\ImapEngine\Connection\Tokens\Crlf;
use DirectoryTree\ImapEngine\Connection\Tokens\ListClose;
use DirectoryTree\ImapEngine\Connection\Tokens\ListOpen;
use DirectoryTree\ImapEngine\Connection\Tokens\Number;
use DirectoryTree\ImapEngine\Connection\Tokens\ResponseCodeClose;
use DirectoryTree\ImapEngine\Connection\Tokens\ResponseCodeOpen;
use DirectoryTree\ImapEngine\Connection\Tokens\Token;
use DirectoryTree\ImapEngine\Exceptions\ImapParserException;

class ImapParser
{
    /**
     * The current token being parsed.
     *
     * Expected to be an associative array with keys like "type" and "value".
     */
    protected ?Token $currentToken = null;

    /**
     * Constructor.
     */
    public function __construct(
        protected ImapTokenizer $tokenizer
    ) {}

    /**
     * Get the next response from the tokenizer.
     */
    public function next(): Data|Token|Response|null
    {
        // Attempt to load the first token.
        if (! $this->currentToken) {
            $this->advance();
        }

        // No token was found, return null.
        if (! $this->currentToken) {
            return null;
        }

        // If the token indicates the beginning of a list, parse it.
        if ($this->currentToken instanceof ListOpen) {
            return $this->parseList();
        }

        // If the token is an Atom or Number, check its value for special markers.
        if ($this->currentToken instanceof Atom || $this->currentToken instanceof Number) {
            // '*' marks an untagged response.
            if ($this->currentToken->value === '*') {
                return $this->parseUntaggedResponse();
            }

            // '+' marks a continuation response.
            if ($this->currentToken->value === '+') {
                return $this->parseContinuationResponse();
            }

            // If it's an ATOM and not '*' or '+', it's likely a tagged response.
            return $this->parseTaggedResponse();
        }

        return $this->parseElement();
    }

    /**
     * Parse an untagged response.
     *
     * An untagged response begins with the '*' token. It may contain
     * multiple elements, including lists and response codes.
     */
    protected function parseUntaggedResponse(): UntaggedResponse
    {
        // Capture the initial '*' token.
        $elements[] = clone $this->currentToken;

        $this->advance();

        // Collect all tokens until the end-of-response marker.
        while ($this->currentToken && ! $this->currentToken instanceof Crlf) {
            $elements[] = $this->parseElement();
        }

        // If the end-of-response marker (CRLF) is present, consume it.
        if ($this->currentToken && $this->currentToken instanceof Crlf) {
            $this->currentToken = null;
        } else {
            throw new ImapParserException('Unterminated untagged response');
        }

        return new UntaggedResponse($elements);
    }

    /**
     * Parse a continuation response.
     *
     * A continuation response starts with a '+' token, indicating
     * that the server expects additional data from the client.
     */
    protected function parseContinuationResponse(): ContinuationResponse
    {
        // Capture the initial '+' token.
        $elements[] = clone $this->currentToken;

        $this->advance();

        // Collect all tokens until the CRLF marker.
        while ($this->currentToken && ! $this->currentToken instanceof Crlf) {
            $elements[] = $this->parseElement();
        }

        // Consume the CRLF marker if present.
        if ($this->currentToken && $this->currentToken instanceof Crlf) {
            $this->currentToken = null;
        } else {
            throw new ImapParserException('Unterminated continuation response');
        }

        return new ContinuationResponse($elements);
    }

    /**
     * Parse a tagged response.
     *
     * A tagged response begins with a tag (which is not '*' or '+')
     * and is followed by a status and optional data.
     */
    protected function parseTaggedResponse(): TaggedResponse
    {
        // Capture the initial TAG token.
        $tokens[] = clone $this->currentToken;

        $this->advance();

        // Collect tokens until the end-of-response marker is reached.
        while ($this->currentToken && ! $this->currentToken instanceof Crlf) {
            $tokens[] = $this->parseElement();
        }

        // Consume the CRLF marker if present.
        if ($this->currentToken && $this->currentToken instanceof Crlf) {
            $this->currentToken = null;
        } else {
            throw new ImapParserException('Unterminated tagged response');
        }

        return new TaggedResponse($tokens);
    }

    /**
     * Parses a bracket group of elements delimited by '[' and ']'.
     *
     * Bracket groups are used to represent response codes.
     */
    protected function parseBracketGroup(): ResponseCodeData
    {
        // Consume the opening '[' token.
        $this->advance();

        $elements = [];

        while (
            $this->currentToken
            && ! $this->currentToken instanceof ResponseCodeClose
        ) {
            // Skip CRLF tokens that may appear inside bracket groups.
            if ($this->currentToken instanceof Crlf) {
                $this->advance();

                continue;
            }

            $elements[] = $this->parseElement();
        }

        if ($this->currentToken === null) {
            throw new ImapParserException('Unterminated bracket group in response');
        }

        // Consume the closing ']' token.
        $this->advance();

        return new ResponseCodeData($elements);
    }

    /**
     * Parses a list of elements delimited by '(' and ')'.
     *
     * Lists are handled recursively, as a list may contain nested lists.
     */
    protected function parseList(): ListData
    {
        // Consume the opening '(' token.
        $this->advance();

        $elements = [];

        // Continue to parse elements until we find the corresponding ')'.
        while (
            $this->currentToken
            && ! $this->currentToken instanceof ListClose
        ) {
            // Skip CRLF tokens that appear inside lists (after literals).
            if ($this->currentToken instanceof Crlf) {
                $this->advance();

                continue;
            }

            $elements[] = $this->parseElement();
        }

        // If we reached the end without finding a closing ')', throw an exception.
        if ($this->currentToken === null) {
            throw new ImapParserException('Unterminated list in response');
        }

        // Consume the closing ')' token.
        $this->advance();

        return new ListData($elements);
    }

    /**
     * Parses a single element, which might be a list or a simple token.
     */
    protected function parseElement(): Data|Token|null
    {
        // If there is no current token, return null.
        if ($this->currentToken === null) {
            return null;
        }

        // If the token indicates the start of a list, parse it as a list.
        if ($this->currentToken instanceof ListOpen) {
            return $this->parseList();
        }

        // If the token indicates the start of a group, parse it as a group.
        if ($this->currentToken instanceof ResponseCodeOpen) {
            return $this->parseBracketGroup();
        }

        // Otherwise, capture the current token.
        $token = clone $this->currentToken;

        $this->advance();

        return $token;
    }

    /**
     * Advance to the next token from the tokenizer.
     */
    protected function advance(): void
    {
        $this->currentToken = $this->tokenizer->nextToken();
    }
}
