<?php
/**
 * This file is part of the ZBateson\MailMimeParser project.
 *
 * @license http://opensource.org/licenses/bsd-license.php BSD
 */

namespace ZBateson\MailMimeParser\Message\Factory;

use Psr\Log\LoggerInterface;
use ZBateson\MailMimeParser\Message\IMessagePart;
use ZBateson\MailMimeParser\Message\IMimePart;
use ZBateson\MailMimeParser\Stream\StreamFactory;

/**
 * Abstract factory for subclasses of IMessagePart.
 *
 * @author Zaahid Bateson
 */
abstract class IMessagePartFactory
{
    public function __construct(
        protected readonly LoggerInterface $logger,
        protected readonly StreamFactory $streamFactory,
        protected readonly PartStreamContainerFactory $partStreamContainerFactory,
        protected readonly string $defaultFallbackCharset = 'ISO-8859-1'
    ) {
    }

    /**
     * Constructs a new IMessagePart object and returns it
     */
    abstract public function newInstance(?IMimePart $parent = null) : IMessagePart;
}
