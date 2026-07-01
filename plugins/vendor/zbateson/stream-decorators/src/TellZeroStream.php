<?php
/**
 * This file is part of the ZBateson\StreamDecorators project.
 *
 * @license http://opensource.org/licenses/bsd-license.php BSD
 */

namespace ZBateson\StreamDecorators;

use Psr\Http\Message\StreamInterface;
use GuzzleHttp\Psr7\StreamDecoratorTrait;

/**
 * Calling tell() always returns 0.  Used by DecoratedCachingStream so a
 * CachingStream can use a BufferedStream, because BufferedStream throws an
 * exception in tell().
 */
class TellZeroStream implements StreamInterface
{
    use StreamDecoratorTrait;

    /**
     * @var StreamInterface
     */
    private StreamInterface $stream;

    public function tell() : int
    {
        return 0;
    }
}