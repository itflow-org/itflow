<?php
/**
 * This file is part of the ZBateson\MailMimeParser project.
 *
 * @license http://opensource.org/licenses/bsd-license.php BSD
 */

namespace ZBateson\MailMimeParser\Stream;

use GuzzleHttp\Psr7\StreamDecoratorTrait;
use Psr\Http\Message\StreamInterface;
use RuntimeException;
use ZBateson\MailMimeParser\Message\IMessagePart;

/**
 * Provides a readable stream for a MessagePart.
 *
 * @author Zaahid Bateson
 */
class MessagePartStreamDecorator implements StreamInterface
{
    use StreamDecoratorTrait {
        read as private decoratorRead;
    }

    /**
     * @var IMessagePart The part to read from.
     */
    protected IMessagePart $part;

    protected ?StreamInterface $stream;

    public function __construct(IMessagePart $part, ?StreamInterface $stream = null)
    {
        $this->part = $part;
        $this->stream = $stream;
    }

    /**
     * Overridden to wrap exceptions in MessagePartReadException which provides
     * 'getPart' to inspect the part the error occurs on.
     *
     * @throws MessagePartStreamReadException
     */
    public function read(int $length) : string
    {
        try {
            return $this->decoratorRead($length);
        } catch (MessagePartStreamReadException $me) {
            throw $me;
        } catch (RuntimeException $e) {
            throw new MessagePartStreamReadException(
                $this->part,
                'Exception occurred reading a part stream: cid=' . $this->part->getContentId()
                . ' type=' . $this->part->getContentType() . ', message: ' . $e->getMessage(),
                $e->getCode(),
                $e
            );
        }
    }
}
