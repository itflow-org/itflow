<?php
/**
 * This file is part of the ZBateson\MailMimeParser project.
 *
 * @license http://opensource.org/licenses/bsd-license.php BSD
 */

namespace ZBateson\MailMimeParser\Parser;

use ZBateson\MailMimeParser\Parser\Proxy\ParserPartProxy;

/**
 * Manages a prioritized list of IParser objects for parsing messages and parts
 * and creating proxied parts.
 *
 * The default ParserManager sets up a MimeParser in priority 0, and a
 * NonMimeParser in priority 1.
 *
 * @author Zaahid Bateson
 */
class ParserManagerService
{
    /**
     * @var IParserService[] List of parsers in order of priority (0 is highest
     *      priority).
     */
    protected array $parsers = [];

    public function __construct(MimeParserService $mimeParser, NonMimeParserService $nonMimeParser)
    {
        $this->setParsers([$mimeParser, $nonMimeParser]);
    }

    /**
     * Overrides the internal prioritized list of parses with the passed list,
     * calling $parser->setParserManager($this) on each one.
     *
     * @param IParserService[] $parsers
     */
    public function setParsers(array $parsers) : static
    {
        foreach ($parsers as $parser) {
            $parser->setParserManager($this);
        }
        $this->parsers = $parsers;
        return $this;
    }

    /**
     * Adds an IParser at the highest priority (up front), calling
     * $parser->setParserManager($this) on it.
     *
     * @param IParserService $parser The parser to add.
     */
    public function prependParser(IParserService $parser) : static
    {
        $parser->setParserManager($this);
        \array_unshift($this->parsers, $parser);
        return $this;
    }

    /**
     * Creates a ParserPartProxy for the passed $partBuilder using a compatible
     * IParser.
     *
     * Loops through registered IParsers calling 'canParse()' on each with the
     * passed PartBuilder, then calling either 'getParserMessageProxyFactory()'
     * or 'getParserPartProxyFactory()' depending on if the PartBuilder has a
     * parent, and finally calling 'newInstance' on the returned
     * ParserPartProxyFactory passing it the IParser, and returning the new
     * ParserPartProxy instance that was created.
     *
     * @param PartBuilder $partBuilder The PartBuilder to wrap in a proxy with
     *        an IParser
     * @throws CompatibleParserNotFoundException if a compatible parser for the
     *         type is not configured.
     * @return ParserPartProxy The created ParserPartProxy tied to a new
     *         IMessagePart and associated IParser.
     */
    public function createParserProxyFor(PartBuilder $partBuilder) : ParserPartProxy
    {
        foreach ($this->parsers as $parser) {
            if ($parser->canParse($partBuilder)) {
                $factory = ($partBuilder->getParent() === null) ?
                    $parser->getParserMessageProxyFactory() :
                    $parser->getParserPartProxyFactory();
                return $factory->newInstance($partBuilder, $parser);
            }
        }
        throw new CompatibleParserNotFoundException('Compatible parser for a part cannot be found with content-type: ' . $partBuilder->getHeaderContainer()->get('Content-Type'));
    }
}
