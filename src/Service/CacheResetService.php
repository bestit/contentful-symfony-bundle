<?php

namespace BestIt\ContentfulBundle\Service;

use Psr\Cache\CacheItemPoolInterface;
use stdClass;

/**
 * Service to reset the cache for contentful.
 * @author lange <lange@bestit-online.de>
 * @package BestIt\ContentfulBundle
 * @subpackage Service
 * @version $id$
 */
class CacheResetService
{
    /**
     * The used cache.
     * @var CacheItemPoolInterface
     */
    protected $cache = null;

    /**
     * Which ids should be resetted everytime.
     * @var array
     */
    protected $cacheResetIds = [];

    /**
     * CacheResetService constructor.
     * @param CacheItemPoolInterface $cache
     * @param array $cacheResetIds
     */
    public function __construct(CacheItemPoolInterface $cache, array $cacheResetIds)
    {
        $this
            ->setCache($cache)
            ->setCacheResetIds($cacheResetIds);
    }

    /**
     * Returns the cache.
     * @return CacheItemPoolInterface
     */
    protected function getCache(): CacheItemPoolInterface
    {
        return $this->cache;
    }

    /**
     * Returns the cache ids which should be resetted everytime.
     * @return array
     */
    protected function getCacheResetIds(): array
    {
        return $this->cacheResetIds;
    }

    /**
     * Rests the cache for the given entry.
     * @param stdClass $entry
     * @return bool
     */
    public function resetEntryCache(stdClass $entry)
    {
        /** @var CacheItemPoolInterface $pool */
        $pool = $this->getCache();
        $return = false;

        if (strtolower(@$entry->sys->type) === 'entry') {
            if ($pool->hasItem($entryId = $entry->sys->id)) {
                $pool->deleteItem($entryId);
            }

            if ($deleteIds = $this->getCacheResetIds()) {
                $pool->deleteItems($deleteIds);
            }

            $return = true;
        }

        return $return;
    }

    /**
     * Sets the cache pool.
     * @param CacheItemPoolInterface $cache
     * @return CacheResetService
     */
    protected function setCache(CacheItemPoolInterface $cache): CacheResetService
    {
        $this->cache = $cache;

        return $this;
    }

    /**
     * Sets the cache ids which should be reseted everytime.
     * @param array $cacheResetIds
     * @return CacheResetService
     */
    protected function setCacheResetIds(array $cacheResetIds): CacheResetService
    {
        $this->cacheResetIds = $cacheResetIds;

        return $this;
    }
}
