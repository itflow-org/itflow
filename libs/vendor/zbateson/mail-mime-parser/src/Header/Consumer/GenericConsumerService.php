<?php
/**
 * This file is part of the ZBateson\MailMimeParser project.
 *
 * @license http://opensource.org/licenses/bsd-license.php BSD
 */

namespace ZBateson\MailMimeParser\Header\Consumer;

use Psr\Log\LoggerInterface;
use ZBateson\MailMimeParser\Header\Part\HeaderPartFactory;

/**
 * The base GenericConsumerService is a consumer with CommentConsumerService and
 * QuotedStringConsumerService as sub-consumers, and splitting tokens by
 * whitespace.
 *
 * @author Zaahid Bateson
 */
class GenericConsumerService extends AbstractGenericConsumerService
{
    public function __construct(
        LoggerInterface $logger,
        HeaderPartFactory $partFactory,
        CommentConsumerService $commentConsumerService,
        QuotedStringConsumerService $quotedStringConsumerService
    ) {
        parent::__construct(
            $logger,
            $partFactory,
            [$commentConsumerService, $quotedStringConsumerService]
        );
    }
}
