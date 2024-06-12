<?php
/*
* File: HeaderTest.php
* Category: -
* Author: M.Goldenbaum
* Created: 28.12.22 18:11
* Updated: -
*
* Description:
*  -
*/

namespace Tests;

use Carbon\Carbon;
use PHPUnit\Framework\TestCase;
use Webklex\PHPIMAP\Address;
use Webklex\PHPIMAP\Attribute;
use Webklex\PHPIMAP\Config;
use Webklex\PHPIMAP\Exceptions\InvalidMessageDateException;
use Webklex\PHPIMAP\Header;
use Webklex\PHPIMAP\IMAP;

class HeaderTest extends TestCase {

    /** @var Config $config */
    protected Config $config;

    /**
     * Setup the test environment.
     *
     * @return void
     */
    public function setUp(): void {
        $this->config = Config::make();
    }

    /**
     * Test parsing email headers
     *
     * @throws InvalidMessageDateException
     */
    public function testHeaderParsing(): void {
        $email = file_get_contents(implode(DIRECTORY_SEPARATOR, [__DIR__, "messages", "1366671050@github.com.eml"]));
        if (!str_contains($email, "\r\n")) {
            $email = str_replace("\n", "\r\n", $email);
        }

        $raw_header = substr($email, 0, strpos($email, "\r\n\r\n"));

        $header = new Header($raw_header, $this->config);
        $subject = $header->get("subject");
        $returnPath = $header->get("Return-Path");
        /** @var Carbon $date */
        $date = $header->get("date")->first();
        /** @var Address $from */
        $from = $header->get("from")->first();
        /** @var Address $to */
        $to = $header->get("to")->first();

        self::assertSame($raw_header, $header->raw);
        self::assertInstanceOf(Attribute::class, $subject);
        self::assertSame("Re: [Webklex/php-imap] Read all folders? (Issue #349)", $subject->toString());
        self::assertSame("Re: [Webklex/php-imap] Read all folders? (Issue #349)", (string)$header->subject);
        self::assertSame("<noreply@github.com>", $returnPath->toString());
        self::assertSame("return_path", $returnPath->getName());
        self::assertSame("-4.299", (string)$header->get("X-Spam-Score"));
        self::assertSame("Webklex/php-imap/issues/349/1365266070@github.com", (string)$header->get("Message-ID"));
        self::assertSame(6, $header->get("received")->count());
        self::assertSame(IMAP::MESSAGE_PRIORITY_UNKNOWN, (int)$header->get("priority")());

        self::assertSame("Username", $from->personal);
        self::assertSame("notifications", $from->mailbox);
        self::assertSame("github.com", $from->host);
        self::assertSame("notifications@github.com", $from->mail);
        self::assertSame("Username <notifications@github.com>", $from->full);

        self::assertSame("Webklex/php-imap", $to->personal);
        self::assertSame("php-imap", $to->mailbox);
        self::assertSame("noreply.github.com", $to->host);
        self::assertSame("php-imap@noreply.github.com", $to->mail);
        self::assertSame("Webklex/php-imap <php-imap@noreply.github.com>", $to->full);

        self::assertInstanceOf(Carbon::class, $date);
        self::assertSame("2022-12-26 08:07:14 GMT-0800", $date->format("Y-m-d H:i:s T"));

        self::assertSame(48, count($header->getAttributes()));
    }

    public function testRfc822ParseHeaders() {
        $mock = $this->getMockBuilder(Header::class)
            ->disableOriginalConstructor()
            ->onlyMethods([])
            ->getMock();

        $config = new \ReflectionProperty($mock, 'options');
        $config->setAccessible(true);
        $config->setValue($mock, $this->config->get("options"));

        $mockHeader = "Content-Type: text/csv; charset=WINDOWS-1252;  name*0=\"TH_Is_a_F ile name example 20221013.c\"; name*1=sv\r\nContent-Transfer-Encoding: quoted-printable\r\nContent-Disposition: attachment; filename*0=\"TH_Is_a_F ile name example 20221013.c\"; filename*1=\"sv\"\r\n";

        $expected = new \stdClass();
        $expected->content_type = 'text/csv; charset=WINDOWS-1252;  name*0="TH_Is_a_F ile name example 20221013.c"; name*1=sv';
        $expected->content_transfer_encoding = 'quoted-printable';
        $expected->content_disposition = 'attachment; filename*0="TH_Is_a_F ile name example 20221013.c"; filename*1="sv"';

        $this->assertEquals($expected, $mock->rfc822_parse_headers($mockHeader));
    }

    public function testExtractHeaderExtensions() {
        $mock = $this->getMockBuilder(Header::class)
            ->disableOriginalConstructor()
            ->onlyMethods([])
            ->getMock();

        $method = new \ReflectionMethod($mock, 'extractHeaderExtensions');
        $method->setAccessible(true);

        $mockAttributes = [
            'content_type'              => new Attribute('content_type', 'text/csv; charset=WINDOWS-1252;  name*0="TH_Is_a_F ile name example 20221013.c"; name*1=sv'),
            'content_transfer_encoding' => new Attribute('content_transfer_encoding', 'quoted-printable'),
            'content_disposition'       => new Attribute('content_disposition', 'attachment; filename*0="TH_Is_a_F ile name example 20221013.c"; filename*1="sv"; attribute_test=attribute_test_value'),
        ];

        $attributes = new \ReflectionProperty($mock, 'attributes');
        $attributes->setAccessible(true);
        $attributes->setValue($mock, $mockAttributes);

        $method->invoke($mock);

        $this->assertArrayHasKey('filename', $mock->getAttributes());
        $this->assertArrayNotHasKey('filename*0', $mock->getAttributes());
        $this->assertEquals('TH_Is_a_F ile name example 20221013.csv', $mock->get('filename'));

        $this->assertArrayHasKey('name', $mock->getAttributes());
        $this->assertArrayNotHasKey('name*0', $mock->getAttributes());
        $this->assertEquals('TH_Is_a_F ile name example 20221013.csv', $mock->get('name'));

        $this->assertArrayHasKey('content_type', $mock->getAttributes());
        $this->assertEquals('text/csv', $mock->get('content_type')->last());

        $this->assertArrayHasKey('charset', $mock->getAttributes());
        $this->assertEquals('WINDOWS-1252', $mock->get('charset')->last());

        $this->assertArrayHasKey('content_transfer_encoding', $mock->getAttributes());
        $this->assertEquals('quoted-printable', $mock->get('content_transfer_encoding'));

        $this->assertArrayHasKey('content_disposition', $mock->getAttributes());
        $this->assertEquals('attachment', $mock->get('content_disposition')->last());
        $this->assertEquals('quoted-printable', $mock->get('content_transfer_encoding'));

        $this->assertArrayHasKey('attribute_test', $mock->getAttributes());
        $this->assertEquals('attribute_test_value', $mock->get('attribute_test'));
    }
}