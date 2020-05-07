<?php

declare(strict_types=1);

namespace BestIt\ContentfulBundle\Tests\Service\Cache;

use BestIt\ContentfulBundle\Delivery\ResponseParserInterface;
use BestIt\ContentfulBundle\Service\Cache\CacheEntryManager;
use Contentful\Delivery\ContentType;
use Contentful\Delivery\DynamicEntry;
use Contentful\Delivery\Query;
use Contentful\ResourceArray;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Cache\Adapter\ArrayAdapter;

/**
 * Tests for teh cache entry manager
 *
 * @author Martin Knoop <martin.knoop@bestit-online.de>
 * @package BestIt\ContentfulBundle\Tests\Service\Cache
 */
class CacheEntryManagerTest extends TestCase
{
    /**
     * The fixture to test
     *
     * @var CacheEntryManager
     */
    private $fixture;

    /**
     * Set up the test
     *
     * @return void
     */
    protected function setUp()
    {
        $this->fixture = new CacheEntryManager(
            $this->getCustomResponseParser(),
            new ArrayAdapter()
        );

        $this->fixture->addResponseParser($this->getCustomResponseParser(), 'foobar');
        $this->fixture->addResponseParser($this->getCustomResponseParser(), 'foobar');

        $this->fixture->setCacheTTL(2);
        parent::setUp();
    }

    /**
     * Test that the id list cache is working
     *
     * @return void
     */
    public function testThatTheIdListCacheIsWorking()
    {
        static::assertNull($this->fixture->getQueryIdListFromCache(new Query()));
        static::assertNull($this->fixture->getQueryIdListFromCache(new Query(), 'foobar'));

        $this->fixture->saveQueryIdListInCache($idList = ['id3', 'id2', 'id1'], new Query());
        $this->fixture->saveQueryIdListInCache($idList = ['id3', 'id2', 'id1'], new Query(), 'foobar');

        static::assertSame($idList, $this->fixture->getQueryIdListFromCache(new Query()));
        static::assertSame($idList, $this->fixture->getQueryIdListFromCache(new Query(), 'foobar'));

        // Sleep 3 seconds to simulate the ttl
        sleep(3);

        static::assertNull($this->fixture->getQueryIdListFromCache(new Query()));
        static::assertNull($this->fixture->getQueryIdListFromCache(new Query(), 'foobar'));
    }

    /**
     * Test that the entry cache is working
     *
     * @return void
     */
    public function testThatTheEntryCacheIsWorking()
    {
        $responseParser = $this->getCustomResponseParser();
        static::assertSame([], $this->fixture->getEntriesFromCache(['entryId'], $responseParser));

        $dynamicEntry = $this->createMock(DynamicEntry::class);
        $dynamicEntry
            ->method('getContentType')
            ->willReturn($contentType = $this->createMock(ContentType::class));

        $dynamicEntry
            ->method('getId')
            ->willReturn('entryId');
        $contentType
            ->method('getName')
            ->willReturn('foobar');

        static::assertSame(['result'], $this->fixture->saveEntryInCache($dynamicEntry, $responseParser)->get());
        static::assertSame(['entryId' =>  ['result']], $this->fixture->getEntriesFromCache(['entryId'], $responseParser));

        // Sleep 3 seconds to simulate the ttl
        sleep(3);
        static::assertSame([], $this->fixture->getEntriesFromCache(['entryId'], $responseParser));
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
                return ['result'];
            }
        };
    }
}
