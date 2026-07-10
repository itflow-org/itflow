<?php
/**
 * This file is part of the ZBateson\MailMimeParser project.
 *
 * @license http://opensource.org/licenses/bsd-license.php BSD
 */

namespace ZBateson\MailMimeParser\Header;

use ZBateson\MailMimeParser\IErrorBag;

/**
 * A mime email header line consisting of a name and value.
 *
 * The header object provides methods to access the header's name, raw value,
 * and also its parsed value.  The parsed value will depend on the type of
 * header and in some cases may be broken up into other parts (for example email
 * addresses in an address header, or parameters in a parameter header).
 *
 * @author Zaahid Bateson
 */
interface IHeader extends IErrorBag
{
    /**
     * Returns an array of IHeaderPart objects the header's value has been
     * parsed into, excluding any
     * {@see \ZBateson\MailMimeParser\Header\Part\CommentPart}s.
     *
     * To retrieve all parts /including/ CommentParts, {@see getAllParts()}.
     *
     * @return IHeaderPart[] The array of parts.
     */
    public function getParts() : array;

    /**
     * Returns an array of all IHeaderPart objects the header's value has been
     * parsed into, including any CommentParts.
     *
     * @return IHeaderPart[] The array of parts.
     */
    public function getAllParts() : array;

    /**
     * Returns an array of comments parsed from the header.  If there are no
     * comments in the header, an empty array is returned.
     *
     * @return string[]
     */
    public function getComments() : array;

    /**
     * Returns the parsed 'value' of the header.
     *
     * For headers that contain multiple parts, like address headers (To, From)
     * or parameter headers (Content-Type), the 'value' is the value of the
     * first parsed part that isn't a comment.
     *
     * @return string The value
     */
    public function getValue() : ?string;

    /**
     * Returns the raw value of the header.
     *
     * @return string The raw value.
     */
    public function getRawValue() : string;

    /**
     * Returns the name of the header.
     *
     * @return string The name.
     */
    public function getName() : string;

    /**
     * Returns the string representation of the header.
     *
     * i.e.: '<HeaderName>: <RawValue>'
     *
     * @return string The string representation.
     */
    public function __toString() : string;
}
