<?php
/**
 * This file is part of the ZBateson\MailMimeParser project.
 *
 * @license http://opensource.org/licenses/bsd-license.php BSD
 */

namespace ZBateson\MailMimeParser\Message\Factory;

use Psr\Log\LoggerInterface;
use ZBateson\MailMimeParser\Header\HeaderFactory;
use ZBateson\MailMimeParser\Message\PartHeaderContainer;

/**
 * Creates PartHeaderContainer instances.
 *
 * @author Zaahid Bateson
 */
class PartHeaderContainerFactory
{
    public function __construct(
        protected readonly LoggerInterface $logger,
        protected readonly HeaderFactory $headerFactory
    ) {
    }

    /**
     * Creates and returns a PartHeaderContainer.
     */
    public function newInstance(?PartHeaderContainer $from = null) : PartHeaderContainer
    {
        return new PartHeaderContainer($this->logger, $this->headerFactory, $from);
    }
}
