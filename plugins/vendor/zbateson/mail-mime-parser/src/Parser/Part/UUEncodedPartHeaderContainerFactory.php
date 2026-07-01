<?php
/**
 * This file is part of the ZBateson\MailMimeParser project.
 *
 * @license http://opensource.org/licenses/bsd-license.php BSD
 */

namespace ZBateson\MailMimeParser\Parser\Part;

use Psr\Log\LoggerInterface;
use ZBateson\MailMimeParser\Header\HeaderFactory;

/**
 * Creates UUEncodedPartHeaderContainer instances.
 *
 * @author Zaahid Bateson
 */
class UUEncodedPartHeaderContainerFactory
{
    protected LoggerInterface $logger;

    /**
     * @var HeaderFactory the HeaderFactory passed to
     *      UUEncodedPartHeaderContainer instances.
     */
    protected HeaderFactory $headerFactory;

    /**
     * Constructor
     *
     */
    public function __construct(LoggerInterface $logger, HeaderFactory $headerFactory)
    {
        $this->logger = $logger;
        $this->headerFactory = $headerFactory;
    }

    /**
     * Creates and returns a UUEncodedPartHeaderContainer.
     */
    public function newInstance(int $mode, string $filename) : UUEncodedPartHeaderContainer
    {
        $container = new UUEncodedPartHeaderContainer($this->logger, $this->headerFactory);
        $container->setUnixFileMode($mode);
        $container->setFilename($filename);
        return $container;
    }
}
