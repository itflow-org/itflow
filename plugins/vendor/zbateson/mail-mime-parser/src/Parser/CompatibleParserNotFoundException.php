<?php
/**
 * This file is part of the ZBateson\MailMimeParser project.
 *
 * @license http://opensource.org/licenses/bsd-license.php BSD
 */

namespace ZBateson\MailMimeParser\Parser;

use RuntimeException;

/**
 * Exception thrown if the ParserManagerService doesn't contain a parser that
 * can handle a given type of part.  The default configuration of MailMimeParser
 * uses NonMimeParserService that is a 'catch-all', so this would indicate a
 * configuration error.
 *
 * @author Zaahid Bateson
 */
class CompatibleParserNotFoundException extends RuntimeException
{
}
