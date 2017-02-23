<?php

namespace BestIt\ContentfulBundle\Tests\Twig;

use BestIt\ContentfulBundle\Service\Delivery\ClientDecorator;
use BestIt\ContentfulBundle\Twig\ContentfulExtension;
use Contentful\Delivery\Query;
use PHPUnit_Framework_MockObject_MockObject;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Twig_SimpleFunction;

/**
 * Tests the contentful extension.
 * @author lange <lange@bestit-online.de>
 * @category Tests
 * @package BestIt\ContentfulBundle
 * @subpackage Twig
 * @version $id$
 */
class ContentfulExtensionTest extends WebTestCase
{
    /**
     * The mocked client.
     * @var ClientDecorator|PHPUnit_Framework_MockObject_MockObject
     */
    private $mockedClient = null;

    /**
     * The tested class.
     * @var ContentfulExtension
     */
    private $fixture = null;

    /**
     * Get contentul getter
     *
     * @return array
     */
    public function getContentfulGetter()
    {
        return [
            ['foo', 'baz', 'bar'],
            [['foo' => 'bar'], 'baz', '', uniqid()],
        ];
    }

    /**
     * Sets up the test.
     * @return void
     */
    public function setUp()
    {
        $this->fixture = new ContentfulExtension(
            $this->mockedClient = static::createMock(ClientDecorator::class)
        );
    }

    /**
     * Checks if the function are returned correctly.
     * @return void
     */
    public function testGetFunctions()
    {
        $functions = $this->fixture->getFunctions();

        /** @var Twig_SimpleFunction $function */
        $this->assertInstanceOf(Twig_SimpleFunction::class, $function = $functions[0], 'Wrong function instance.');

        $this->assertSame(
            [$this->fixture, 'getContentfulContent'],
            $function->getCallable(),
            'The callback is not set correctly.'
        );

        $this->assertSame('get_contentful', $function->getName(), 'The name was not correct.');
    }

    /**
     * Checks the return of the contentful getter.
     * @dataProvider getContentfulGetter
     * @param string|array $where
     * @param string $return
     * @param string $attribute
     * @param string $contentType
     * @param int $limit
     * @param string $default
     * @todo Add more tests.
     */
    public function testGetContentfulContent(
        $where,
        $return,
        string $attribute,
        string $contentType = '',
        int $limit = 1,
        $default = ''
    ) {
        if (is_scalar($where)) {
            $this->mockedClient
                ->method('getEntry')
                ->with($where)
                ->willReturn($mockReturn = [$attribute => $return]);

            static::assertSame($return, $this->fixture->getContentfulContent(
                $where,
                $attribute,
                $contentType,
                $limit,
                $default
            ));
        } else {
            $this->mockedClient
                ->method('getEntries')
                ->with(
                    $this->callback(function (callable $callback) use ($contentType) {
                        /** @var Query $query */
                        $query = $callback(new Query());

                        static::assertSame($contentType, $query->getContentType());

                        return true;
                    })
                )
                ->willReturn($mockReturn = [$attribute => $return]);

            static::assertSame(
                [$attribute => $return],
                $this->fixture->getContentfulContent($where, $attribute, $contentType, $limit, $default)
            );
        }
    }
}
