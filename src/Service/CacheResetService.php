<?php

declare(strict_types=1);

namespace BestIt\ContentfulBundle\Service;

use BestIt\ContentfulBundle\Routing\ContentfulSlugMatcher;
use BestIt\ContentfulBundle\Routing\RoutableTypesAwareTrait;
use Psr\Cache\CacheItemPoolInterface;
use stdClass;
use Symfony\Component\Cache\Adapter\TagAwareAdapterInterface;
use function func_num_args;
use function in_array;
use function sha1;
use function strtolower;

/**
 * Service to reset the cache for contentful.
 *
 * @author lange <lange@bestit-online.de>
 * @package BestIt\ContentfulBundle\Service
 */
class CacheResetService
{
    use RoutableTypesAwareTrait;

    /**
     * @var CacheItemPoolInterface The used cache.
     */
    protected $cache;

    /**
     * @var array Which ids should be resetted everytime.
     */
    protected $cacheResetIds;

    /**
     * @var bool Should the complete cache be cleared after request.
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
     * Returns the cache tags for the given contentful entry.
     *
     * @param stdClass $entry
     * @param string $entryId
     * @return array
     */
    private function getCacheTags(stdClass $entry, string $entryId): array
    {
        $tags = [ContentfulSlugMatcher::COLLECTION_CACHE_KEY, $entryId];

        if (@$entry->sys->contentType &&
            (in_array($entry->sys->contentType->sys->id, $this->getRoutableTypes()))) {

            $tags = array_merge($tags, $this->getSlugTags($entry));
        }

        return $tags;
    }

    /**
     * Returns tags for the slug field of the entry.
     *
     * @param stdClass $entry
     * @return array
     */
    private function getSlugTags(stdClass $entry): array
    {
        $tags = [];

        $slugFieldName = $this->getSlugField();

        if ($slugFieldName && ($slugField = $entry->fields->{$slugFieldName})) {
            foreach ($slugField as $lang => $slug) {
                $tags[] = ContentfulSlugMatcher::ROUTE_CACHE_KEY_PREFIX . sha1($slug);
            }
        }

        return $tags;
    }

    /**
     * Rests the cache for the given entry.
     *
     * @param stdClass $entry
     * @return bool
     */
    public function resetEntryCache(stdClass $entry)
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
                    $this->cache->invalidateTags($this->getCacheTags($entry, $entryId));
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
