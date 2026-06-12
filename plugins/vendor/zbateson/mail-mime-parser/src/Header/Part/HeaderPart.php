<?php
/**
 * This file is part of the ZBateson\MailMimeParser project.
 *
 * @license http://opensource.org/licenses/bsd-license.php BSD
 */

namespace ZBateson\MailMimeParser\Header\Part;

use Psr\Log\LoggerInterface;
use Psr\Log\LogLevel;
use ZBateson\MailMimeParser\ErrorBag;
use ZBateson\MailMimeParser\Header\IHeaderPart;
use ZBateson\MbWrapper\MbWrapper;
use ZBateson\MbWrapper\UnsupportedCharsetException;

/**
 * Abstract base class representing a single part of a parsed header.
 *
 * @author Zaahid Bateson
 */
abstract class HeaderPart extends ErrorBag implements IHeaderPart
{
    /**
     * @var string the representative value of the part after any conversion or
     *      processing has been done on it (e.g. removing new lines, converting,
     *      whatever else).
     */
    protected string $value;

    /**
     * @var MbWrapper $charsetConverter the charset converter used for
     *      converting strings in HeaderPart::convertEncoding
     */
    protected MbWrapper $charsetConverter;

    /**
     * @var bool set to true to ignore spaces before this part
     */
    protected bool $canIgnoreSpacesBefore = false;

    /**
     * @var bool set to true to ignore spaces after this part
     */
    protected bool $canIgnoreSpacesAfter = false;

    /**
     * True if the part is a space token
     */
    protected bool $isSpace = false;

    public function __construct(LoggerInterface $logger, MbWrapper $charsetConverter, string $value)
    {
        parent::__construct($logger);
        $this->charsetConverter = $charsetConverter;
        $this->value = $value;
    }

    /**
     * Returns the part's representative value after any necessary processing
     * has been performed.  For the raw value, call getRawValue().
     */
    public function getValue() : string
    {
        return $this->value;
    }

    /**
     * Returns the value of the part (which is a string).
     *
     * @return string the value
     */
    public function __toString() : string
    {
        return $this->value;
    }

    /**
     * Ensures the encoding of the passed string is set to UTF-8.
     *
     * The method does nothing if the passed $from charset is UTF-8 already, or
     * if $force is set to false and mb_check_encoding for $str returns true
     * for 'UTF-8'.
     *
     * @return string utf-8 string
     */
    protected function convertEncoding(string $str, string $from = 'ISO-8859-1', bool $force = false) : string
    {
        if ($from !== 'UTF-8') {
            // mime header part decoding will force it.  This is necessary for
            // UTF-7 because mb_check_encoding will return true
            if ($force || !($this->charsetConverter->checkEncoding($str, 'UTF-8'))) {
                try {
                    return $this->charsetConverter->convert($str, $from, 'UTF-8');
                } catch (UnsupportedCharsetException $ce) {
                    $this->addError('Unable to convert charset', LogLevel::ERROR, $ce);
                    return $this->charsetConverter->convert($str, 'ISO-8859-1', 'UTF-8');
                }
            }
        }
        return $str;
    }

    public function getComments() : array
    {
        return [];
    }

    /**
     * Default implementation returns an empty array.
     */
    protected function getErrorBagChildren() : array
    {
        return [];
    }
}
