<?php

namespace DirectoryTree\ImapEngine;

use DirectoryTree\ImapEngine\Exceptions\RuntimeException;
use ZBateson\MailMimeParser\Header\IHeader;
use ZBateson\MailMimeParser\Header\IHeaderPart;
use ZBateson\MailMimeParser\Header\Part\AddressPart;
use ZBateson\MailMimeParser\Header\Part\ContainerPart;
use ZBateson\MailMimeParser\Header\Part\NameValuePart;
use ZBateson\MailMimeParser\IMessage;

trait HasParsedMessage
{
    /**
     * The parsed message.
     */
    protected ?IMessage $message = null;

    /**
     * Get all headers from the message.
     */
    public function headers(): array
    {
        return $this->parse()->getAllHeaders();
    }

    /**
     * Get a header from the message.
     */
    public function header(string $name, int $offset = 0): ?IHeader
    {
        return $this->parse()->getHeader($name, $offset);
    }

    /**
     * Get addresses from the given header.
     *
     * @return Address[]
     */
    public function addresses(string $header): array
    {
        $parts = $this->header($header)?->getParts() ?? [];

        $addresses = array_map(fn (IHeaderPart $part) => match (true) {
            $part instanceof AddressPart => new Address($part->getEmail(), $part->getName()),
            $part instanceof NameValuePart => new Address($part->getName(), $part->getValue()),
            $part instanceof ContainerPart => new Address($part->getValue(), ''),
            default => null,
        }, $parts);

        return array_filter($addresses);
    }

    /**
     * Parse the message into a MailMimeMessage instance.
     */
    public function parse(): IMessage
    {
        if ($this->isEmpty()) {
            throw new RuntimeException('Cannot parse an empty message');
        }

        return $this->message ??= MessageParser::parse((string) $this);
    }

    /**
     * Determine if the message is empty.
     */
    abstract public function isEmpty(): bool;

    /**
     * Get the string representation of the message.
     */
    abstract public function __toString(): string;
}
