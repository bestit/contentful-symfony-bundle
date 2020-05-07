<?php

declare(strict_types=1);

namespace BestIt\ContentfulBundle\Service\Cache;

use BestIt\ContentfulBundle\Service\Delivery\ClientDecorator;
use BestIt\ContentfulBundle\Service\Delivery\ContentSynchronisationClient;
use Symfony\Component\HttpKernel\CacheWarmer\CacheWarmerInterface;

/**
 * Cache warmer to prefill the content full cache
 *
 * @author Martin Knoop <martin.knoop@bestit-online.de>
 * @package BestIt\ContentfulBundle\Service\Cache
 */
class ContentfulCacheWarmer implements CacheWarmerInterface
{
    /**
     * The cache manager for the entries
     *
     * @var CacheEntryManager
     */
    private $cacheEntryManager;

    /**
     * The client to fetch the entries for the prefill
     *
     * @var ContentSynchronisationClient
     */
    private $syncClient;

    /**
     * The storage for all used contentful queries in the project
     *
     * @var QueryStorageInterface
     */
    private $queryStorage;

    /**
     * The decorated contentful client to execute the queries
     *
     * @var ClientDecorator
     */
    private $client;

    /**
     * ContentfulCacheWarmer constructor.
     *
     * @param CacheEntryManager $cacheEntryManager
     * @param ContentSynchronisationClient $syncClient
     * @param QueryStorageInterface $queryStorage
     * @param ClientDecorator $client
     */
    public function __construct(
        CacheEntryManager $cacheEntryManager,
        ContentSynchronisationClient $syncClient,
        QueryStorageInterface $queryStorage,
        ClientDecorator $client
    ) {
        $this->cacheEntryManager = $cacheEntryManager;
        $this->syncClient = $syncClient;
        $this->queryStorage = $queryStorage;
        $this->client = $client;
    }

    /**
     * Checks whether this warmer is optional or not.
     *
     * Optional warmers can be ignored on certain conditions.
     *
     * A warmer should return true if the cache can be
     * generated incrementally and on-demand.
     *
     * @return bool true if the warmer is optional, false otherwise
     */
    public function isOptional()
    {
        return true;
    }

    /**
     * Warm up the cache
     *
     * @param string $cacheDir
     */
    public function warmUp($cacheDir)
    {
        // First we will get all queries from the storage to fill the id list cache
        foreach ($this->queryStorage->getQueries() as $cacheId => $query) {
            $this->client->getAllEntryIds($query, $cacheId);
        }

        // Then we will fetch all entries from contentful and save this entries in the cache
        foreach ($this->syncClient->getSyncEntries() as $syncEntry) {
            $this->cacheEntryManager->saveEntryInCache($syncEntry);
        }
    }
}
