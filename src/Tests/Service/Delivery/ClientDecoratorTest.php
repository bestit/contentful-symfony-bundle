<?php

declare(strict_types=1);

namespace BestIt\ContentfulBundle\Tests\Service\Delivery;

use BestIt\ContentfulBundle\CacheTTLAwareTrait;
use BestIt\ContentfulBundle\CacheTagsGetterTrait;
use BestIt\ContentfulBundle\Delivery\ResponseParserInterface;
use BestIt\ContentfulBundle\Service\Cache\CacheEntryManager;
use BestIt\ContentfulBundle\Service\Cache\QueryStorageInterface;
use BestIt\ContentfulBundle\Service\Delivery\ClientDecorator;
use Closure;
use Contentful\Delivery\Client;
use Contentful\Delivery\DynamicEntry;
use Contentful\Delivery\Query;
use Contentful\ResourceArray;
use Doctrine\Common\Cache\ArrayCache;
use PHPUnit\Framework\TestCase;
use PHPUnit_Framework_MockObject_MockObject;
use Psr\Log\LoggerAwareTrait;
use Psr\Log\LoggerInterface;
use Symfony\Component\Cache\Adapter\DoctrineAdapter;
use Symfony\Component\Cache\CacheItem;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

/**
 * Tests the service for cache resetting.
 *
 * @author lange <lange@bestit-online.de>
 * @package BestIt\ContentfulBundle\Tests\Service\Delivery
 */
class ClientDecoratorTest extends TestCase
{
    /**
     * @var Client|PHPUnit_Framework_MockObject_MockObject|null The mocked client.
     */
    private $client;

    /**
     * @var EventDispatcherInterface|PHPUnit_Framework_MockObject_MockObject|null The mocked event dispatcher.
     */
    private $eventDispatcher;

    /**
     * @var ClientDecorator|null The tested class.
     */
    protected $fixture;

    /**
     * @var ResponseParserInterface|PHPUnit_Framework_MockObject_MockObject|null The used parser.
     */
    private $parser;

    /**
     * @var CacheEntryManager|PHPUnit_Framework_MockObject_MockObject The used cache managerr
     */
    private $cacheEntryManager;

    /**
     * @var QueryStorageInterface|PHPUnit_Framework_MockObject_MockObject The used query storage
     */
    private $queryStorage;

    /**
     * Returns an injection parser to test and its response.
     *
     * @return array
     */
    public function getParserToFetch()
    {
        $mockParser = $this->createMock(ResponseParserInterface::class);

        $mockParser
            ->expects($this->once())
            ->method('toArray')
            ->with($mockEntry = $this->createMock(DynamicEntry::class))
            ->willReturn($result = [uniqid()]);

        return [
            [null],
            [$mockParser, $result]
        ];
    }

    /**
     * Returns the names of the used traits.
     *
     * @return array
     */
    protected function getUsedTraitNames(): array
    {
        return [CacheTagsGetterTrait::class, CacheTTLAwareTrait::class, LoggerAwareTrait::class];
    }

    /**
     * Sets up the test.
     *
     * @return void
     */
    protected function setUp()
    {
        $this->fixture = new ClientDecorator(
            $this->client = $this->createMock(Client::class),
            new DoctrineAdapter(new ArrayCache()),
            $this->eventDispatcher = $this->createMock(EventDispatcherInterface::class),
            $this->createMock(LoggerInterface::class),
            $this->parser = $this->createMock(ResponseParserInterface::class),
            $this->cacheEntryManager = $this->createMock(CacheEntryManager::class),
            $this->queryStorage = $this->createMock(QueryStorageInterface::class)
        );
    }

    /**
     * Checks if the magical getter calls the base client.
     *
     * @return void
     */
    public function testCallSuccess()
    {
        $this->client
            ->expects($this->once())
            ->method('getAsset')
            ->with($id = uniqid())
            ->willReturn($return = uniqid());

        static::assertSame($return, $this->fixture->getAsset($id));
    }

