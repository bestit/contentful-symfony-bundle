<?php

declare(strict_types=1);

namespace BestIt\ContentfulBundle\Tests\Routing;

use BestIt\ContentfulBundle\CacheTTLAwareTrait;
use BestIt\ContentfulBundle\Delivery\ResponseParserInterface;
use BestIt\ContentfulBundle\Routing\CachingContentfulSlugMatcher;
use BestIt\ContentfulBundle\Tests\TestTraitsTrait;
use Contentful\Delivery\Client;
use Contentful\Delivery\ContentType;
use Contentful\Delivery\DynamicEntry;
use Contentful\Delivery\Query;
use Contentful\Exception\NotFoundException;
use Contentful\ResourceArray;
use PHPUnit\Framework\TestCase;
use PHPUnit_Framework_MockObject_MockObject;
use Psr\Cache\CacheItemPoolInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Exception\ResourceNotFoundException;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;
use Symfony\Contracts\Cache\ItemInterface;

/**
 * Class CachingContentfulSlugMatcherTest
 *
 * @author André Varelmann <andre.varelmann@bestit-online.de>
 * @package BestIt\ContentfulBundle\Tests\Routing
 */
class CachingContentfulSlugMatcherTest extends TestCase
{
    use TestTraitsTrait;

    /**
     * @var CachingContentfulSlugMatcher|null|PHPUnit_Framework_MockObject_MockObject The tested class.
     */
    protected $fixture;

    /**
     * @var Client|null|PHPUnit_Framework_MockObject_MockObject The used client.
     */
    private $client;

    /**
     * @var string The used controller field.
     */
    private $controllerField;

    /**
     * @var CacheItemPoolInterface|null|PHPUnit_Framework_MockObject_MockObject $cache
     */
    private $cache;

    /**
     * @var int How many levels should be matched with the contentful request.
     */
    private $matchingLevel;

    /**
     * @var string|null The used slug field.
     */
    private $slugField;

    /**
     * @var ResponseParserInterface|null|PHPUnit_Framework_MockObject_MockObject $routeCollectionParser
     */
    private $routeCollectionParser;

    /**
     * @var ResponseParserInterface|null|PHPUnit_Framework_MockObject_MockObject $simpleResponseParser
     */
    private $simpleResponseParser;

    /**
     * @var string $ignoreCacheKey
     */
    private $ignoreCacheParameter;

    /**
     * Returns the names of the used traits.
     *
     * @return array
     */
    protected function getUsedTraitNames(): array
    {
        return [CacheTTLAwareTrait::class];
    }

    /**
     * Sets up the test.
     *
     * @return void
     */
    protected function setUp()
    {
        $this->fixture = new CachingContentfulSlugMatcher(
            $this->cache = $this->createMock(CacheItemPoolInterface::class),
            $this->client = $this->createMock(Client::class),
            $this->controllerField = uniqid('', true),
            $this->slugField = uniqid('', true),
            $this->routeCollectionParser = $this->createMock(ResponseParserInterface::class),
            $this->simpleResponseParser = $this->createMock(ResponseParserInterface::class),
            $this->ignoreCacheParameter = uniqid(),
            $this->matchingLevel = mt_rand(1, 10)
        );
    }

