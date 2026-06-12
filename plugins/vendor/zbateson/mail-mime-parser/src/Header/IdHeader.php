<?php
/**
 * This file is part of the ZBateson\MailMimeParser project.
 *
 * @license http://opensource.org/licenses/bsd-license.php BSD
 */

namespace ZBateson\MailMimeParser\Header;

use Psr\Log\LoggerInterface;
use ZBateson\MailMimeParser\Header\Consumer\IdBaseConsumerService;
use ZBateson\MailMimeParser\Header\Part\CommentPart;
use ZBateson\MailMimeParser\Header\Part\MimeTokenPartFactory;
use ZBateson\MailMimeParser\MailMimeParser;

/**
 * Represents a Content-ID, Message-ID, In-Reply-To or References header.
 *
 * For a multi-id header like In-Reply-To or References, all IDs can be
 * retrieved by calling {@see IdHeader::getIds()}.  Otherwise, to retrieve the
 * first (or only) ID call {@see IdHeader::getValue()}.
 *
 * @author Zaahid Bateson
 */
class IdHeader extends MimeEncodedHeader
{
    public function __construct(
        string $name,
        string $value,
        ?LoggerInterface $logger = null,
        ?MimeTokenPartFactory $mimeTokenPartFactory = null,
        ?IdBaseConsumerService $consumerService = null
    ) {
        $di = MailMimeParser::getGlobalContainer();
        parent::__construct(
            $logger ?? $di->get(LoggerInterface::class),
            $mimeTokenPartFactory ?? $di->get(MimeTokenPartFactory::class),
            $consumerService ?? $di->get(IdBaseConsumerService::class),
            $name,
            $value
        );
    }

    /**
     * Returns the ID. Synonymous to calling getValue().
     *
     * @return string|null The ID
     */
    public function getId() : ?string
    {
        return $this->getValue();
    }

    /**
     * Returns all IDs parsed for a multi-id header like References or
     * In-Reply-To.
     *
     * @return string[] An array of IDs
     */
    public function getIds() : array
    {
        return \array_values(\array_map(
            function($p) {
                return $p->getValue();
            },
            \array_filter($this->parts, function($p) {
                return !($p instanceof CommentPart);
            })
        ));
    }
}
