<?php

declare(strict_types=1);

namespace BestIt\ContentfulBundle\Service\Cache;

use Contentful\Query;

/**
 * Trait that is used to generate the cache keys
 *
 * @author Martin Knoop <martin.knoop@bestit-online.de>
 * @package BestIt\ContentfulBundle\Service\Cache
 */
trait CacheKeyTrait
{
    /**
     * Get cache key for a entry
     *
     * @param string $entryId The id of the entry
     * @param string $responseParserClass The parser that is used for the result
     *
     * @return string
     */
    public function getEntryCacheKey(string $entryId, string $responseParserClass):string
    {
        return 'Parser_' . md5($responseParserClass) .  'Entry_' . $entryId;
    }

    /**
     * Get cache key for a id
     *
     * @param Query $query The query that is used to fetch the ids
     *
     * @return string
     */
    public function getQueryIdsCacheKey(Query $query):string
    {
        return md5($query->getQueryString()) . '_Ids';
    }
}
