<?php
/**
 * This file is part of the ZBateson\MailMimeParser project.
 *
 * @license http://opensource.org/licenses/bsd-license.php BSD
 */

namespace ZBateson\MailMimeParser;

use GuzzleHttp\Psr7;
use Psr\Http\Message\StreamInterface;
use Psr\Log\LoggerInterface;
use ZBateson\MailMimeParser\Header\HeaderConsts;
use ZBateson\MailMimeParser\Message\Helper\MultipartHelper;
use ZBateson\MailMimeParser\Message\Helper\PrivacyHelper;
use ZBateson\MailMimeParser\Message\IMessagePart;
use ZBateson\MailMimeParser\Message\MimePart;
use ZBateson\MailMimeParser\Message\PartChildrenContainer;
use ZBateson\MailMimeParser\Message\PartFilter;
use ZBateson\MailMimeParser\Message\PartHeaderContainer;
use ZBateson\MailMimeParser\Message\PartStreamContainer;

/**
 * An email message.
 *
 * The message could represent a simple text email, a multipart message with
 * children, or a non-mime message containing UUEncoded parts.
 *
 * @author Zaahid Bateson
 */
class Message extends MimePart implements IMessage
{
    /**
     * @var MultipartHelper service providing functions for multipart messages.
     */
    private MultipartHelper $multipartHelper;

    /**
     * @var PrivacyHelper service providing functions for multipart/signed
     *      messages.
     */
    private PrivacyHelper $privacyHelper;

    public function __construct(
        ?LoggerInterface $logger = null,
        ?PartStreamContainer $streamContainer = null,
        ?PartHeaderContainer $headerContainer = null,
        ?PartChildrenContainer $partChildrenContainer = null,
        ?MultipartHelper $multipartHelper = null,
        ?PrivacyHelper $privacyHelper = null,
        string $defaultFallbackCharset = 'ISO-8859-1'
    ) {
        parent::__construct(
            null,
            $logger,
            $streamContainer,
            $headerContainer,
            $partChildrenContainer,
            $defaultFallbackCharset
        );
        $di = MailMimeParser::getGlobalContainer();
        $this->multipartHelper = $multipartHelper ?? $di->get(MultipartHelper::class);
        $this->privacyHelper = $privacyHelper ?? $di->get(PrivacyHelper::class);
    }

    /**
     * Convenience method to parse a handle or string into an IMessage without
     * requiring including MailMimeParser, instantiating it, and calling parse.
     *
     * If the passed $resource is a resource handle or StreamInterface, the
     * resource must remain open while the returned IMessage object exists.
     * Pass true as the second argument to have the resource automatically
     * closed when the returned IMessage is destroyed, or pass false to
     * manage the resource lifecycle yourself.
     *
     * @param resource|StreamInterface|string $resource The resource handle to
     *        the input stream of the mime message, or a string containing a
     *        mime message.
     * @param bool $autoClose pass true to have the resource closed
     *        automatically when the returned IMessage is destroyed.
     */
    public static function from(mixed $resource, bool $autoClose) : IMessage
    {
        static $mmp = null;
        if ($mmp === null) {
            $mmp = new MailMimeParser();
        }
        return $mmp->parse($resource, $autoClose);
    }

    /**
     * Returns true if the current part is a mime part.
     *
     * The message is considered 'mime' if it has either a Content-Type or
     * MIME-Version header defined.
     *
     */
    public function isMime() : bool
    {
        $contentType = $this->getHeaderValue(HeaderConsts::CONTENT_TYPE);
        $mimeVersion = $this->getHeaderValue(HeaderConsts::MIME_VERSION);
        return ($contentType !== null || $mimeVersion !== null);
    }

    public function getSubject() : ?string
    {
        return $this->getHeaderValue(HeaderConsts::SUBJECT);
    }

    public function getTextPart(int $index = 0) : ?IMessagePart
    {
        return $this->getPart(
            $index,
            PartFilter::fromInlineContentType('text/plain')
        );
    }

    public function getTextPartCount() : int
    {
        return $this->getPartCount(
            PartFilter::fromInlineContentType('text/plain')
        );
    }

    public function getHtmlPart(int $index = 0) : ?IMessagePart
    {
        return $this->getPart(
            $index,
            PartFilter::fromInlineContentType('text/html')
        );
    }

    public function getHtmlPartCount() : int
    {
        return $this->getPartCount(
            PartFilter::fromInlineContentType('text/html')
        );
    }

    public function getTextStream(int $index = 0, string $charset = MailMimeParser::DEFAULT_CHARSET) : ?StreamInterface
    {
        $textPart = $this->getTextPart($index);
        if ($textPart !== null) {
            return $textPart->getContentStream($charset);
        }
        return null;
    }

    public function getTextContent(int $index = 0, string $charset = MailMimeParser::DEFAULT_CHARSET) : ?string
    {
        $part = $this->getTextPart($index);
        if ($part !== null) {
            return $part->getContent($charset);
        }
        return null;
    }

