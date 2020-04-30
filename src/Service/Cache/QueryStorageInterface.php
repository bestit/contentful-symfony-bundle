<?php

declare(strict_types=1);

namespace BestIt\ContentfulBundle\Service\Cache;

use Contentful\Query;

/**
 * Interface for the implementation of the query
 *
 * @author Martin Knoop <martin.knoop@bestit-online.de>
 * @package BestIt\ContentfulBundle\Service\Cache
 */
interface QueryStorageInterface
{
    /**
     * Get all stored queries, indexed by the cache id
     *
     * @return array
     */
    public function getQueries():array;

    /**
     * Save a query in the storage with the given cache id
     *
     * @param Query $query The query that is used
     * @param Query $originalQuery The original query that is build, will be used to generate the cache ids
     * @param string $cacheId A custom cache id that is used
     *
     * @return void
     */
    public function saveQueryInStorage(Query $query, Query $originalQuery, string $cacheId = null);
}
