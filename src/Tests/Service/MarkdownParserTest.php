<?php

namespace BestIt\ContentfulBundle\Tests\Service;

use BestIt\ContentfulBundle\Service\MarkdownParser;
use PHPUnit\Framework\TestCase;

/**
 * Tests the markdown parser.
 * @author lange <lange@bestit-online.de>
 * @package BestIt\ContentfulBundle\Tests\Service
 */
class MarkdownParserTest extends TestCase
{
    /**
     * The tested class.
     * @var MarkdownParser|null
     */
    protected $fixture;

    /**
     * Sets up the test.
     * @return void
     */
    protected function setUp()
    {
        $this->fixture = new MarkdownParser();
    }

    /**
     * Checks if the html is parsed.
     * @return void
     */
    public function testToHTMLSimple()
    {
        static::assertSame('<h1>Headline</h1>', $this->fixture->toHtml('#Headline'));
    }
}
