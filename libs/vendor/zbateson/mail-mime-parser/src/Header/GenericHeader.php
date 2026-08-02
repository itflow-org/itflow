<?php
/**
 * This file is part of the ZBateson\MailMimeParser project.
 *
 * @license http://opensource.org/licenses/bsd-license.php BSD
 */

namespace ZBateson\MailMimeParser\Header;

use Psr\Log\LoggerInterface;
use ZBateson\MailMimeParser\Header\Consumer\GenericConsumerMimeLiteralPartService;

/**
 * Reads a generic header.
 *
 * Header's may contain mime-encoded parts, quoted parts, and comments.  The
 * string value is the combined value of all its parts.
 *
 * @author Zaahid Bateson
 */
class GenericHeader extends AbstractHeader
{
    public function __construct(
        string $name,
        string $value,
        ?LoggerInterface $logger = null,
        ?GenericConsumerMimeLiteralPartService $consumerService = null
    ) {
        parent::__construct(
            self::resolveService($logger, LoggerInterface::class),
            self::resolveService($consumerService, GenericConsumerMimeLiteralPartService::class),
            $name,
            $value
        );
    }

    public function getValue() : ?string
    {
        if (!empty($this->parts)) {
            return \implode('', \array_map(fn($p) => $p->getValue(), $this->parts));
        }
        return null;
    }
}
