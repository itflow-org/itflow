<?php
/**
 * This file is part of the ZBateson\MailMimeParser project.
 *
 * @license http://opensource.org/licenses/bsd-license.php BSD
 */

namespace ZBateson\MailMimeParser\Header;

use Psr\Log\LoggerInterface;
use ZBateson\MailMimeParser\Header\Consumer\GenericConsumerMimeLiteralPartService;
use ZBateson\MailMimeParser\MailMimeParser;

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
        $di = MailMimeParser::getGlobalContainer();
        parent::__construct(
            $logger ?? $di->get(LoggerInterface::class),
            $consumerService ?? $di->get(DateConsumerService::class),
            $name,
            $value
        );
        parent::__construct($logger, $consumerService, $name, $value);
    }

    public function getValue() : ?string
    {
        if (!empty($this->parts)) {
            return \implode('', \array_map(function($p) { return $p->getValue(); }, $this->parts));
        }
        return null;
    }
}
