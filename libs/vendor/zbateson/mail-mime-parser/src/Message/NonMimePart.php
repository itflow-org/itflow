<?php
/**
 * This file is part of the ZBateson\MailMimeParser project.
 *
 * @license http://opensource.org/licenses/bsd-license.php BSD
 */

namespace ZBateson\MailMimeParser\Message;

use Psr\Log\LoggerInterface;

/**
 * Represents part of a non-mime message.
 *
 * @author Zaahid Bateson
 */
abstract class NonMimePart extends MessagePart
{
    protected string $fallbackCharset = 'ISO-8859-1';

    public function __construct(
        LoggerInterface $logger,
        PartStreamContainer $partStreamContainer,
        ?IMimePart $parent = null,
        string $defaultFallbackCharset = 'ISO-8859-1'
    ) {
        parent::__construct($logger, $partStreamContainer, $parent);
        $this->fallbackCharset = $defaultFallbackCharset;
    }
    /**
     * Returns true.
     *
     */
    public function isTextPart() : bool
    {
        return true;
    }

    /**
     * Returns text/plain
     */
    public function getContentType(string $default = 'text/plain') : string
    {
        return $default;
    }

    /**
     * Returns the configured fallback charset (ISO-8859-1 by default).
     */
    public function getCharset() : ?string
    {
        return $this->fallbackCharset;
    }

    /**
     * Returns 'inline'.
     */
    public function getContentDisposition(?string $default = 'inline') : ?string
    {
        return 'inline';
    }

    /**
     * Returns '7bit'.
     */
    public function getContentTransferEncoding(?string $default = '7bit') : ?string
    {
        return '7bit';
    }

    /**
     * Returns false.
     *
     */
    public function isMime() : bool
    {
        return false;
    }

    /**
     * Returns the Content ID of the part.
     *
     * NonMimeParts do not have a Content ID, and so this simply returns null.
     *
     */
    public function getContentId() : ?string
    {
        return null;
    }
}
