<?php

namespace DirectoryTree\ImapEngine;

use Carbon\CarbonInterface;
use Stringable;
use ZBateson\MailMimeParser\Header\IHeader;
use ZBateson\MailMimeParser\IMessage;
use ZBateson\MailMimeParser\Message as MailMimeMessage;

interface MessageInterface extends FlaggableInterface, Stringable
{
    /**
     * Get the message's identifier.
     */
    public function uid(): int;

    /**
     * Get the message's size in bytes (RFC822.SIZE).
     */
    public function size(): ?int;

    /**
     * Get the message date and time.
     */
    public function date(): ?CarbonInterface;

    /**
     * Get the message's subject.
     */
    public function subject(): ?string;

    /**
     * Get the 'From' address.
     */
    public function from(): ?Address;

    /**
     * Get the 'Sender' address.
     */
    public function sender(): ?Address;

    /**
     * Get the message's 'Message-ID'.
     */
    public function messageId(): ?string;

    /**
     * Get the 'Reply-To' address.
     */
    public function replyTo(): ?Address;

    /**
     * Get the 'In-Reply-To' message identifier(s).
     *
     * @return string[]
     */
    public function inReplyTo(): array;

    /**
     * Get the 'To' addresses.
     *
     * @return Address[]
     */
    public function to(): array;

    /**
     * Get the 'CC' addresses.
     *
     * @return Address[]
     */
    public function cc(): array;

    /**
     * Get the 'BCC' addresses.
     *
     * @return Address[]
     */
    public function bcc(): array;

    /**
     * Get the message's attachments.
     *
     * @return Attachment[]
     */
    public function attachments(): array;

    /**
     * Determine if the message has attachments.
     */
    public function hasAttachments(): bool;

    /**
     * Get the count of attachments.
     */
    public function attachmentCount(): int;

    /**
     * Get addresses from the given header.
     *
     * @return Address[]
     */
    public function addresses(string $header): array;

    /**
     * Get the message's HTML content.
     */
    public function html(): ?string;

    /**
     * Get the message's text content.
     */
    public function text(): ?string;

    /**
     * Get all headers from the message.
     */
    public function headers(): array;

    /**
     * Get a header from the message.
     */
    public function header(string $name, int $offset = 0): ?IHeader;

    /**
     * Parse the message into a MailMimeMessage instance.
     */
    public function parse(): IMessage;

    /**
     * Get the message's body structure.
     */
    public function bodyStructure(): ?BodyStructureCollection;

    /**
     * Determine if the message has body structure data.
     */
    public function hasBodyStructure(): bool;

    /**
     * Fetch a specific body part by part number.
     */
    public function bodyPart(string $partNumber, bool $peek = true): ?string;

    /**
     * Determine if the message is the same as another message.
     */
    public function is(MessageInterface $message): bool;

    /**
     * Get the string representation of the message.
     */
    public function __toString(): string;
}
