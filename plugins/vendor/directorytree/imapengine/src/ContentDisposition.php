<?php

namespace DirectoryTree\ImapEngine;

use DirectoryTree\ImapEngine\Connection\Responses\Data\ListData;
use DirectoryTree\ImapEngine\Connection\Tokens\Token;
use DirectoryTree\ImapEngine\Enums\ContentDispositionType;
use Illuminate\Contracts\Support\Arrayable;
use JsonSerializable;

/**
 * @see https://datatracker.ietf.org/doc/html/rfc2183
 */
class ContentDisposition implements Arrayable, JsonSerializable
{
    /**
     * Constructor.
     */
    public function __construct(
        protected ContentDispositionType $type,
        protected array $parameters = [],
    ) {}

    /**
     * Parse the disposition from tokens.
     *
     * @param  array<Token|ListData>  $tokens
     */
    public static function parse(array $tokens): ?static
    {
        for ($i = 8; $i < count($tokens); $i++) {
            if (! $tokens[$i] instanceof ListData) {
                continue;
            }

            $innerTokens = $tokens[$i]->tokens();

            if (! isset($innerTokens[0]) || ! $innerTokens[0] instanceof Token) {
                continue;
            }

            if (! $type = ContentDispositionType::tryFrom(strtolower($innerTokens[0]->value))) {
                continue;
            }

            $parameters = isset($innerTokens[1]) && $innerTokens[1] instanceof ListData
                ? $innerTokens[1]->toKeyValuePairs()
                : [];

            return new self($type, $parameters);
        }

        return null;
    }

    /**
     * Get the disposition type.
     */
    public function type(): ContentDispositionType
    {
        return $this->type;
    }

    /**
     * Get the disposition parameters.
     */
    public function parameters(): array
    {
        return $this->parameters;
    }

    /**
     * Get a specific parameter value.
     */
    public function parameter(string $name): ?string
    {
        return $this->parameters[strtolower($name)] ?? null;
    }

    /**
     * Get the filename parameter.
     */
    public function filename(): ?string
    {
        return $this->parameters['filename'] ?? null;
    }

    /**
     * Determine if this is an attachment disposition.
     */
    public function isAttachment(): bool
    {
        return $this->type === ContentDispositionType::Attachment;
    }

    /**
     * Determine if this is an inline disposition.
     */
    public function isInline(): bool
    {
        return $this->type === ContentDispositionType::Inline;
    }

    /**
     * Get the array representation.
     */
    public function toArray(): array
    {
        return [
            'type' => $this->type->value,
            'parameters' => $this->parameters,
        ];
    }

    /**
     * Get the JSON representation.
     */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
