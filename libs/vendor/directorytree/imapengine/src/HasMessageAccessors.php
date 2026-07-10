<?php

namespace DirectoryTree\ImapEngine;

use Carbon\Carbon;
use Carbon\CarbonInterface;
use ZBateson\MailMimeParser\Header\DateHeader;
use ZBateson\MailMimeParser\Header\HeaderConsts;
use ZBateson\MailMimeParser\Header\IHeader;
use ZBateson\MailMimeParser\Header\IHeaderPart;
use ZBateson\MailMimeParser\IMessage;

trait HasMessageAccessors
{
    /**
     * Parse the message into a MailMimeMessage instance.
     */
    abstract public function parse(): IMessage;

    /**
     * Get addresses from the given header.
     *
     * @return Address[]
     */
    abstract public function addresses(string $header): array;

    /**
     * Get a header from the message.
     */
    abstract public function header(string $name, int $offset = 0): ?IHeader;

    /**
     * Get the message date and time.
     */
    public function date(): ?CarbonInterface
    {
        if (! $header = $this->header(HeaderConsts::DATE)) {
            return null;
        }

        if (! $header instanceof DateHeader) {
            return null;
        }

        if (! $date = $header->getDateTime()) {
            return null;
        }

        return Carbon::instance($date);
    }

    /**
     * Get the message's message-id.
     */
    public function messageId(): ?string
    {
        return $this->header(HeaderConsts::MESSAGE_ID)?->getValue();
    }

    /**
     * Get the message's subject.
     */
    public function subject(): ?string
    {
        return $this->header(HeaderConsts::SUBJECT)?->getValue();
    }

    /**
     * Get the FROM address.
     */
    public function from(): ?Address
    {
        return head($this->addresses(HeaderConsts::FROM)) ?: null;
    }

    /**
     * Get the SENDER address.
     */
    public function sender(): ?Address
    {
        return head($this->addresses(HeaderConsts::SENDER)) ?: null;
    }

    /**
     * Get the REPLY-TO address.
     */
    public function replyTo(): ?Address
    {
        return head($this->addresses(HeaderConsts::REPLY_TO)) ?: null;
    }

    /**
     * Get the IN-REPLY-TO message identifier(s).
     *
     * @return string[]
     */
    public function inReplyTo(): array
    {
        $parts = $this->header(HeaderConsts::IN_REPLY_TO)?->getParts() ?? [];

        $values = array_map(fn (IHeaderPart $part) => $part->getValue(), $parts);

        return array_values(array_filter($values));
    }

    /**
     * Get the TO addresses.
     *
     * @return Address[]
     */
    public function to(): array
    {
        return $this->addresses(HeaderConsts::TO);
    }

    /**
     * Get the CC addresses.
     *
     * @return Address[]
     */
    public function cc(): array
    {
        return $this->addresses(HeaderConsts::CC);
    }

    /**
     * Get the BCC addresses.
     *
     * @return Address[]
     */
    public function bcc(): array
    {
        return $this->addresses(HeaderConsts::BCC);
    }

    /**
     * Get the message's HTML content.
     */
    public function html(): ?string
    {
        return $this->parse()->getHtmlContent();
    }

    /**
     * Get the message's text content.
     */
    public function text(): ?string
    {
        return $this->parse()->getTextContent();
    }

    /**
     * Get the message's attachments.
     *
     * @return Attachment[]
     */
    public function attachments(): array
    {
        return Attachment::parsed($this);
    }

    /**
     * Determine if the message has attachments.
     */
    public function hasAttachments(): bool
    {
        return $this->attachmentCount() > 0;
    }

    /**
     * Get the count of attachments.
     */
    public function attachmentCount(): int
    {
        return $this->parse()->getAttachmentCount();
    }
}
