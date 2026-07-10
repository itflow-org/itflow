<?php
/**
 * This file is part of the ZBateson\MailMimeParser project.
 *
 * @license http://opensource.org/licenses/bsd-license.php BSD
 */

namespace ZBateson\MailMimeParser\Header;

use Psr\Log\LoggerInterface;
use ZBateson\MailMimeParser\Header\Consumer\SubjectConsumerService;
use ZBateson\MailMimeParser\MailMimeParser;

/**
 * Reads a subject header.
 *
 * The subject header is unique in that it doesn't include comments or quoted
 * parts.
 *
 * @author Zaahid Bateson
 */
class SubjectHeader extends AbstractHeader
{
    public function __construct(
        string $name,
        string $value,
        ?LoggerInterface $logger = null,
        ?SubjectConsumerService $consumerService = null
    ) {
        $di = MailMimeParser::getGlobalContainer();
        parent::__construct(
            $logger ?? $di->get(LoggerInterface::class),
            $consumerService ?? $di->get(SubjectConsumerService::class),
            $name,
            $value
        );
    }
}
