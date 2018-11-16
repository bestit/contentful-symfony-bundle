<?php

declare(strict_types=1);

namespace BestIt\ContentfulBundle\Tests\Routing;

use BestIt\ContentfulBundle\CacheTTLAwareTrait;
use BestIt\ContentfulBundle\CacheTagsGetterTrait;
use BestIt\ContentfulBundle\Delivery\ResponseParserInterface;
use BestIt\ContentfulBundle\Routing\ContentfulSlugMatcher;
use BestIt\ContentfulBundle\Tests\TestTraitsTrait;
use Contentful\Delivery\Client;
use Contentful\Delivery\ContentType;
use Contentful\Delivery\Query;
use Contentful\Exception\NotFoundException;
use Contentful\ResourceArray;
use PHPUnit\Framework\TestCase;
use PHPUnit_Framework_MockObject_MockObject;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Exception\ResourceNotFoundException;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Routing\Matcher\RequestMatcherInterface;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;
use function mt_rand;
use function uniqid;

/**
 * Checks the router for the contentful bundle.
 *
 * @author blange <lange@bestit-online.de>
 * @package BestIt\ContentfulBundle\Tests\Routing
 */
class ContentfulSlugMatcherTest extends TestCase
{
    use TestTraitsTrait;

    /**
     * @var Client|null|PHPUnit_Framework_MockObject_MockObject The used client.
     */
    private $client;

    /**
     * @var string The used controller field.
     */
    private $controllerField;

    /**
     * @var ContentfulSlugMatcher|null|PHPUnit_Framework_MockObject_MockObject The tested class.
     */
    protected $fixture;

    /**
     * @var int How many levels should be matched with the contentful request.
     */
    private $matchingLevel;

    /**
     * @var string|null The used slug field.
     */
    private $slugField;

    /**
     * @var ResponseParserInterface|PHPUnit_Framework_MockObject_MockObject $routeCollectionParser
     */
    private $routeCollectionParser;

    /**
     * Returns the names of the used traits.
     *
     * @return array
     */
    protected function getUsedTraitNames(): array
    {
        return [CacheTagsGetterTrait::class];
    }

    /**
     * Sets up the test.
     *
     * @return void
     */
    protected function setUp()
    {
        $this->fixture = new ContentfulSlugMatcher(
            $this->client = $this->createMock(Client::class),
            $this->controllerField = uniqid('', true),
            $this->slugField = uniqid('', true),
            $this->routeCollectionParser = $this->createMock(ResponseParserInterface::class),
            $this->matchingLevel = mt_rand(1, 10)
        );
    }

    /**
     * Checks if the client exception is skipped and the rest of the entries are registered normally.
     *
     * @return void
     */
    public function testGetRouteCollectionSkipOnNotFoundException()
    {
        $this->fixture
            ->setRoutableTypes([$type1 = uniqid(), $type2 = uniqid()]);

        $this->client
            ->expects(static::at(0))
            ->method('getEntries')
            ->with(static::callback(function (Query  $query) use ($type1) {
                static::assertSame($type1, $query->getQueryData()['content_type'], 'Wrong type 1.');
                return true;
            }))
            ->willReturn($entries = $this->createMock(ResourceArray::class));

        $this->routeCollectionParser
            ->expects(static::once())
            ->method('toArray')
            ->with($entries)
            ->willReturn([
                $entry = [
                    '_contentType' => $type = $this->createMock(ContentType::class),
                    '_id' => $id = uniqid(),
                    $this->slugField => $slug = uniqid()
                ]
            ]);

        $type->expects(static::once())->method('getId')->willReturn($typeId = uniqid());

        $this->client
            ->expects(static::at(1))
            ->method('getEntries')
            ->with(static::callback(function (Query $query) use ($type2) {
                static::assertSame($type2, $query->getQueryData()['content_type'], 'Wrong type 2.');
                return true;
            }))
            ->willThrowException($this->createMock(NotFoundException::class));

        $collection = $this->fixture->getRouteCollection();

        static::assertInstanceOf(RouteCollection::class, $collection, 'Wrong return.');
        static::assertCount(1, $collection, 'Wrong route count.');

        static::assertInstanceOf(
            Route::class,
            $route = $collection->get('contentful_' . $typeId . '_' . $id),
            'Wrong registered route.'
        );

        static::assertSame('/' . $slug, $route->getPath(), 'Wrong path');
    }

    /**
     * Checks the interfaces of the class.
     *
     * @return void
     */
    public function testInterfaces()
    {
        static::assertInstanceOf(RequestMatcherInterface::class, $this->fixture);
        static::assertInstanceOf(UrlGeneratorInterface::class, $this->fixture);
    }

    /**
     * Checks if a request is correctly matched.
     *
     * @return void
     */
    public function testMatchRequestSuccess()
    {
        $this->fixture = new ContentfulSlugMatcher(
            $this->client,
            $this->controllerField,
            $this->slugField,
            $this->createMock(ResponseParserInterface::class),
            $this->matchingLevel
        );

        $this->fixture
            ->setRoutableTypes(['unusedType', 'usedType']);

        $request = $this->createMock(Request::class);

        $request
            ->expects(static::once())
            ->method('getRequestUri')
            ->willReturn($slug = '/' . uniqid());

        $this->client
            ->expects(static::exactly(2))
            ->method('getEntries')
            ->withConsecutive(
                [
                    static::callback(function (Query $query) use ($slug) {
                        static::assertSame(
                            [
                                'limit' => 1,
                                'skip' => null,
                                'content_type' => 'unusedType',
                                'mimetype_group' => null,
                                'fields.' . $this->slugField => $slug,
                                'include' => $this->matchingLevel
                            ],
                            $query->getQueryData()
                        );

                        return true;
                    })
                ],
                [
                    static::callback(function (Query  $query) use ($slug) {
                        static::assertSame(
                            [
                                'limit' => 1,
                                'skip' => null,
                                'content_type' => 'usedType',
                                'mimetype_group' => null,
                                'fields.' . $this->slugField => $slug,
                                'include' => $this->matchingLevel
                            ],
                            $query->getQueryData()
                        );

                        return true;
                    })
                ]
            )
            ->will(static::onConsecutiveCalls(
                $emptyEntries = $this->createMock(ResourceArray::class),
                $entries = $this->createMock(ResourceArray::class)
            ));

        $emptyEntries
            ->expects(static::once())
            ->method('count')
            ->willReturn(0);
        $entries
            ->expects(static::once())
            ->method('count')
            ->willReturn(1);

        $entries
            ->expects(static::once())
            ->method('offsetGet')
            ->with(0)
            ->willReturn($entry = [
                '_contentType' => $contentType = $this->createMock(ContentType::class),
                '_id' => $id = uniqid(),
                $this->slugField => $slug,
                $this->controllerField => $controller = uniqid()
            ]);

        $contentType
            ->expects(static::once())
            ->method('getId')
            ->willReturn($contentTypeId = uniqid());

        static::assertSame(
            [
                '_controller' => $controller,
                '_route' => 'contentful_' . $contentTypeId . '_' . $id,
                'data' => $entry
            ],
            $this->fixture->matchRequest($request)
        );
    }

    /**
     * Checks if the correct exception is thrown if the request in not matched.
     *
     * @return void
     */
    public function testMatchRequestUnmatched()
    {
        $this->expectException(ResourceNotFoundException::class);

        $request = $this->createMock(Request::class);

        $request
            ->expects(static::once())
            ->method('getRequestUri')
            ->willReturn($slug = '/' . uniqid());

        $this->fixture->matchRequest($request);
    }
}
