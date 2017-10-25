<?php

namespace BestIt\ContentfulBundle\Tests\Routing;

use BestIt\ContentfulBundle\Routing\ContentfulSlugMatcher;
use BestIt\ContentfulBundle\Service\Delivery\ClientDecorator;
use Contentful\Delivery\ContentType;
use Contentful\Delivery\Query;
use Contentful\Exception\NotFoundException;
use PHPUnit\Framework\TestCase;
use PHPUnit_Framework_MockObject_MockObject;
use Psr\Cache\CacheItemInterface;
use Psr\Cache\CacheItemPoolInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Routing\Matcher\RequestMatcherInterface;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;

/**
 * Checks the router for the contentful bundle.
 * @author blange <lange@bestit-online.de>
 * @package BestIt\ContentfulBundle\Tests\Routing
 */
class ContentfulSlugMatcherTest extends TestCase
{
    /**
     * The used client.
     * @var ClientDecorator|null|PHPUnit_Framework_MockObject_MockObject
     */
    private $client;

    /**
     * The tested class.
     * @var ContentfulSlugMatcher|null
     */
    private $fixture;

    /**
     * The used slug field.
     * @var string|null
     */
    private $slugField;

    /**
     * The cache
     *
     * @var CacheItemPoolInterface|null|PHPUnit_Framework_MockObject_MockObject
     */
    private $cache;

    /**
     * Sets up the test.
     * @return void
     */
    protected function setUp()
    {
        $this->fixture = new ContentfulSlugMatcher(
            $this->cache = $this->createMock(CacheItemPoolInterface::class),
            $this->client = $this->createMock(ClientDecorator::class),
            uniqid('', true),
            $this->slugField = uniqid('', true)
        );
    }

    /**
     * Checks if the getter and setter change the routable types.
     * @return void
     */
    public function testGetAndSetRoutableTypes()
    {
        static::assertSame([], $this->fixture->getRoutableTypes(), 'Wrong default return.');
        static::assertSame($this->fixture, $this->fixture->setRoutableTypes($types = [uniqid()]), 'Fluent broken.');
        static::assertSame($types, $this->fixture->getRoutableTypes(), 'Not persisted.');
    }

    /**
     * Checks if the client exception is skipped and the rest of the entries are registered normally.
     * @return void
     */
    public function testGetRouteCollectionSkipOnNotFoundException()
    {
        $this->fixture->setRoutableTypes([$type1 = uniqid(), $type2 = uniqid()]);

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
     * Checks the interfaces of the class.
     * @return void
     */
    public function testInterfaces()
    {
        static::assertInstanceOf(RequestMatcherInterface::class, $this->fixture);
        static::assertInstanceOf(UrlGeneratorInterface::class, $this->fixture);
    }
}
