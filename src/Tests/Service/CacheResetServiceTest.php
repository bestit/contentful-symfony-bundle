<?php

namespace BestIt\ContentfulBundle\Tests\Service;

use BestIt\ContentfulBundle\Service\CacheResetService;
use PHPUnit\Framework\TestCase;
use Psr\Cache\CacheItemPoolInterface;
use stdClass;

/**
 * Tests the service for cache resetting.
 * @author lange <lange@bestit-online.de>
 * @category Tests
 * @package BestIt\ContentfulBundle
 * @subpackage Service
 * @version $id$
 */
class CacheResetServiceTest extends TestCase
{
    /**
     * Checks if the entry cache is reset correctly.
     * @return void
     */
    public function testResetEntryCacheSuccess()
    {
        $fixture = new CacheResetService(
            $cache = $this->createMock(CacheItemPoolInterface::class),
            $ids = [uniqid()],
            false
        );

        $cache
            ->expects(static::once())
            ->method('deleteItem')
            ->with($id = uniqid());

        $cache
            ->expects(static::once())
            ->method('deleteItems')
            ->with($ids);

        $cache
            ->expects(static::once())
            ->method('hasItem')
            ->with($id)
            ->willReturn(true);

        $cache
            ->expects(static::never())
            ->method('clear');

        static::assertTrue($fixture->resetEntryCache((object) [
            'sys' => (object) [
                'id' => $id,
                'type' => 'Entry'
            ]
        ]));
    }

    /**
     * Checks that the whole content cache is cleared if configured.
     * @return void
     */
    public function testThatWholeCacheIsCleared()
    {
        $fixture = new CacheResetService(
            $cache = $this->createMock(CacheItemPoolInterface::class),
            $ids = [uniqid()],
            true
        );

        $cache
            ->expects(static::never())
            ->method('deleteItem')
            ->with($id = uniqid());

        $cache
            ->expects(static::never())
            ->method('deleteItems')
            ->with($ids);

        $cache
            ->expects(static::never())
            ->method('hasItem')
            ->with($id)
            ->willReturn(true);

        $cache
            ->expects(static::once())
            ->method('clear');

        static::assertTrue($fixture->resetEntryCache((object) [
            'sys' => (object) [
                'id' => $id,
                'type' => 'Entry'
            ]
        ]));
    }

    /**
     * Checks if the service does nothing if the entry type is not correct.
     * @return void
     */
    public function testResetEntryCacheWrongType()
    {
        $fixture = new CacheResetService(
            $cache = $this->createMock(CacheItemPoolInterface::class),
            [],
            false
        );

        $cache
            ->expects(static::never())
            ->method('hasItem');

        static::assertFalse($fixture->resetEntryCache(new stdClass()));
    }
}
