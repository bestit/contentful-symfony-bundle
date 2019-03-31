<?php

declare(strict_types=1);

namespace BestIt\ContentfulBundle\Tests\Service;

use BestIt\ContentfulBundle\Service\CacheResetService;
use BestIt\ContentfulBundle\Tests\TestTraitsTrait;
use PHPUnit\Framework\TestCase;
use PHPUnit_Framework_MockObject_MockObject;
use Psr\Cache\CacheItemPoolInterface;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use Symfony\Component\Cache\Adapter\TagAwareAdapter;
use Symfony\Component\Cache\Adapter\TagAwareAdapterInterface;
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
    use TestTraitsTrait;

    /**
     * The injected cache adapter.
     *
     * @var TagAwareAdapterInterface|null|PHPUnit_Framework_MockObject_MockObject
     */
    private $cacheAdapter;

    /**
     * The used cache reset ids.
     *
     * @var array|null
     */
    private $cacheResetIds;

    /**
     * An object of the tested class.
     *
     * @var CacheResetService|null
     */
    protected $fixture;

    /**
     * Should a full cache reset be done?
     *
     * @var bool|null
     */
    private $withCompleteReset;

    /**
     * Returns entry types which should be valid for cache resets.
     *
     * @return array
     */
    public function getUsableEntryTypes(): array
    {
        return [
            'Deleted Entry' => ['DeletedEntry',],
            'Entry' => ['Entry',],
        ];
    }

    /**
     * Returns the names of the used traits.
     *
     * @return array
     */
    protected function getUsedTraitNames(): array
    {
        return [
            LoggerAwareTrait::class,
        ];
    }

    /**
     * Loads the required object instance.
     *
     * @return void
     */
    protected function setUp(): void
    {
        $this->fixture = new CacheResetService(
            $this->cacheAdapter = $this->createMock(TagAwareAdapter::class),
            $this->cacheResetIds = [uniqid(),],
            $this->withCompleteReset = false
        );
    }

    /**
     * Checks if the required interfaces are implemented.
     *
     * @return void
     */
    public function testInterfaces(): void
    {
        static::assertInstanceOf(LoggerAwareInterface::class, $this->fixture);
    }

    /**
     * Checks if the entry cache is reset correctly.
     *
     * @dataProvider getUsableEntryTypes
     * @param string $usableEntryType
     *
     * @return void
     */
    public function testResetEntryCacheSuccess(string $usableEntryType): void
    {
        $this->cacheAdapter
            ->expects(static::never())
            ->method('clear');

        $this->cacheAdapter
            ->expects(static::once())
            ->method('deleteItem')
            ->with($id = uniqid());

        $this->cacheAdapter
            ->expects(static::once())
            ->method('deleteItems')
            ->with($this->cacheResetIds);

        $this->cacheAdapter
            ->expects(static::once())
            ->method('hasItem')
            ->with($id)
            ->willReturn(true);

        $this->cacheAdapter
            ->expects(static::once())
            ->method('invalidateTags')
            ->with(['route_collection', $id]);

        static::assertTrue($this->fixture->resetEntryCache((object) [
            'sys' => (object) [
                'id' => $id,
                'type' => $usableEntryType,
            ],
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
     * @dataProvider getUsableEntryTypes
     * @param string $usableEntryType
     *
     * @return void
     */
    public function testThatWholeCacheIsCleared(string $usableEntryType): void
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
                'type' => $usableEntryType,
            ],
        ]));
    }
}
