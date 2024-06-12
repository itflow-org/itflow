<?php
/*
* File: EmailAddressTest.php
* Category: -
* Author: M.Goldenbaum
* Created: 09.03.23 02:24
* Updated: -
*
* Description:
*  -
*/

namespace Tests\fixtures;

/**
 * Class EmailAddressTest
 *
 * @package Tests\fixtures
 */
class EmailAddressTest extends FixtureTestCase {

    /**
     * Test the fixture email_address.eml
     *
     * @return void
     */
    public function testFixture() : void {
        $message = $this->getFixture("email_address.eml");

        self::assertEquals("", $message->subject);
        self::assertEquals("123@example.com", $message->message_id);
        self::assertEquals("Hi\r\nHow are you?", $message->getTextBody());
        self::assertFalse($message->hasHTMLBody());
        self::assertFalse($message->date->first());
        self::assertEquals("no_host@UNKNOWN", (string)$message->from);
        self::assertEquals("", $message->to);
        self::assertEquals("This one: is \"right\" <ding@dong.com>, No-address@UNKNOWN", $message->cc);
    }
}