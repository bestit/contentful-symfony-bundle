<?php

declare(strict_types=1);

namespace BestIt\ContentfulBundle\Service\Cache;

use BestIt\ContentfulBundle\CacheTagsGetterTrait;
use BestIt\ContentfulBundle\CacheTTLAwareTrait;
use BestIt\ContentfulBundle\Delivery\ResponseParserInterface;
use Contentful\Delivery\DynamicEntry;
use Contentful\Query;
use Psr\Cache\CacheItemInterface;
use Psr\Cache\CacheItemPoolInterface;
use Psr\Log\LoggerAwareTrait;
use Psr\Log\NullLogger;
use function get_class;
use function sprintf;
use function array_unique;
use function array_keys;
use function array_filter;

/**
 * Manager to handle all cache operations for the content full elements
 *
 * @author Martin Knoop <martin.knoop@bestit-online.de>
 * @package BestIt\ContentfulBundle\Service\Cache
 */
class CacheEntryManager
{
    use CacheKeyTrait;
    use CacheTagsGetterTrait;
    use CacheTTLAwareTrait;
    use LoggerAwareTrait;

    /**
     * The array of all response parsers for the content types
     *
     * @var ResponseParserInterface[]
     */
    private $responseParsers;

    /**
     * The default response parser is used every time no custom parser is configured or given
     *
     * @var ResponseParserInterface
     */
    private $defaultResponseParser;

    /**
     * The used cache
     *
     * @var CacheItemPoolInterface
     */
    private $cache;

    /**
     * CacheEntryManager constructor.
     *
     * @param ResponseParserInterface $defaultResponseParser
     * @param CacheItemPoolInterface $cache
     */
    public function __construct( ResponseParserInterface $defaultResponseParser, CacheItemPoolInterface $cache)
    {
        $this->defaultResponseParser = $defaultResponseParser;
        $this->cache = $cache;
        $this->logger = new NullLogger();
    }

    /**
     * Add a response parser for the given content type
     *
     * @param ResponseParserInterface $responseParser
     * @param string $contentType
     *
     * @return self
     */
    public function addResponseParser(ResponseParserInterface $responseParser, string $contentType):CacheEntryManager
    {
        $this->responseParsers[$contentType][] = $responseParser;

        return $this;
    }

    /**
     * Get the query id list from the cache
     *
     * @param Query $query The query to generate the cache key
     * @param string $cacheId A custom cache id that is used
     *
     * @return array|null
     */
    public function getQueryIdListFromCache(Query $query, string $cacheId = null)
    {
        $this->logger->debug(
            'Try to fetch id list for the given query from the cache',
            [
                'query' => $query->getQueryString(),
                'cacheId' => $cacheId = $cacheId ?? $this->getQueryIdsCacheKey($query)
            ]
        );

        return $this->cache->getItem($cacheId)->get();
    }

    /**
     * Save the query id list in the cache
     *
     * @param array $idList A list of ids that will be saved in the cache
     * @param Query $query The query for the id list, used for the cache key
     * @param string $cacheId A custom cache id that is used
     *
     * @return void
     */
    public function saveQueryIdListInCache(array $idList, Query $query, string $cacheId = null)
    {
        $this->logger->debug(
            'Save id list for query in cache',
            [
                'idList' => $idList,
                'query' => $query->getQueryString(),
                'cacheId' => $cacheId = $cacheId ?? $this->getQueryIdsCacheKey($query)
            ]
        );

        $cacheItem = $this->cache->getItem($cacheId);
        $cacheItem->set($idList);

        if ($cacheTTL = $this->getCacheTTL()) {
            $cacheItem->expiresAfter($cacheTTL);
        }

        $this->cache->save($cacheItem);
    }

    /**
     * Save entry in cache
     *
     * @param DynamicEntry $entry The entry that will be saved in the cache
     * @param ResponseParserInterface|null $customResponseParser A custom response parser that will
*                                                                be used for the return value
     *
     * @return CacheItemInterface
     */
    public function saveEntryInCache(DynamicEntry $entry, ResponseParserInterface $customResponseParser = null):CacheItemInterface
    {
        $contentType = $entry->getContentType()->getName();

        $entryTags = $this->getCacheTags($entry);
        $parsers = $this->getResponseParserForContentType($contentType, $customResponseParser);

        $cacheItem = null;
        foreach ($parsers as $responseParser) {
            $cacheItem = $this->saveInCache($responseParser, $entry, $entryTags);
        }

        assert($cacheItem instanceof CacheItemInterface);

        return $cacheItem;
    }

    /**
     * Get entries from the cache
     *
     * @param array $ids Array of ids that will be fetched from the cache
     * @param ResponseParserInterface $responseParser A custom response parser that will
     *                                                be used for the return value
     *
     * @return DynamicEntry[]
     */
    public function getEntriesFromCache(array $ids, ResponseParserInterface $responseParser): array
    {
        $cachedEntries = [];

        foreach ($ids as $id) {
            $cachedItem = $this->cache->getItem($this->getEntryCacheKey($id, get_class($responseParser)));
            if ($cachedItem->isHit()) {
                $cachedEntries[$id] = $cachedItem->get();
            }
        }

        $this->logger->debug('Revived cached entries', ['ids' => array_keys($cachedEntries)]);

        return $cachedEntries;
    }

    /**
     * Get all the configured response parser for the content type
     *
     * @param string $contentType The content type for the response
     * @param ResponseParserInterface|null $customResponseParser The custom response parser
     *
     * @return ResponseParserInterface[]
     */
    private function getResponseParserForContentType(string $contentType, ResponseParserInterface $customResponseParser = null)
    {
        $responseParsers = $this->responseParsers[$contentType] ?? [];
        $responseParsers[] = $this->defaultResponseParser;
        $responseParsers[] = $customResponseParser;

        return array_unique(array_filter($responseParsers), SORT_REGULAR);
    }

    /**
     * Save a entry in the cache
     *
     * @param ResponseParserInterface $responseParser The parser for which the cache entry has been created
     * @param DynamicEntry $entry The entry to save
     * @param array $entryTags The tags for the entry
     *
     * @return CacheItemInterface
     */
    private function saveInCache(ResponseParserInterface $responseParser, DynamicEntry $entry, array $entryTags):CacheItemInterface
    {
        $entryId = $entry->getId();
        $parserClass = get_class($responseParser);

        $cacheItem = $this->cache->getItem($this->getEntryCacheKey($entryId, $parserClass));
        $cacheItem->set($responseParser->toArray($entry));

        if ($entryTags && method_exists($cacheItem, 'tag')) {
            $cacheItem->tag($entryTags);
        }

        if ($cacheTTL = $this->getCacheTTL()) {
            $cacheItem->expiresAfter($cacheTTL);
        }

        $this->cache->save($cacheItem);

        $this->logger->debug(
            sprintf('Save entry with id %s for parser cache ', $entryId),
            [
                'entryId' => $entryId,
                'parser' => $parserClass,
            ]
        );
        return $cacheItem;
    }
}
