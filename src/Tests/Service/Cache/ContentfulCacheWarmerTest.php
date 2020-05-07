<?php

declare(strict_types=1);

namespace BestIt\ContentfulBundle\Tests\Service\Cache;

use BestIt\ContentfulBundle\Service\Cache\CacheEntryManager;
use BestIt\ContentfulBundle\Service\Cache\ContentfulCacheWarmer;
use BestIt\ContentfulBundle\Service\Cache\QueryStorageInterface;
use BestIt\ContentfulBundle\Service\Delivery\ClientDecorator;
use BestIt\ContentfulBundle\Service\Delivery\ContentSynchronisationClient;
use Contentful\Delivery\DynamicEntry;
use Contentful\Delivery\Query;
use PHPUnit\Framework\TestCase;

/**
 * Tests for the cache warmup
 *
 * @author Martin Knoop <martin.knoop@bestit-online.de>
 * @package BestIt\ContentfulBundle\Tests\Service\Cache
 */
class ContentfulCacheWarmerTest extends TestCase
{
    /**
     * Test that the warmup works
     *
     * @return void
     */
    public function testThatTheWarmupWorks()
    {
        $fixture = new ContentfulCacheWarmer(
            $cacheManager = $this->createMock(CacheEntryManager::class),
            $client = $this->createMock(ContentSynchronisationClient::class),
            $queryStorage = $this->createMock(QueryStorageInterface::class),
            $clientDecorator = $this->createMock(ClientDecorator::class)
        );

        static::assertTrue($fixture->isOptional());

        $queryStorage
            ->method('getQueries')
            ->willReturn(
                [
                    'cacheId1' =>  $query1 = new Query(),
                    'cacheId2' =>  $query2 = new Query(),
                    'cacheId3' =>  $query3 = new Query(),
                    'cacheId4' =>  $query4 = new Query(),
                    'cacheId5' =>  $query5 = new Query(),
                ]
            );

        $clientDecorator
            ->expects(self::exactly(5))
            ->method('getAllEntryIds')
            ->withConsecutive(
                    [$query1, 'cacheId1'],
                    [$query2, 'cacheId2'],
                    [$query3, 'cacheId3'],
                    [$query4, 'cacheId4'],
                    [$query5, 'cacheId5']
            );

        $entry =  $this->createMock(DynamicEntry::class);;
        $generator = function () use($entry) {
          yield $entry;
        };

        $client
            ->method('getSyncEntries')
            ->willReturn($generator());

        $cacheManager
            ->expects(self::once())
            ->method('saveEntryInCache')
            ->with($entry);

        $fixture->warmUp('foobar');
   }
}
