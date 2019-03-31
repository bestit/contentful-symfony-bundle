<?php

declare(strict_types=1);

namespace BestIt\ContentfulBundle\Service;

use BestIt\ContentfulBundle\Routing\CachingContentfulSlugMatcher;
use Psr\Cache\CacheItemPoolInterface;
use Symfony\Component\Cache\Adapter\TagAwareAdapterInterface;
use stdClass;
use function func_num_args;
use function strtolower;

/**
 * Service to reset the cache for contentful.
 *
 * @author lange <lange@bestit-online.de>
 * @package BestIt\ContentfulBundle\Service
 */
class CacheResetService
{
    /**
     * The used cache.
     *
     * @var CacheItemPoolInterface
     */
    protected $cache;

    /**
     * Which ids should be resetted everytime.
     *
     * @var array
     */
    protected $cacheResetIds;

    /**
     * Should the complete cache be cleared after request.
     *
     * @var bool
     */
    private $withCompleteReset = false;

    /**
     * CacheResetService constructor.
     *
     * @param CacheItemPoolInterface $cache
     * @param array $cacheResetIds
     * @param bool $completeReset
     */
    public function __construct(CacheItemPoolInterface $cache, array $cacheResetIds, bool $completeReset = false)
    {
        $this->withCompleteReset($completeReset);

        $this->cache = $cache;
        $this->cacheResetIds = $cacheResetIds;
    }

    /**
     * Rests the cache for the given entry.
     *
     * @param stdClass $entry
     *
     * @return bool
     */
    public function resetEntryCache(stdClass $entry): bool
    {
        $return = false;

        if (@$entry->sys && @$entry->sys->type && strtolower(@$entry->sys->type) === 'entry') {
            if ($this->withCompleteReset()) {
                $this->cache->clear();
            } else {
                if ($this->cache->hasItem($entryId = $entry->sys->id)) {
                    $this->cache->deleteItem($entryId);
                }

                if ($deleteIds = $this->cacheResetIds) {
                    $this->cache->deleteItems($deleteIds);
                }

                if ($this->cache instanceof TagAwareAdapterInterface) {
                    $this->cache->invalidateTags([CachingContentfulSlugMatcher::COLLECTION_CACHE_KEY, $entryId]);
                }
            }

            $return = true;
        }

        return $return;
    }

    /**
     * Should the complete cache be reset?
     *
     * @param bool $newStatus The new status.
     *
     * @return bool The old status.
     */
    private function withCompleteReset(bool $newStatus = false): bool
    {
        $oldStatus = $this->withCompleteReset;

        if (func_num_args()) {
            $this->withCompleteReset = $newStatus;
        }

        return $oldStatus;
    }
}
