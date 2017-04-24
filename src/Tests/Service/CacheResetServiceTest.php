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
        $fixture = new CacheResetService($cache = static::createMock(CacheItemPoolInterface::class), $ids = [uniqid()]);

        $cache
            ->expects($this->once())
            ->method('deleteItem')
            ->with($id = uniqid());

        $cache
            ->expects($this->once())
            ->method('deleteItems')
            ->with($ids);

        $cache
            ->expects($this->once())
            ->method('hasItem')
            ->with($id)
            ->willReturn(true);

        $this->assertTrue($fixture->resetEntryCache((object) [
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
        $fixture = new CacheResetService($cache = static::createMock(CacheItemPoolInterface::class), []);

        $cache
            ->expects($this->never())
            ->method('hasItem');

        $this->assertFalse($fixture->resetEntryCache(new stdClass()));
    }
}
