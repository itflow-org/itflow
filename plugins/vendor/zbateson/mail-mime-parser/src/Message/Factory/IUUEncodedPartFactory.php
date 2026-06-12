<?php
/**
 * This file is part of the ZBateson\MailMimeParser project.
 *
 * @license http://opensource.org/licenses/bsd-license.php BSD
 */

namespace ZBateson\MailMimeParser\Message\Factory;

use ZBateson\MailMimeParser\Message\IMimePart;
use ZBateson\MailMimeParser\Message\IUUEncodedPart;
use ZBateson\MailMimeParser\Message\UUEncodedPart;

/**
 * Responsible for creating UUEncodedPart instances.
 *
 * @author Zaahid Bateson
 */
class IUUEncodedPartFactory extends IMessagePartFactory
{
    /**
     * Constructs a new UUEncodedPart object and returns it
     */
    public function newInstance(?IMimePart $parent = null) : IUUEncodedPart
    {
        $streamContainer = $this->partStreamContainerFactory->newInstance();
        $part = new UUEncodedPart(
            null,
            null,
            $parent,
            $this->logger,
            $streamContainer
        );
        $streamContainer->setStream($this->streamFactory->newMessagePartStream($part));
        return $part;
    }
}
