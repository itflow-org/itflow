<?php

namespace DirectoryTree\ImapEngine;

use DateTimeInterface;
use Stringable;
use Symfony\Component\Mime\Email;

class DraftMessage implements Stringable
{
    /**
     * The underlying Symfony Email instance.
     */
    protected Email $message;

    /**
     * Constructor.
     */
    public function __construct(
        protected ?string $from = null,
        protected array|string $to = [],
        protected array|string $cc = [],
        protected array|string $bcc = [],
        protected ?string $subject = null,
        protected ?string $text = null,
        protected ?string $html = null,
        protected array $headers = [],
        protected array $attachments = [],
        protected ?DateTimeInterface $date = null,
    ) {
        $this->message = new Email;

        if ($this->from) {
            $this->message->from($this->from);
        }

        if ($this->subject) {
            $this->message->subject($this->subject);
        }

        if ($this->text) {
            $this->message->text($this->text);
        }

        if ($this->html) {
            $this->message->html($this->html);
        }

        if ($this->date) {
            $this->message->date($this->date);
        }

        if (! empty($this->to)) {
            $this->message->to(...(array) $this->to);
        }

        if (! empty($this->cc)) {
            $this->message->cc(...(array) $this->cc);
        }

        if (! empty($this->bcc)) {
            $this->message->bcc(...(array) $this->bcc);
        }

        foreach ($this->attachments as $attachment) {
            match (true) {
                $attachment instanceof Attachment => $this->message->attach(
                    $attachment->contents(),
                    $attachment->filename(),
                    $attachment->contentType()
                ),

                is_resource($attachment) => $this->message->attach($attachment),

                default => $this->message->attachFromPath($attachment),
            };
        }

        foreach ($this->headers as $name => $value) {
            $this->message->getHeaders()->addTextHeader($name, $value);
        }
    }

    /**
     * Get the underlying Symfony Email instance.
     */
    public function email(): Email
    {
        return $this->message;
    }

    /**
     * Get the email as a string.
     */
    public function __toString(): string
    {
        return $this->message->toString();
    }
}