    public function getHtmlStream(int $index = 0, string $charset = MailMimeParser::DEFAULT_CHARSET) : ?StreamInterface
    {
        $htmlPart = $this->getHtmlPart($index);
        if ($htmlPart !== null) {
            return $htmlPart->getContentStream($charset);
        }
        return null;
    }

    public function getHtmlContent(int $index = 0, string $charset = MailMimeParser::DEFAULT_CHARSET) : ?string
    {
        $part = $this->getHtmlPart($index);
        if ($part !== null) {
            return $part->getContent($charset);
        }
        return null;
    }

    public function setTextPart(mixed $resource, string $charset = 'UTF-8') : static
    {
        $this->multipartHelper
            ->setContentPartForMimeType(
                $this,
                'text/plain',
                $resource,
                $charset
            );
        return $this;
    }

    public function setHtmlPart(mixed $resource, string $charset = 'UTF-8') : static
    {
        $this->multipartHelper
            ->setContentPartForMimeType(
                $this,
                'text/html',
                $resource,
                $charset
            );
        return $this;
    }

    public function removeTextPart(int $index = 0) : static
    {
        $this->multipartHelper
            ->removePartByMimeType(
                $this,
                'text/plain',
                $index
            );
        return $this;
    }

    public function removeAllTextParts(bool $moveRelatedPartsBelowMessage = true) : static
    {
        $this->multipartHelper
            ->removeAllContentPartsByMimeType(
                $this,
                'text/plain',
                $moveRelatedPartsBelowMessage
            );
        return $this;
    }

    public function removeHtmlPart(int $index = 0) : static
    {
        $this->multipartHelper
            ->removePartByMimeType(
                $this,
                'text/html',
                $index
            );
        return $this;
    }

    public function removeAllHtmlParts(bool $moveRelatedPartsBelowMessage = true) : static
    {
        $this->multipartHelper
            ->removeAllContentPartsByMimeType(
                $this,
                'text/html',
                $moveRelatedPartsBelowMessage
            );
        return $this;
    }

    public function getAttachmentPart(int $index) : ?IMessagePart
    {
        return $this->getPart(
            $index,
            PartFilter::fromAttachmentFilter()
        );
    }

    public function getAllAttachmentParts() : array
    {
        return $this->getAllParts(
            PartFilter::fromAttachmentFilter()
        );
    }

    public function getAttachmentCount() : int
    {
        return \count($this->getAllAttachmentParts());
    }

    public function addAttachmentPart(mixed $resource, string $mimeType, ?string $filename = null, string $disposition = 'attachment', string $encoding = 'base64') : static
    {
        $this->multipartHelper
            ->createAndAddPartForAttachment(
                $this,
                $resource,
                $mimeType,
                (\strcasecmp($disposition, 'inline') === 0) ? 'inline' : 'attachment',
                $filename,
                $encoding
            );
        return $this;
    }

    public function addAttachmentPartFromFile(string $filePath, string $mimeType, ?string $filename = null, string $disposition = 'attachment', string $encoding = 'base64') : static
    {
        $handle = Psr7\Utils::streamFor(\fopen($filePath, 'r'));
        if ($filename === null) {
            $filename = \basename($filePath);
        }
        $this->addAttachmentPart($handle, $mimeType, $filename, $disposition, $encoding);
        return $this;
    }

    public function removeAttachmentPart(int $index) : static
    {
        $part = $this->getAttachmentPart($index);
        $this->removePart($part);
        return $this;
    }

    public function getSignedMessageStream() : ?StreamInterface
    {
        return $this
            ->privacyHelper
            ->getSignedMessageStream($this);
    }

    public function getSignedMessageAsString() : ?string
    {
        return $this
            ->privacyHelper
            ->getSignedMessageAsString($this);
    }

    public function getSignaturePart() : ?IMessagePart
    {
        if (\strcasecmp($this->getContentType(), 'multipart/signed') === 0) {
            return $this->getChild(1);
        }
        return null;
    }

    public function setAsMultipartSigned(string $micalg, string $protocol) : static
    {
        $this->privacyHelper
            ->setMessageAsMultipartSigned($this, $micalg, $protocol);
        return $this;
    }

    public function setSignature(string $body) : static
    {
        $this->privacyHelper
            ->setSignature($this, $body);
        return $this;
    }

    public function getMessageId() : ?string
    {
        return $this->getHeaderValue(HeaderConsts::MESSAGE_ID);
    }

    public function getErrorLoggingContextName() : string
    {
        $params = '';
        if (!empty($this->getMessageId())) {
            $params .= ', message-id=' . $this->getMessageId();
        }
        $params .= ', content-type=' . $this->getContentType();
        $nsClass = static::class;
        $pos = \strrpos($nsClass, '\\');
        $class = ($pos !== false) ? \substr($nsClass, $pos + 1) : $nsClass;
        return $class . '(' . \spl_object_id($this) . $params . ')';
    }
}