    /**
     * Test that that entry will be returned
     *
     * @return void
     */
    public function testGetEntryForUncachedEntries()
    {
        $responseParser = $this->getCustomResponseParser();

        $this->cacheEntryManager
            ->expects(self::once())
            ->method('getEntriesFromCache')
            ->with(['entryId'], $responseParser)
            ->willReturn([]);

        $this->client
            ->expects(self::once())
            ->method('getEntries')
            ->with(self::callback(function (Query $query) {
                static::assertSame('sys.id%5Bin%5D=entryId', $query->getQueryString());

                return true;
            }))
            ->willReturn(new ResourceArray([$entry = $this->createMock(DynamicEntry::class)], 1, 0, 0));

        $cacheItem = new CacheItem();
        $cacheItem->set($result = ['foobar']);

        $this->cacheEntryManager
            ->expects(self::once())
            ->method('saveEntryInCache')
            ->with($entry, $responseParser)
            ->willReturn($cacheItem);

        static::assertSame($result, $this->fixture->getEntry('entryId', $responseParser));
    }

    /**
     * Test that that cached entry will be returned
     *
     * @return void
     */
    public function testGetEntryForCachedEntries()
    {
        $responseParser = $this->getCustomResponseParser();

        $this->cacheEntryManager
            ->expects(self::once())
            ->method('getEntriesFromCache')
            ->with(['entryId'], $responseParser)
            ->willReturn(['entryId' => $result = ['foobar']]);

        $this->client
            ->expects(self::never())
            ->method('getEntries');

        static::assertSame($result, $this->fixture->getEntry('entryId', $responseParser));
    }

    /**
     * Test that that cached entries will be returned
     *
     * @return void
     */
    public function testGetEntryForCachedIdListAndCachedEntries()
    {
        $cacheId = 'foobar';
        $responseParser = $this->getCustomResponseParser();
        $callable = $this->getQueryCallMethod($query = new Query());

        $this->cacheEntryManager
            ->method('getQueryIdListFromCache')
            ->with($query, $cacheId)
            ->willReturn($idList = ['id1', 'id2', 'id3']);

        $this->cacheEntryManager
            ->expects(self::once())
            ->method('getEntriesFromCache')
            ->with($idList, $responseParser)
            ->willReturn(
                $result = [
                    'id1' => ['foobar1'],
                    'id2' => ['foobar2'],
                    'id3' => ['foobar3']
                ]
            );

        static::assertSame(array_values($result), $this->fixture->getEntries($callable, $cacheId, $responseParser));
    }

    /**
     * Test that that entries will be returned
     *
     * @return void
     */
    public function testGetEntryForCachedIdListAndUncachedEntries()
    {
        $cacheId = 'foobar';
        $responseParser = $this->getCustomResponseParser();
        $callable = $this->getQueryCallMethod($query = new Query());

        $this->cacheEntryManager
            ->method('getQueryIdListFromCache')
            ->with($query, $cacheId)
            ->willReturn($idList = ['id1', 'id2', 'id3']);

        $this->cacheEntryManager
            ->expects(self::once())
            ->method('getEntriesFromCache')
            ->with($idList, $responseParser)
            ->willReturn([]);

        $this->client
            ->expects(self::once())
            ->method('getEntries')
            ->with(self::callback(function (Query $query) {
                static::assertSame('sys.id%5Bin%5D=id1%2Cid2%2Cid3', $query->getQueryString());

                return true;
            }))
            ->willReturn(new ResourceArray([$entry = $this->createMock(DynamicEntry::class)], 1, 0, 0));

        $cacheItem = new CacheItem();
        $cacheItem->set($result = ['foobar']);

        $this->cacheEntryManager
            ->expects(self::once())
            ->method('saveEntryInCache')
            ->with($entry, $responseParser)
            ->willReturn($cacheItem);

        static::assertSame([$result], $this->fixture->getEntries($callable, $cacheId, $responseParser));
    }

    /**
     * Get the query build callable
     *
     * @param Query $query
     *
     * @return Closure
     */
    private function getQueryCallMethod(Query $query):Closure
    {
        return function (Query $baseQuery) use($query) {
            return $query;
        };
    }

    /**
     * Get a custom response parser
     *
     * @return ResponseParserInterface
     */
    private function getCustomResponseParser():ResponseParserInterface
    {
        return new class implements ResponseParserInterface {

            /**
             * Makes a simple array out of the response to cache it and make it more independent.
             *
             * @param DynamicEntry|ResourceArray|array $result
             *
             * @return array
             */
            public function toArray($result): array
            {
                return $result;
            }
        };
    }
}
