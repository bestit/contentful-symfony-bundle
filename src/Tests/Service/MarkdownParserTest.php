<?php

namespace BestIt\ContentfulBundle\Tests\Service;

use BestIt\ContentfulBundle\Service\MarkdownParser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Tests the markdown parser.
 * @author lange <lange@bestit-online.de>
 * @category Tests
 * @package BestIt\ContentfulBundle
 * @subpackage Service
 * @version $id$
 */
class MarkdownParserTest extends WebTestCase
{
    /**
     * The tested class.
     * @var MarkdownParser
     */
    protected $fixture = null;

    /**
     * Sets up the test.
     * @return void
     */
    public function setUp()
    {
        $this->fixture = new MarkdownParser();
    }

    /**
     * Checks if the service is registered correctly.
     * @return void
     */
    public function testServiceDeclarationSuccess()
    {
        $this->assertInstanceOf(
            MarkdownParser::class,
            static::createClient()->getContainer()->get('best_it_contentful.markdown.parser'),
            'The service was not registered correctly.'
        );
    }

    /**
     * Checks if the html is parsed.
     * @return void
     */
    public function testToHTMLSimple()
    {
        $this->assertSame('<h1>Headline</h1>', $this->fixture->toHtml('#Headline'));
    }
}
