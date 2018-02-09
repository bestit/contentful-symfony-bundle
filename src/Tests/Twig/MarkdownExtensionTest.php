<?php

namespace BestIt\ContentfulBundle\Tests\Twig;

use BestIt\ContentfulBundle\Service\MarkdownParser;
use BestIt\ContentfulBundle\Twig\MarkdownExtension;
use PHPUnit\Framework\TestCase;
use Twig_SimpleFilter;

/**
 * Tests the markdown extension.
 * @author lange <lange@bestit-online.de>
 * @package BestIt\ContentfulBundle\Tests\Twig
 */
class MarkdownExtensionTest extends TestCase
{
    /**
     * The tested class.
     * @var MarkdownExtension|null
     */
    protected $fixture;

    /**
     * Sets up the test.
     * @return void
     */
    protected function setUp()
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
        static::assertInstanceOf(Twig_SimpleFilter::class, $filter = $filters[0], 'Wrong filter instance.');

        static::assertSame(
            [$this->fixture, 'parseMarkdown'],
            $filter->getCallable(),
            'The callback is not set correctly.'
        );

        static::assertSame('parseMarkdown', $filter->getName(), 'The name was not correct.');
    }

    /**
     * Checks if the html is parsed correctly.
     * @return void
     */
    public function testParseMarkdownSimple()
    {
        static::assertSame('<h1>Headline</h1>', $this->fixture->parseMarkdown('#Headline'));
    }

    /**
     * Sometimes contentful sends empty fields as null, so we need a test for that
     */
    public function testParseMarkdownWithNull()
    {
        static::assertSame('', $this->fixture->parseMarkdown(null));
    }
}
