<?php
/**
 * This file is part of the ZBateson\MailMimeParser project.
 *
 * @license http://opensource.org/licenses/bsd-license.php BSD
 */

namespace ZBateson\MailMimeParser\Header;

use Stringable;
use ZBateson\MailMimeParser\IErrorBag;

/**
 * Represents a single parsed part of a header line's value.
 *
 * For header values with multiple parts, for instance a list of addresses, each
 * address would be parsed into a single part.
 *
 * @author Zaahid Bateson
 */
interface IHeaderPart extends IErrorBag, Stringable
{
    /**
     * Returns the part's value.
     *
     * @return string The value of the part
     */
    public function getValue() : ?string;

    /**
     * Returns any CommentParts under this part container.
     *
     * @return CommentPart[]
     */
    public function getComments() : array;
}
