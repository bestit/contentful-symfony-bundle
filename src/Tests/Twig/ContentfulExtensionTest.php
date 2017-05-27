<?php

namespace BestIt\ContentfulBundle\Tests\Twig;

use BestIt\ContentfulBundle\Service\Delivery\ClientDecorator;
use BestIt\ContentfulBundle\Twig\ContentfulExtension;
use Contentful\Delivery\Query;
use PHPUnit\Framework\TestCase;
use PHPUnit_Framework_MockObject_MockObject;
use Twig_SimpleFunction;

/**
 * Tests the contentful extension.
 * @author lange <lange@bestit-online.de>
 * @package BestIt\ContentfulBundle\Tests\Twig
 */
class ContentfulExtensionTest extends TestCase
{
    /**
     * The mocked client.
     * @var ClientDecorator|PHPUnit_Framework_MockObject_MockObject|null
     */
    private $mockedClient;

    /**
     * The tested class.
     * @var ContentfulExtension|null
     */
    private $fixture;

    /**
     * Get contentful getter name with rules to assert.
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
    protected function setUp()
    {
        $this->fixture = new ContentfulExtension(
            $this->mockedClient = $this->createMock(ClientDecorator::class)
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
        static::assertInstanceOf(Twig_SimpleFunction::class, $function = $functions[0], 'Wrong function instance.');

        static::assertSame(
            [$this->fixture, 'getContentfulContent'],
            $function->getCallable(),
            'The callback is not set correctly.'
        );

        static::assertSame('get_contentful', $function->getName(), 'The name was not correct.');
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

                        static::assertSame($contentType, $query->getQueryData()['content_type']);

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
