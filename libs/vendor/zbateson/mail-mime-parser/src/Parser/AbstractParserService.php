<?php
/**
 * This file is part of the ZBateson\MailMimeParser project.
 *
 * @license http://opensource.org/licenses/bsd-license.php BSD
 */

namespace ZBateson\MailMimeParser\Parser;

use ZBateson\MailMimeParser\Parser\Proxy\ParserPartProxyFactory;

/**
 * Provides basic implementations for:
 * - IParser::setParserManager
 * - IParser::getParserMessageProxyFactory (returns $this->parserMessageProxyFactory
 *   which can be set via the default constructor)
 * - IParser::getParserPartProxyFactory (returns $this->parserPartProxyFactory
 *   which can be set via the default constructor)
 *
 * @author Zaahid Bateson
 */
abstract class AbstractParserService implements IParserService
{
    /**
     * @var ParserPartProxyFactory the parser's message proxy factory service
     *      responsible for creating an IMessage part wrapped in a
     *      ParserPartProxy.
     */
    protected ParserPartProxyFactory $parserMessageProxyFactory;

    /**
     * @var ParserPartProxyFactory the parser's part proxy factory service
     *      responsible for creating IMessagePart parts wrapped in a
     *      ParserPartProxy.
     */
    protected ParserPartProxyFactory $parserPartProxyFactory;

    /**
     * @var PartBuilderFactory Service for creating PartBuilder objects for new
     *      children.
     */
    protected PartBuilderFactory $partBuilderFactory;

    /**
     * @var ParserManagerService the ParserManager, which should call setParserManager
     *      when the parser is added.
     */
    protected ParserManagerService $parserManager;

    public function __construct(
        ParserPartProxyFactory $parserMessageProxyFactory,
        ParserPartProxyFactory $parserPartProxyFactory,
        PartBuilderFactory $partBuilderFactory
    ) {
        $this->parserMessageProxyFactory = $parserMessageProxyFactory;
        $this->parserPartProxyFactory = $parserPartProxyFactory;
        $this->partBuilderFactory = $partBuilderFactory;
    }

    public function setParserManager(ParserManagerService $pm) : static
    {
        $this->parserManager = $pm;
        return $this;
    }

    public function getParserMessageProxyFactory() : ParserPartProxyFactory
    {
        return $this->parserMessageProxyFactory;
    }

    public function getParserPartProxyFactory() : ParserPartProxyFactory
    {
        return $this->parserPartProxyFactory;
    }
}
