<?php

namespace DirectoryTree\ImapEngine;

use GuzzleHttp\Psr7\Utils;
use Illuminate\Contracts\Support\Arrayable;
use JsonSerializable;
use Psr\Http\Message\StreamInterface;
use Symfony\Component\Mime\MimeTypes;
use ZBateson\MailMimeParser\Message\IMessagePart;

class Attachment implements Arrayable, JsonSerializable
{
    /**
     * Constructor.
     */
    public function __construct(
        protected ?string $filename,
        protected ?string $contentId,
        protected string $contentType,
        protected ?string $contentDisposition,
        protected StreamInterface $contentStream,
    ) {}

    /**
     * Get attachments from a parsed message.
     *
     * @return Attachment[]
     */
    public static function parsed(MessageInterface $message): array
    {
        $attachments = [];

        foreach ($message->parse()->getAllAttachmentParts() as $part) {
            if (static::isForwardedMessage($part)) {
                $attachments = array_merge($attachments, (new FileMessage($part->getContent()))->attachments());
            } else {
                $attachments[] = new Attachment(
                    $part->getFilename(),
                    $part->getContentId(),
                    $part->getContentType(),
                    $part->getContentDisposition(),
                    $part->getBinaryContentStream() ?? Utils::streamFor(''),
                );
            }
        }

        return $attachments;
    }

    /**
     * Get attachments from a message's body structure using lazy streams.
     *
     * @return Attachment[]
     */
    public static function lazy(Message $message): array
    {
        $attachments = [];

        foreach ($message->bodyStructure(fetch: true)?->attachments() ?? [] as $part) {
            $attachments[] = new Attachment(
                $part->filename(),
                $part->id(),
                $part->contentType(),
                $part->disposition()?->type()?->value,
                new Support\LazyBodyPartStream($message, $part),
            );
        }

        return $attachments;
    }

    /**
     * Determine if the attachment should be treated as an embedded forwarded message.
     */
    protected static function isForwardedMessage(IMessagePart $part): bool
    {
        return empty($part->getFilename())
            && strtolower((string) $part->getContentType()) === 'message/rfc822'
            && strtolower((string) $part->getContentDisposition()) !== 'attachment';
    }

    /**
     * Get the attachment's filename.
     */
    public function filename(): ?string
    {
        return $this->filename;
    }

    /**
     * Get the attachment's content ID.
     */
    public function contentId(): ?string
    {
        return $this->contentId;
    }

    /**
     * Get the attachment's content type.
     */
    public function contentType(): string
    {
        return $this->contentType;
    }

    /**
     * Get the attachment's content disposition.
     */
    public function contentDisposition(): string
    {
        return $this->contentDisposition;
    }

    /**
     * Get the attachment's contents.
     */
    public function contents(): string
    {
        return $this->contentStream->getContents();
    }

    /**
     * Get the attachment's content stream.
     */
    public function contentStream(): StreamInterface
    {
        return $this->contentStream;
    }

    /**
     * Save the attachment to a file.
     */
    public function save(string $path): false|int
    {
        return file_put_contents($path, $this->contents());
    }

    /**
     * Get the attachment's extension.
     */
    public function extension(): ?string
    {
        if ($ext = pathinfo($this->filename ?? '', PATHINFO_EXTENSION)) {
            return $ext;
        }

        if ($ext = (MimeTypes::getDefault()->getExtensions($this->contentType)[0] ?? null)) {
            return $ext;
        }

        return null;
    }

    /**
     * Get the array representation of the attachment.
     */
    public function toArray(): array
    {
        return [
            'filename' => $this->filename,
            'content_type' => $this->contentType,
            'contents' => $this->contents(),
        ];
    }

    /**
     * Get the JSON representation of the attachment.
     */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