    /**
     * Test that we get the matching entry from cache
     *
     * @param bool $withCacheUsage
     * @param Request|null $request
     *
     * @return void
     */
    public function testMatchRequestSuccess(bool $withCacheUsage = true, Request $request = null)
    {
        if (!$request) {
            $request = $this->createMock(Request::class);
        }

        $this->fixture
            ->setCacheTTL($ttl = mt_rand(1, 10000))
            ->setRoutableTypes(['unusedType', 'usedType']);

        $request
            ->expects(static::once())
            ->method('getRequestUri')
            ->willReturn($slug = '/' . uniqid());

        $this->cache
            ->expects(static::once())
            ->method('getItem')
            ->with($cacheTag = md5($slug) . '-contentful-routing')
            ->willReturn($cacheItem = $this->createMock(ItemInterface::class));

        $cacheItem
            ->expects($withCacheUsage ? static::once() : static::never())
            ->method('isHit')
            ->willReturn(false);

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
            ->willReturn($dynamicEntry = $this->createMock(DynamicEntry::class));

        $this->simpleResponseParser
            ->expects(static::once())
            ->method('toArray')
            ->with($dynamicEntry)
            ->willReturn($entry = [
                '_contentType' => $contentType = $this->createMock(ContentType::class),
                '_id' => $id = uniqid(),
                $this->slugField => $slug,
                $this->controllerField => $controller = uniqid()
            ]);

        $tags = [];

        $dynamicEntry
            ->expects($withCacheUsage ? static::once() : static::never())
            ->method('getId')
            ->willReturn($tags[] = $id);

        $dynamicEntry
            ->expects($withCacheUsage ? static::once() : static::never())
            ->method('getContentType')
            ->willReturn($contentType);

        $contentTypeId = uniqid();

        $contentType
            ->expects($withCacheUsage ? static::exactly(2) : static::once())
            ->method('getId')
            ->willReturn($contentTypeId);

        $contentType
            ->expects($withCacheUsage ? static::once() : static::never())
            ->method('getFields')
            ->willReturn(null);

        $cacheItem
            ->expects(static::once())
            ->method('set')
            ->with($entry);

        $cacheItem
            ->expects(static::once())
            ->method('expiresAfter')
            ->with($ttl);

        $cacheItem
            ->expects($withCacheUsage ? static::once() : static::never())
            ->method('tag')
            ->with($tags);

        $cacheItem
            ->expects(static::once())
            ->method('get')
            ->willReturn($entry);

        $this->cache
            ->expects($withCacheUsage ? static::once() : static::never())
            ->method('save')
            ->with($cacheItem);

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
     * Test match request but ignore cache
     *
     * @return void
     */
    public function testMatchRequestSuccessButIgnoreCache()
    {
        $request = $this->createMock(Request::class);

        $request
            ->expects(static::once())
            ->method('get')
            ->with($this->ignoreCacheParameter, false)
            ->willReturn('false');

        $this->testMatchRequestSuccess(false, $request);
    }

    /**
     * Test match request with cache hit
     *
     * @return void
     */
    public function testMatchRequestWithCacheHit()
    {
        $request = $this->createMock(Request::class);

        $this->fixture
            ->setCacheTTL($ttl = mt_rand(1, 10000))
            ->setRoutableTypes(['unusedType', 'usedType']);

        $request
            ->expects(static::once())
            ->method('getRequestUri')
            ->willReturn($slug = '/' . uniqid());

        $this->cache
            ->expects(static::once())
            ->method('getItem')
            ->with($cacheTag = md5($slug) . '-contentful-routing')
            ->willReturn($cacheItem = $this->createMock(ItemInterface::class));

        $cacheItem
            ->expects(static::once())
            ->method('isHit')
            ->willReturn(true);

        $cacheItem
            ->method('get')
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

        $result = [
            '_controller' => $controller,
            '_route' => 'contentful_' . $contentTypeId . '_' . $id,
            'data' => $entry
        ];

        static::assertEquals($result, $this->fixture->matchRequest($request));
    }

    /**
     * Test match request with empty entry result
     *
     * @return void
     */
    public function testMatchRequestWithEmptyEntryResult()
    {
        $request = $this->createMock(Request::class);

        $this->fixture
            ->setCacheTTL($ttl = mt_rand(1, 10000))
            ->setRoutableTypes(['unusedType']);

        $request
            ->expects(static::once())
            ->method('getRequestUri')
            ->willReturn($slug = '/' . uniqid());

        $this->cache
            ->expects(static::once())
            ->method('getItem')
            ->with($cacheTag = md5($slug) . '-contentful-routing')
            ->willReturn($cacheItem = $this->createMock(ItemInterface::class));

        $cacheItem
            ->expects(static::once())
            ->method('isHit')
            ->willReturn(false);

        $this->client
            ->expects(static::once())
            ->method('getEntries')
            ->with(
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
            )
            ->willReturn($emptyEntries = $this->createMock(ResourceArray::class));

        $emptyEntries
            ->expects(static::once())
            ->method('count')
            ->willReturn(0);


        static::expectException(ResourceNotFoundException::class);

        $this->fixture->matchRequest($request);
    }

    /**
     * Checks if the route collection use cache
     *
     * @return void
     */
    public function testGetRouteCollectionCache()
    {
        $this->fixture->setRoutableTypes([$type1 = uniqid(), $type2 = uniqid()]);

        $this->client
            ->expects(static::never())
            ->method('getEntries');

        $this->cache
            ->expects(static::once())
            ->method('getItem')
            ->with('route_collection')
            ->willReturn($cacheItem = $this->createMock(ItemInterface::class));

        $cacheItem->method('isHit')->willReturn(true);
        $cacheItem->method('get')->willReturn($collection = new RouteCollection());

        static::assertSame($collection, $this->fixture->getRouteCollection());
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

        $this->cache
            ->expects(static::once())
            ->method('getItem')
            ->with('route_collection')
            ->willReturn($cacheItem = $this->createMock(ItemInterface::class));

        $cacheItem->expects(static::once())->method('isHit')->willReturn(false);
        $cacheItem->expects(static::once())->method('tag')->with(CachingContentfulSlugMatcher::COLLECTION_CACHE_KEY);
        $cacheItem->expects(static::once())->method('set')->willReturn($cacheItem);

        $this->cache
            ->method('save')
            ->with($cacheItem);

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
}
