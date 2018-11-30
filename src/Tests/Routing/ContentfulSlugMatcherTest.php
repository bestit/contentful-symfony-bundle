<?php

declare(strict_types=1);

namespace BestIt\ContentfulBundle\Tests\Routing;

use BestIt\ContentfulBundle\CacheTTLAwareTrait;
use BestIt\ContentfulBundle\CacheTagsGetterTrait;
use BestIt\ContentfulBundle\Delivery\ResponseParserInterface;
use BestIt\ContentfulBundle\Routing\ContentfulSlugMatcher;
use BestIt\ContentfulBundle\Service\Delivery\ClientDecorator;
use BestIt\ContentfulBundle\Tests\TestTraitsTrait;
use Contentful\Delivery\ContentType;
use Contentful\Delivery\Query;
use Contentful\Exception\NotFoundException;
use PHPUnit\Framework\TestCase;
use PHPUnit_Framework_MockObject_MockObject;
use Psr\Cache\CacheItemInterface;
use Psr\Cache\CacheItemPoolInterface;
use Symfony\Component\Cache\CacheItem;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Exception\ResourceNotFoundException;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Routing\Matcher\RequestMatcherInterface;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;
use function md5;
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
     * @var CacheItemPoolInterface|null|PHPUnit_Framework_MockObject_MockObject The used cache.
     */
    private $cache;

    /**
     * @var ClientDecorator|null|PHPUnit_Framework_MockObject_MockObject The used client.
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
     * @var string Query parameter name to ignore caches.
     */
    private $ignoreCacheParameter;

    /**
     * @var int How many levels should be matched with the contentful request.
     */
    private $matchingLevel;

    /**
     * @var string|null The used slug field.
     */
    private $slugField;

    /**
     * Returns the names of the used traits.
     *
     * @return array
     */
    protected function getUsedTraitNames(): array
    {
        return [CacheTagsGetterTrait::class, CacheTTLAwareTrait::class];
    }

    /**
     * Sets up the test.
     *
     * @return void
     */
    protected function setUp()
    {
        $this->fixture = new ContentfulSlugMatcher(
            $this->cache = $this->createMock(CacheItemPoolInterface::class),
            $this->client = $this->createMock(ClientDecorator::class),
            $this->controllerField = uniqid('', true),
            $this->slugField = uniqid('', true),
            $this->createMock(ResponseParserInterface::class),
            $this->ignoreCacheParameter = uniqid(),
            $this->matchingLevel = mt_rand(1, 10)
        );
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
            ->willReturn($cacheItem = $this->createMock(CacheItemInterface::class));

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
            ->setCacheTTL($ttl = mt_rand(1, 10000))
            ->setRoutableTypes([$type1 = uniqid(), $type2 = uniqid()]);

        $this->client
            ->expects(static::at(0))
            ->method('getEntries')
            ->with(static::callback(function (callable $queryChanger) use ($type1) {
                $queryChanger($query = new Query());

                static::assertSame($type1, $query->getQueryData()['content_type'], 'Wrong type 1.');

                return true;
            }))
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
            ->with(static::callback(function (callable $queryChanger) use ($type2) {
                $queryChanger($query = new Query());

                static::assertSame($type2, $query->getQueryData()['content_type'], 'Wrong type 2.');

                return true;
            }))
            ->willThrowException($this->createMock(NotFoundException::class));

        $this->cache
            ->expects(static::once())
            ->method('getItem')
            ->with('route_collection')
            ->willReturn($cacheItem = $this->createMock(CacheItemInterface::class));

        $cacheItem->method('expiresAfter')->with($ttl);
        $cacheItem->method('isHit')->willReturn(false);
        $cacheItem->method('set')->with(static::isInstanceOf(RouteCollection::class))->willReturnSelf();
        $this->cache->method('save')->with($cacheItem);

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
     * @param bool $withCacheUsage
     *
     * @return void
     */
    public function testMatchRequestSuccess(bool $withCacheUsage = true, Request $request = null)
    {
        $this->fixture = $this->getMockBuilder(ContentfulSlugMatcher::class)
            ->setConstructorArgs([
                $this->cache,
                $this->client,
                $this->controllerField,
                $this->slugField,
                $this->createMock(ResponseParserInterface::class),
                $this->ignoreCacheParameter,
                $this->matchingLevel
            ])
            ->setMethods(['getCacheTags'])
            ->getMock();

        $this->fixture
            ->setRoutableTypes(['unusedType', 'usedType']);

        if (!$request) {
            $request = $this->createMock(Request::class);
        }

        $request
            ->expects(static::once())
            ->method('getRequestUri')
            ->willReturn($slug = '/' . uniqid());

        $this->cache
            ->expects(static::once())
            ->method('getItem')
            ->with(md5($slug) . '-contentful-routing')
            ->willReturn($cacheItem = new CacheItem());

        $this->client
            ->expects(static::exactly(2))
            ->method('getEntries')
            ->withConsecutive(
                [
                    static::callback(function (callable $callback) use ($slug) {
                        $callback($query = new Query());

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
                    static::callback(function (callable $callback) use ($slug) {
                        $callback($query = new Query());

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
                [],
                [
                    $entry = [
                        '_contentType' => $contentType = $this->createMock(ContentType::class),
                        '_id' => $id = uniqid(),
                        $this->controllerField => $controller = uniqid()
                    ]
                ]
            ));

        $contentType
            ->expects(static::once())
            ->method('getId')
            ->willReturn($contentTypeId = uniqid());

        $this->cache
            ->expects($withCacheUsage ? static::once() : static::never())
            ->method('save')
            ->with($cacheItem);

        $this->fixture
            ->expects($withCacheUsage ? static::once() : static::never())
            ->method('getCacheTags')
            ->with($entry)
            ->willReturn([uniqid()]);

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
     * Checks if the request is matched but the cache ignored.
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

        $this->cache
            ->expects(static::once())
            ->method('getItem')
            ->with(md5($slug) . '-contentful-routing')
            ->willReturn(new CacheItem());

        $this->fixture->matchRequest($request);
    }
}
