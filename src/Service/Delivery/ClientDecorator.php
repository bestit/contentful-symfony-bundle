<?php

declare(strict_types=1);

namespace BestIt\ContentfulBundle\Service\Delivery;

use BestIt\ContentfulBundle\ClientEvents;
use BestIt\ContentfulBundle\Delivery\ResponseParserInterface;
use BestIt\ContentfulBundle\Service\Cache\CacheEntryManager;
use Contentful\Delivery\Asset;
use Contentful\Delivery\Client;
use Contentful\Delivery\DynamicEntry;
use Contentful\Delivery\Query;
use Contentful\ResourceArray;
use Psr\Cache\CacheItemPoolInterface;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\EventDispatcher\GenericEvent;
use Throwable;
use function get_class;
use function sprintf;
use function array_diff;
use function array_keys;
use function count;

/**
 * Extends the logics for the contentful delivery.
 *
 * @author  lange <lange@bestit-online.de>
 * @method  Asset getAsset(string $id, string | null $locale = null)
 * @package BestIt\ContentfulBundle\Service\Delivery
 */
class ClientDecorator implements LoggerAwareInterface
{
    use LoggerAwareTrait;

    /**
     * @var CacheItemPoolInterface The possible cache class.
     */
    private $cache;

    /**
     * @var Client The used client.
     */
    private $client;

    /**
     * @var ResponseParserInterface The default response parser.
     */
    private $defaultResponseParser;

    /**
     * @var EventDispatcherInterface The event dispatcher.
     */
    private $eventDispatcher;

    /**
     * @var CacheEntryManager The cache manager for the entries
     */
    private $cacheEntryManager;

    /**
     * ClientDecorator constructor.
     *
     * @param Client $client
     * @param CacheItemPoolInterface $cache
     * @param EventDispatcherInterface $eventDispatcher
     * @param LoggerInterface $logger
     * @param ResponseParserInterface $responseParser
     * @param CacheEntryManager $cacheEntryManager
     */
    public function __construct(
        Client $client,
        CacheItemPoolInterface $cache,
        EventDispatcherInterface $eventDispatcher,
        LoggerInterface $logger,
        ResponseParserInterface $responseParser,
        CacheEntryManager $cacheEntryManager
    ) {
        $this->cache = $cache;
        $this->client = $client;
        $this->defaultResponseParser = $responseParser;
        $this->eventDispatcher = $eventDispatcher;
        $this->cacheEntryManager = $cacheEntryManager;

        $this->setLogger($logger);
    }

    /**
     * Delegates to the original client.
     *
     * @param string $method
     * @param array $args
     *
     * @return mixed
     */
    public function __call(string $method, array $args = [])
    {
        return $this->client->$method(...$args);
    }

    /**
     * Returns a base query.
     *
     * @return Query
     */
    protected function getBaseQuery(): Query
    {
        return new Query();
    }

    /**
     * Returns a list of clients.
     *
     * @param callable $buildQuery
     * @param bool|string $cacheId
     * @param ResponseParserInterface $parser
     *
     * @return array
     */
    public function getEntries(
        callable $buildQuery,
        string $cacheId = '',
        ResponseParserInterface $parser = null
    ): array {
        $query = $this->getBaseQuery();
        $buildQuery($query);

        $entryIds = $this->getAllEntryIds($query, !empty($cacheId) ? $cacheId : null);
        $entries = null;

        if (!$parser) {
            $parser = $this->defaultResponseParser;
        }

        if (count($entryIds) && $foundEntries = count($entries = $this->getEntriesByIds($entryIds, $parser))) {
            $this->logger->debug(
                'Found contentful elements.',
                ['cacheId' => $cacheId, 'parser' => get_class($parser), 'found' => $foundEntries]
            );
        }

        return is_array($entries) ? $entries : [];
    }

