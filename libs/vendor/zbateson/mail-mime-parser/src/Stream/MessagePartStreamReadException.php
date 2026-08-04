<?php
/**
 * This file is part of the ZBateson\MailMimeParser project.
 *
 * @license http://opensource.org/licenses/bsd-license.php BSD
 */

namespace ZBateson\MailMimeParser\Stream;

use RuntimeException;
use ZBateson\MailMimeParser\Message\IMessagePart;

/**
 * Thrown for exceptions on MessagePartStream::read so a $part can be used to
 * determine where the exception occurred.
 *
 * @author Zaahid Bateson
 */
class MessagePartStreamReadException extends RuntimeException
{
    /**
     * @var IMessagePart the IMessagePart the error was caused on.
     */
    protected IMessagePart $part;

    public function __construct(IMessagePart $part, string $message = '', int $code = 0, ?\Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
        $this->part = $part;
    }

    public function getPart() : IMessagePart
    {
        return $this->part;
    }
}
