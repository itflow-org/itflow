<?php
/**
 * This file is part of the ZBateson\MailMimeParser project.
 *
 * @license http://opensource.org/licenses/bsd-license.php BSD
 */

namespace ZBateson\MailMimeParser\Header\Part;

use Psr\Log\LoggerInterface;
use ZBateson\MbWrapper\MbWrapper;

/**
 * Represents a name/value parameter part of a header.
 *
 * @author Zaahid Bateson
 */
class ParameterPart extends NameValuePart
{
    /**
     * @var string the RFC-1766 language tag if set.
     */
    protected ?string $language = null;

    /**
     * @var string charset of content if set.
     */
    protected ?string $charset = null;

    /**
     * @var int the zero-based index of the part if part of a 'continuation' in
     *      an RFC-2231 split parameter.
     */
    protected ?int $index = null;

    /**
     * @var bool true if the part is an RFC-2231 encoded part, and the value
     *      needs to be decoded.
     */
    protected bool $encoded = false;

    /**
     * @param HeaderPart[] $nameParts
     */
    public function __construct(
        LoggerInterface $logger,
        MbWrapper $charsetConverter,
        array $nameParts,
        ContainerPart $valuePart
    ) {
        parent::__construct($logger, $charsetConverter, $nameParts, $valuePart->children);
    }

    protected function getNameFromParts(array $parts) : string
    {
        $name = parent::getNameFromParts($parts);
        if (\preg_match('~^\s*([^\*]+)\*(\d*)(\*)?$~', $name, $matches)) {
            $name = $matches[1];
            $this->index = ($matches[2] !== '') ? (int) ($matches[2]) : null;
            $this->encoded = (($matches[2] === '') || !empty($matches[3]));
        }
        return $name;
    }

    protected function decodePartValue(string $value, ?string $charset = null) : string
    {
        if ($charset !== null) {
            return $this->convertEncoding(\rawurldecode($value), $charset, true);
        }
        return $this->convertEncoding(\rawurldecode($value));
    }

    protected function getValueFromParts(array $parts) : string
    {
        $value = parent::getValueFromParts($parts);
        if ($this->encoded && \preg_match('~^([^\']*)\'?([^\']*)\'?(.*)$~', $value, $matches)) {
            $this->charset = (!empty($matches[1]) && !empty($matches[3])) ? $matches[1] : $this->charset;
            $this->language = (!empty($matches[2])) ? $matches[2] : $this->language;
            $ev = (empty($matches[3])) ? $matches[1] : $matches[3];
            // only if it's not part of a SplitParameterPart
            if ($this->index === null) {
                // subsequent parts are decoded as a SplitParameterPart since only
                // the first part are supposed to have charset/language fields
                return $this->decodePartValue($ev, $this->charset);
            }
            return $ev;
        }
        return $value;
    }

    /**
     * Returns the charset if the part is an RFC-2231 part with a charset set.
     */
    public function getCharset() : ?string
    {
        return $this->charset;
    }

    /**
     * Returns the RFC-1766 (or subset) language tag, if the parameter is an
     * RFC-2231 part with a language tag set.
     *
     * @return ?string the language if set, or null if not
     */
    public function getLanguage() : ?string
    {
        return $this->language;
    }

    public function isUrlEncoded() : bool
    {
        return $this->encoded;
    }

    public function getIndex() : ?int
    {
        return $this->index;
    }
}
