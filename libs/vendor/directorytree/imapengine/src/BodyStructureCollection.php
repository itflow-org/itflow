<?php

namespace DirectoryTree\ImapEngine;

use Countable;
use DirectoryTree\ImapEngine\Connection\Responses\Data\ListData;
use DirectoryTree\ImapEngine\Connection\Tokens\Nil;
use DirectoryTree\ImapEngine\Connection\Tokens\Token;
use Illuminate\Contracts\Support\Arrayable;
use IteratorAggregate;
use JsonSerializable;
use Traversable;

/**
 * @implements IteratorAggregate<int, BodyStructurePart|BodyStructureCollection>
 */
class BodyStructureCollection implements Arrayable, Countable, IteratorAggregate, JsonSerializable
{
    /**
     * Constructor.
     *
     * @param  array<BodyStructurePart|BodyStructureCollection>  $parts
     */
    public function __construct(
        protected string $subtype = 'mixed',
        protected array $parameters = [],
        protected array $parts = [],
    ) {}

    /**
     * Parse a multipart BODYSTRUCTURE ListData into a BodyStructureCollection.
     */
    public static function fromListData(ListData $data, ?string $partNumber = null): static
    {
        $tokens = $data->tokens();

        $parts = [];
        $childIndex = 1;
        $subtypeIndex = null;

        foreach ($tokens as $index => $token) {
            if ($token instanceof Token && ! $token instanceof Nil) {
                $subtypeIndex = $index;

                break;
            }

            if (! $token instanceof ListData) {
                continue;
            }

            $childPartNumber = $partNumber ? "{$partNumber}.{$childIndex}" : (string) $childIndex;

            $parts[] = static::isMultipart($token)
                ? static::fromListData($token, $childPartNumber)
                : BodyStructurePart::fromListData($token, $childPartNumber);

            $childIndex++;
        }

        $parameters = [];

        if ($subtypeIndex) {
            foreach (array_slice($tokens, $subtypeIndex + 1) as $token) {
                if ($token instanceof ListData && ! static::isDispositionList($token)) {
                    $parameters = $token->toKeyValuePairs();

                    break;
                }
            }
        }

        return new static(
            $subtypeIndex ? strtolower($tokens[$subtypeIndex]->value) : 'mixed',
            $parameters,
            $parts
        );
    }

    /**
     * Determine if a ListData represents a multipart structure.
     */
    protected static function isMultipart(ListData $data): bool
    {
        return head($data->tokens()) instanceof ListData;
    }

    /**
     * Determine if a ListData represents a disposition (INLINE or ATTACHMENT).
     */
    protected static function isDispositionList(ListData $data): bool
    {
        $tokens = $data->tokens();

        if (count($tokens) < 2 || ! isset($tokens[0]) || ! $tokens[0] instanceof Token) {
            return false;
        }

        return in_array(strtoupper($tokens[0]->value), ['INLINE', 'ATTACHMENT']);
    }

    /**
     * Get the multipart subtype (mixed, alternative, related, etc.).
     */
    public function subtype(): string
    {
        return $this->subtype;
    }

    /**
     * Get the content type.
     */
    public function contentType(): string
    {
        return "multipart/{$this->subtype}";
    }

    /**
     * Get the parameters (e.g., boundary).
     */
    public function parameters(): array
    {
        return $this->parameters;
    }

    /**
     * Get the boundary parameter.
     */
    public function boundary(): ?string
    {
        return $this->parameters['boundary'] ?? null;
    }

    /**
     * Get the direct child parts.
     *
     * @return array<BodyStructurePart|BodyStructureCollection>
     */
    public function parts(): array
    {
        return $this->parts;
    }

    /**
     * Get all parts flattened (including nested parts).
     *
     * @return BodyStructurePart[]
     */
    public function flatten(): array
    {
        $flattened = [];

        foreach ($this->parts as $part) {
            if ($part instanceof self) {
                $flattened = array_merge($flattened, $part->flatten());
            } else {
                $flattened[] = $part;
            }
        }

        return $flattened;
    }

    /**
     * Find a part by its part number.
     */
    public function find(string $partNumber): BodyStructurePart|BodyStructureCollection|null
    {
        foreach ($this->parts as $part) {
            if ($part instanceof self) {
                if ($found = $part->find($partNumber)) {
                    return $found;
                }
            } elseif ($part->partNumber() === $partNumber) {
                return $part;
            }
        }

        return null;
    }

    /**
     * Get the text/plain part if available.
     */
    public function text(): ?BodyStructurePart
    {
        foreach ($this->flatten() as $part) {
            if ($part->isText()) {
                return $part;
            }
        }

        return null;
    }

    /**
     * Get the text/html part if available.
     */
    public function html(): ?BodyStructurePart
    {
        foreach ($this->flatten() as $part) {
            if ($part->isHtml()) {
                return $part;
            }
        }

        return null;
    }

    /**
     * Get all attachment parts.
     *
     * @return BodyStructurePart[]
     */
    public function attachments(): array
    {
        return array_values(array_filter(
            $this->flatten(),
            fn (BodyStructurePart $part) => $part->isAttachment()
        ));
    }

    /**
     * Determine if the collection has attachments.
     */
    public function hasAttachments(): bool
    {
        return count($this->attachments()) > 0;
    }

    /**
     * Get the count of attachments.
     */
    public function attachmentCount(): int
    {
        return count($this->attachments());
    }

    /**
     * Get the count of parts.
     */
    public function count(): int
    {
        return count($this->parts);
    }

    /**
     * Get an iterator for the parts.
     */
    public function getIterator(): Traversable
    {
        yield from $this->parts;
    }

    /**
     * Get the array representation.
     */
    public function toArray(): array
    {
        return [
            'subtype' => $this->subtype,
            'parameters' => $this->parameters,
            'content_type' => $this->contentType(),
            'parts' => array_map(fn (Arrayable $part) => $part->toArray(), $this->parts),
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
