<?php

namespace BestIt\ContentfulBundle\Tests\Twig;

use BestIt\ContentfulBundle\Service\MarkdownParser;
use BestIt\ContentfulBundle\Twig\MarkdownExtension;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Twig_SimpleFilter;

/**
 * Tests the markdown extension.
 * @author lange <lange@bestit-online.de>
 * @category Tests
 * @package BestIt\ContentfulBundle
 * @subpackage Twig
 * @version $id$
 */
class MarkdownExtensionTest extends WebTestCase
{
    /**
     * The tested class.
     * @var MarkdownExtension
     */
    protected $fixture = null;

    /**
     * Sets up the test.
     * @return void
     */
    public function setUp()
    {
        $this->fixture = new MarkdownExtension(new MarkdownParser());
    }

    /**
     * Checks if the filters are returned correctly.
     * @return void
     */
    public function testGetFiltersSuccess()
    {
        $filters = $this->fixture->getFilters();

        /** @var Twig_SimpleFilter $filter */
        $this->assertInstanceOf(Twig_SimpleFilter::class, $filter = $filters[0], 'Wrong filter instance.');

        $this->assertSame(
            [$this->fixture, 'parseMarkdown'],
            $filter->getCallable(),
            'The callback is not set correctly.'
        );

        $this->assertSame('parseMarkdown', $filter->getName(), 'The name was not correct.');
    }

    /**
     * Checks if the html is parsed correctly.
     * @return void
     */
    public function testParseMarkdownSimple()
    {
        $this->assertSame('<h1>Headline</h1>', $this->fixture->parseMarkdown('#Headline'));
    }
}
