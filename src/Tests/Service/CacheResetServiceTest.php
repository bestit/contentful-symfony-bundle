<?php

declare(strict_types=1);

namespace BestIt\ContentfulBundle\Tests\Service;

use BestIt\ContentfulBundle\Service\CacheResetService;
use PHPUnit\Framework\TestCase;
use Psr\Cache\CacheItemPoolInterface;
use Symfony\Component\Cache\Adapter\TagAwareAdapter;
use stdClass;
use function uniqid;

/**
 * Tests the service for cache resetting.
 *
 * @author lange <lange@bestit-online.de>
 * @package BestIt\ContentfulBundle\Tests\Service
 */
class CacheResetServiceTest extends TestCase
{
    /**
     * Checks if the entry cache is reset correctly.
     *
     * @return void
     */
    public function testResetEntryCacheSuccess(): void
    {
        $fixture = new CacheResetService(
            $cache = $this->createMock(TagAwareAdapter::class),
            $ids = [uniqid()],
            false
        );

        $cache
            ->expects(static::never())
            ->method('clear');

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
            ->expects(static::once())
            ->method('invalidateTags')
            ->with(['route_collection', $id]);

        static::assertTrue($fixture->resetEntryCache((object) [
            'sys' => (object) [
                'id' => $id,
                'type' => 'Entry'
            ]
        ]));
    }

    /**
     * Checks if the service does nothing if the entry type is not correct.
     *
     * @return void
     */
    public function testResetEntryCacheWrongType(): void
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

    /**
     * Checks that the whole content cache is cleared if configured.
     *
     * @return void
     */
    public function testThatWholeCacheIsCleared(): void
    {
        $fixture = new CacheResetService(
            $cache = $this->createMock(CacheItemPoolInterface::class),
            $ids = [uniqid()],
            true
        );

        $cache
            ->expects(static::once())
            ->method('clear');

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

        static::assertTrue($fixture->resetEntryCache((object) [
            'sys' => (object) [
                'id' => $id,
                'type' => 'Entry'
            ]
        ]));
    }
}
