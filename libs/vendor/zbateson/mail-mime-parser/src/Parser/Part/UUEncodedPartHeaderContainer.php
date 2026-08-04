<?php
/**
 * This file is part of the ZBateson\MailMimeParser project.
 *
 * @license http://opensource.org/licenses/bsd-license.php BSD
 */

namespace ZBateson\MailMimeParser\Parser\Part;

use ZBateson\MailMimeParser\Message\PartHeaderContainer;

/**
 * Header container representing the start line of a uu-encoded part.
 *
 * The line may contain a unix file mode and a filename.
 *
 * @author Zaahid Bateson
 */
class UUEncodedPartHeaderContainer extends PartHeaderContainer
{
    /**
     * @var ?int the unix file permission
     */
    protected ?int $mode = null;

    /**
     * @var ?string the name of the file in the uuencoding 'header'.
     */
    protected ?string $filename = null;

    /**
     * Returns the file mode included in the uuencoded 'begin' line for this
     * part.
     */
    public function getUnixFileMode() : ?int
    {
        return $this->mode;
    }

    /**
     * Sets the unix file mode for the uuencoded 'begin' line.
     */
    public function setUnixFileMode(int $mode) : static
    {
        $this->mode = $mode;
        return $this;
    }

    /**
     * Returns the filename included in the uuencoded 'begin' line for this
     * part.
     */
    public function getFilename() : ?string
    {
        return $this->filename;
    }

    /**
     * Sets the filename included in the uuencoded 'begin' line.
     */
    public function setFilename(string $filename) : static
    {
        $this->filename = $filename;
        return $this;
    }
}