    /**
     * Returns (and caches) the entry with the given id.
     *
     * @param string $id
     * @param ResponseParserInterface|null $parser
     *
     * @return array
     */
    public function getEntry(string $id, ResponseParserInterface $parser = null): array
    {
        if (!$parser) {
            $parser = $this->defaultResponseParser;
        }

        $entries = $this->getEntriesByIds([$id], $parser);

        return count($entries) ? current($entries) : [];
    }

    /**
     * Get the entry ids for a query
     *
     * @param Query $query The query that will be executed
     * @param string|null $cacheId A custom cache id
     *
     * @return array
     */
    private function getAllEntryIds(Query $query, string $cacheId = null): array
    {
        $this->logger->debug(
            'Loading contentful entry Ids for given query. Try to fetch ids from cache first.',
            ['query' => $query->getQueryString()]
        );

        $idList = $this->cacheEntryManager->getQueryIdListFromCache($query, $cacheId) ?? [];

        if ($idList === []) {
            $originalQuery = clone $query;
            // We only want to select the ids because these values can't be cached via the webhook
            $query->select(['sys.id']);

            $this->logger->debug('No ids in cache found, fetch from contentful.');

            $entries = $this->client->getEntries($query);
            if ($entries && count($entries)) {
                $idList = array_map(
                    function (DynamicEntry $dynamicEntry) {
                        return $dynamicEntry->getId();
                    }, $entries->getItems()
                );

                $this->cacheEntryManager->saveQueryIdListInCache($idList, $originalQuery);
            }
        }

        return $idList;
    }

    /**
     * Get the entries for the given ids
     *
     * Try to fetch the ids from the cache first.
     *
     * @param array $ids The ids that will be fetched
     * @param ResponseParserInterface|null $responseParser The response parser that is used for the result
     *
     * @return array
     */
    private function getEntriesByIds(array $ids, ResponseParserInterface $responseParser = null): array
    {
        $this->logger->debug(
            'Loading dynamic entries for the given ids. Try to fetch entries from cache first.',
            ['ids' => $ids]
        );

        $cachedEntries = $this->cacheEntryManager->getEntriesFromCache($ids, $responseParser);
        $notFoundIds = array_diff($ids, array_keys($cachedEntries));

        $this->logger->debug(
            'Fetched entries from cache .',
            ['missing_entries' => count($notFoundIds)]
        );

        if (count($notFoundIds)) {
            $query = (new Query())->where('sys.id[in]', $notFoundIds);

            $this->logger->debug(
                'Loading missing contentful element with IDs .',
                ['ids' => $notFoundIds]
            );

            /** @var ResourceArray $entries */
            if ($entries = $this->fetchEntries($query)) {

                /** @var DynamicEntry $entry */
                foreach ($entries as $entry) {
                    $this->eventDispatcher->dispatch(ClientEvents::LOAD_CONTENTFUL_ENTRY, new GenericEvent($entry));
                    $cacheItem = $this->cacheEntryManager->saveEntryInCache($entry, $responseParser);

                    $this->logger->notice(
                        sprintf('Found contentful element with ID %s.', $entry->getId()),
                        ['id' => $entry->getId()]
                    );
                    $cachedEntries[] = $cacheItem->get();
                }
            }
        }

        return array_values($cachedEntries);
    }

    /**
     * Fetch the entries by the given query
     *
     * @param Query $query The query that will be used
     *
     * @return ResourceArray|null
     */
    private function fetchEntries(Query $query)
    {
        $this->logger->debug('Fetch entries from ct directly', ['query' => $query->getQueryString()]);

        $entries = null;
        try {
            $entries = $this->client->getEntries($query);

            $this->eventDispatcher->dispatch(ClientEvents::LOAD_CONTENTFUL_ENTRIES, new GenericEvent($entries));
        } catch (Throwable $e) {
            $this->logger->critical(
                'Elements could not be loaded.',
                ['exception' => $e, 'query' => $query->getQueryString(), 'trace' => $e->getTrace()]
            );
        }

        $this->logger->debug('Fetched entries', ['result' => is_object($entries) ? get_class($entries) : 'no_object']);

        return $entries;
    }
}
