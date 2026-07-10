<?php

namespace DirectoryTree\ImapEngine;

use DirectoryTree\ImapEngine\Connection\Responses\Data\ListData;
use DirectoryTree\ImapEngine\Connection\Tokens\Nil;
use DirectoryTree\ImapEngine\Connection\Tokens\Token;
use Illuminate\Contracts\Support\Arrayable;
use JsonSerializable;

class BodyStructurePart implements Arrayable, JsonSerializable
{
    /**
     * Constructor.
     */
    public function __construct(
        protected string $partNumber,
        protected string $type,
        protected string $subtype,
        protected array $parameters = [],
        protected ?string $id = null,
        protected ?string $description = null,
        protected ?string $encoding = null,
        protected ?int $size = null,
        protected ?int $lines = null,
        protected ?ContentDisposition $disposition = null,
    ) {}

    /**
     * Parse a single part BODYSTRUCTURE ListData into a BodyStructurePart.
     */
    public static function fromListData(ListData $data, string $partNumber = '1'): static
    {
        return static::parse($data->tokens(), $partNumber);
    }

    /**
     * Parse a single (non-multipart) part.
     *
     * @param  array<Token|ListData>  $tokens
     */
    protected static function parse(array $tokens, string $partNumber): static
    {
        return new static(
            partNumber: $partNumber,
            type: strtolower(static::tokenValueAt($tokens, 0) ?? 'text'),
            subtype: strtolower(static::tokenValueAt($tokens, 1) ?? 'plain'),
            parameters: isset($tokens[2]) && $tokens[2] instanceof ListData ? $tokens[2]->toKeyValuePairs() : [],
            id: static::tokenValueAt($tokens, 3),
            description: static::tokenValueAt($tokens, 4),
            encoding: static::tokenValueAt($tokens, 5),
            size: static::tokenIntValueAt($tokens, 6),
            lines: static::tokenIntValueAt($tokens, 7),
            disposition: ContentDisposition::parse($tokens),
        );
    }

    /**
     * Safely read a scalar token value from the parsed body structure.
     *
     * @param  array<Token|ListData>  $tokens
     */
    protected static function tokenValueAt(array $tokens, int $index): ?string
    {
        $token = $tokens[$index] ?? null;

        if (! $token instanceof Token || $token instanceof Nil) {
            return null;
        }

        return $token->value;
    }

    /**
     * Safely read an integer token value from the parsed body structure.
     *
     * @param  array<Token|ListData>  $tokens
     */
    protected static function tokenIntValueAt(array $tokens, int $index): ?int
    {
        $value = static::tokenValueAt($tokens, $index);

        return $value === null ? null : (int) $value;
    }

    /**
     * Get the part number (e.g., "1", "1.2", "2.1.3").
     */
    public function partNumber(): string
    {
        return $this->partNumber;
    }

    /**
     * Get the MIME type (e.g., "text", "image", "multipart").
     */
    public function type(): string
    {
        return $this->type;
    }

    /**
     * Get the MIME subtype (e.g., "plain", "html", "jpeg", "mixed").
     */
    public function subtype(): string
    {
        return $this->subtype;
    }

    /**
     * Get the full content type (e.g., "text/plain", "multipart/alternative").
     */
    public function contentType(): string
    {
        return "{$this->type}/{$this->subtype}";
    }

    /**
     * Get the parameters (e.g., charset, boundary).
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
     * Get the content ID.
     */
    public function id(): ?string
    {
        return $this->id;
    }

    /**
     * Get the content description.
     */
    public function description(): ?string
    {
        return $this->description;
    }

    /**
     * Get the content transfer encoding.
     */
    public function encoding(): ?string
    {
        return $this->encoding;
    }

    /**
     * Get the size in bytes.
     */
    public function size(): ?int
    {
        return $this->size;
    }

    /**
     * Get the number of lines (for text parts).
     */
    public function lines(): ?int
    {
        return $this->lines;
    }

    /**
     * Get the content disposition.
     */
    public function disposition(): ?ContentDisposition
    {
        return $this->disposition;
    }

    /**
     * Get the filename from disposition parameters.
     */
    public function filename(): ?string
    {
        return $this->disposition?->filename() ?? $this->parameters['name'] ?? null;
    }

    /**
     * Get the charset from parameters.
     */
    public function charset(): ?string
    {
        return $this->parameters['charset'] ?? null;
    }

    /**
     * Determine if this is a text part.
     */
    public function isText(): bool
    {
        return $this->type === 'text' && $this->subtype === 'plain';
    }

    /**
     * Determine if this is an HTML part.
     */
    public function isHtml(): bool
    {
        return $this->type === 'text' && $this->subtype === 'html';
    }

    /**
     * Determine if this is an attachment.
     */
    public function isAttachment(): bool
    {
        if ($this->disposition?->isAttachment()) {
            return true;
        }

        // Inline parts are not attachments.
        if ($this->disposition?->isInline()) {
            return false;
        }

        // Consider non-text/html parts with filenames as attachments.
        if ($this->filename() && ! $this->isText() && ! $this->isHtml()) {
            return true;
        }

        return false;
    }

    /**
     * Determine if this is an inline part.
     */
    public function isInline(): bool
    {
        return $this->disposition?->isInline() ?? false;
    }

    /**
     * Get the array representation.
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'type' => $this->type,
            'size' => $this->size,
            'lines' => $this->lines,
            'subtype' => $this->subtype,
            'encoding' => $this->encoding,
            'parameters' => $this->parameters,
            'part_number' => $this->partNumber,
            'description' => $this->description,
            'content_type' => $this->contentType(),
            'disposition' => $this->disposition?->toArray(),
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
