<?php

declare(strict_types=1);

namespace BestIt\ContentfulBundle\Service;

use BestIt\ContentfulBundle\Routing\CachingContentfulSlugMatcher;
use Psr\Cache\CacheItemPoolInterface;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use Psr\Log\NullLogger;
use Symfony\Component\Cache\Adapter\TagAwareAdapterInterface;
use stdClass;
use function array_map;
use function func_num_args;
use function in_array;
use function strtolower;

/**
 * Service to reset the cache for contentful.
 *
 * @author lange <lange@bestit-online.de>
 * @package BestIt\ContentfulBundle\Service
 */
class CacheResetService implements LoggerAwareInterface
{
    use LoggerAwareTrait;

    /**
     * This array saves which entry types are usable for cache resets.
     *
     * @var array
     */
    private const USABLE_ENTRY_TYPE = ['DeletedEntry', 'Entry',];

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
        $this->logger = new NullLogger();
    }

    /**
     * Is the given entry usable for the cache reset.
     *
     * @param stdClass $entry
     *
     * @return bool
     */
    private function isUsableEntryType(stdClass $entry): bool
    {
        return @$entry->sys && @$entry->sys->type &&
            in_array(
                strtolower(@$entry->sys->type),
                array_map('strtolower', self::USABLE_ENTRY_TYPE),
                true
            );
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

        $this->logger->debug('Reset contentful cache for entry.', $logContext = ['entry' => $entry,]);

        if ($this->isUsableEntryType($entry)) {
            if ($completeCacheReset = $this->withCompleteReset()) {
                $this->logger->debug('Reset the complete contentful cache for entry.', $logContext);

                $this->cache->clear();
            } else {
                if ($directMatch = $this->cache->hasItem($entryId = $entry->sys->id)) {
                    $this->logger->debug('Reset the exact contentful cache for entry.', $logContext);

                    $this->cache->deleteItem($entryId);
                }

                if ($deleteIds = $this->cacheResetIds) {
                    $this->logger->debug(
                        'Reset the given reset ids of the contentful cache.',
                        $logContext + ['cacheResetIds' => $deleteIds,]
                    );

                    $this->cache->deleteItems($deleteIds);
                }

                $tags = [CachingContentfulSlugMatcher::COLLECTION_CACHE_KEY, $entryId];

                if ($isTagAwareAdapter = ($this->cache instanceof TagAwareAdapterInterface)) {
                    $this->logger->debug(
                        'Reset the given tags of the contentful cache.',
                        $logContext + ['tags' => $tags,]
                    );

                    $this->cache->invalidateTags($tags);
                }
            }

            $this->logger->info(
                'Reset the contentful cache for the given entry.',
                $logContext + [
                    'cacheResetIds' => $deleteIds ?? [],
                    'completeCacheReset' => $completeCacheReset,
                    'directMatch' => $directMatch ?? false,
                    'tags' => $tags ?? [],
                    'tagsUsable' => $isTagAwareAdapter ?? false,
                ]
            );

            $return = true;
        } else {
            $this->logger->warning('Did not receive an entry for which a cache could be reset.', $logContext);
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
