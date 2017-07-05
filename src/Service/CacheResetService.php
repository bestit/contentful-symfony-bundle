<?php

namespace BestIt\ContentfulBundle\Service;

use Psr\Cache\CacheItemPoolInterface;
use stdClass;
use Symfony\Component\Cache\Adapter\TagAwareAdapterInterface;

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
     * Should the complete cache be cleared after request
     * @var bool
     */
    private $webhookCompleteReset = false;

    /**
     * CacheResetService constructor.
     *
     * @param CacheItemPoolInterface $cache
     * @param array $cacheResetIds
     * @param bool $webhookCompleteReset
     */
    public function __construct(CacheItemPoolInterface $cache, array $cacheResetIds, bool $webhookCompleteReset)
    {
        $this->setCache($cache)
            ->setCacheResetIds($cacheResetIds)
            ->setWebhookCompleteReset($webhookCompleteReset);
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
     * Is WebhookCompleteReset
     *
     * @return bool
     */
    protected function isWebhookCompleteReset(): bool
    {
        return $this->webhookCompleteReset;
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

            if ($pool instanceof TagAwareAdapterInterface) {
                $pool->invalidateTags([$entryId]);
            }

            // Should the whole contentful cache be cleared ?
            if ($this->isWebhookCompleteReset()) {
                $pool->clear();
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

    /**
     * Set WebhookCompleteReset
     *
     * @param bool $webhookCompleteReset
     * @return CacheResetService
     */
    public function setWebhookCompleteReset(bool $webhookCompleteReset): CacheResetService
    {
        $this->webhookCompleteReset = $webhookCompleteReset;

        return $this;
    }
}
