<?php
/**
 * This file is part of the ZBateson\MailMimeParser project.
 *
 * @license http://opensource.org/licenses/bsd-license.php BSD
 */

namespace ZBateson\MailMimeParser\Parser\Proxy;

use ZBateson\MailMimeParser\Parser\IParserService;
use ZBateson\MailMimeParser\Parser\PartBuilder;

/**
 * Base class for factories creating ParserPartProxy classes.
 *
 * @author Zaahid Bateson
 */
abstract class ParserPartProxyFactory
{
    /**
     * Constructs a new ParserPartProxy wrapping an IMessagePart object.
     *
     */
    abstract public function newInstance(PartBuilder $partBuilder, IParserService $parser) : ParserPartProxy;
}
