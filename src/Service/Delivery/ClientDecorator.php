<?php

namespace BestIt\ContentfulBundle\Service\Delivery;

use BestIt\ContentfulBundle\ClientEvents;
use BestIt\ContentfulBundle\Delivery\ResponseParserInterface;
use Contentful\Delivery\Client;
use Contentful\Delivery\DynamicEntry;
use Contentful\Delivery\Query;
use Contentful\ResourceArray;
use GuzzleHttp\Exception\RequestException;
use Psr\Cache\CacheItemPoolInterface;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\EventDispatcher\GenericEvent;

/**
 * Extends the logics for the contentful delivery.
 * @author lange <lange@bestit-online.de>
 * @package BestIt\ContentfulBundle
 * @subpackage Service
 * @version $id$
 */
class ClientDecorator implements LoggerAwareInterface
{
    use LoggerAwareTrait;

    /**
     * The possible cache class.
     * @var CacheItemPoolInterface
     */
    protected $cache = null;

    /**
     * The used client.
     * @var Client
     */
    protected $client = null;

    /**
     * The default response parser.
     * @var ResponseParserInterface
     */
    protected $defaultResponseParser = null;

    /**
     * The event dispatcher.
     * @var EventDispatcherInterface
     */
    protected $eventDispatcher = null;

    /**
     * Delegates to the original client.
     * @param string $method
     * @param array $args
     * @return mixed
     */
    public function __call(string $method, array $args = [])
    {
        return $this->client->$method(...$args);
    }

    /**
     * ClientDecorator constructor.
     * @param Client $client
     * @param CacheItemPoolInterface $cache
     * @param EventDispatcherInterface $eventDispatcher
     * @param LoggerInterface $logger
     * @param ResponseParserInterface $responseParser
     */
    public function __construct(
        Client $client,
        CacheItemPoolInterface $cache,
        EventDispatcherInterface $eventDispatcher,
        LoggerInterface $logger,
        ResponseParserInterface $responseParser
    ) {
        $this
            ->setCache($cache)
            ->setClient($client)
            ->setDefaultResponseParser($responseParser)
            ->setEventDispatcher($eventDispatcher)
            ->setLogger($logger);
    }

    /**
     * Returns a base query.
     * @return Query
     */
    protected function getBaseQuery(): Query
    {
        return new Query();
    }

    /**
     * Returns the cache if there is one.
     * @return void|CacheItemPoolInterface
     */
    private function getCache()
    {
        return $this->cache;
    }

    /**
     * Returns the used client.
     * @return Client
     */
    private function getClient(): Client
    {
        return $this->client;
    }

    /**
     * Returns the default response parser.
     * @return ResponseParserInterface
     */
    private function getDefaultResponseParser(): ResponseParserInterface
    {
        return $this->defaultResponseParser;
    }

    /**
     * Returns a list of clients.
     * @param callable $buildQuery
     * @param bool|string $cacheId
     * @param ResponseParserInterface $parser
     * @return array
     */
    public function getEntries(
        callable $buildQuery,
        string $cacheId = '',
        ResponseParserInterface $parser = null
    ):array {
        $cache = $this->getCache();
        $cacheItem = null;
        $cacheHit = false;
        $entries = null;
        $logger = $this->getLogger();
        $query = $this->getBaseQuery();

        $buildQuery($query);

        if ($cacheId) {
            $cacheItem = $cache->getItem($cacheId);

            if ($cacheHit = $cacheItem->isHit()) {
                $entries = $cacheItem->get();
            }
        }

        if (!$cacheHit || !$cacheId) {
            $dispatcher = $this->getEventDispatcher();

            if (!$parser) {
                $parser = $this->getDefaultResponseParser();
            }

            try {
                $logger->debug(
                    'Loading contentful elements.',
                    ['cacheId' => $cacheId, 'parser' => get_class($parser)]
                );

                /** @var ResourceArray $entries */
                $entries = $this->client->getEntries($query);
                $entryIds = [];

                $dispatcher->dispatch(ClientEvents::LOAD_CONTENTFUL_ENTRIES, new GenericEvent($entries));

                foreach ($entries as $entry) {
                    $dispatcher->dispatch(ClientEvents::LOAD_CONTENTFUL_ENTRY, new GenericEvent($entry));

                    if ($entry instanceof DynamicEntry) {
                        $entryIds[] = $entry->getId();
                    }
                }

                $entries = $parser->toArray($entries);

                $logger->notice(
                    'Found contentful elements.',
                    ['cacheId' => $cacheId, 'entries' => $entries, 'parser' => get_class($parser)]
                );
            } catch (RequestException $exception) {
                $this->getLogger()->critical(
                    'Elements could not be loaded.',
                    ['cacheId' => $cacheId, 'exception' => $exception, 'parser' => get_class($parser)]
                );
            }
        }

        if ($cacheId && !$cacheHit && $entries !== null) {
            if (array_filter($entryIds) && method_exists($cacheItem, 'tag')) {
                $cacheItem->tag($entryIds);
            }

            $cache->save($cacheItem->set($entries));
        }

        return $entries ?? [];
    }

    /**
     * Returns (and caches) the entry with the given id.
     * @param string $id
     * @return array
     */
    public function getEntry(string $id, ResponseParserInterface $parser = null): array
    {
        $cache = $this->getCache();
        $cacheItem = $cache->getItem($id);
        $entry = [];
        $logger = $this->getLogger();

        if (!$parser) {
            $parser = $this->getDefaultResponseParser();
        }

        if ($cacheHit = $cacheItem->isHit()) {
            $entry = $cacheItem->get();
        }

        if (!$cacheHit) {
            try {
                $logger->debug(
                    sprintf('Loading contentful element with ID %s.', $id),
                    ['id' => $id, 'parser' => get_class($parser)]
                );

                $entry = $this->getClient()->getEntry($id);

                $this->getEventDispatcher()->dispatch(ClientEvents::LOAD_CONTENTFUL_ENTRY, new GenericEvent($entry));

                $entry = $parser->toArray($entry);

                $logger->notice(
                    sprintf('Found contentful element with ID %s.', $id),
                    ['id' => $id, 'entry' => $entry, 'parser' => get_class($parser)]
                );

                if (method_exists($cacheItem, 'tag')) {
                    $cacheItem->tag($id);
                }

                $cache->save($cacheItem->set($entry));
            } catch (RequestException $exception) {
                $logger->critical(
                    sprintf('Element with ID %s could not be loaded.', $id),
                    ['exception' => $exception, 'id' => $id, 'parser' => get_class($parser)]
                );
            }
        }

        return $entry;
    }

    /**
     * Returns the event dispatcher.
     * @return EventDispatcherInterface
     */
    private function getEventDispatcher(): EventDispatcherInterface
    {
        return $this->eventDispatcher;
    }

    /**
     * Returns the used logger.
     * @return LoggerInterface
     */
    private function getLogger(): LoggerInterface
    {
        return $this->logger;
    }

    /**
     * Sets the possible cache for the results.
     * @param CacheItemPoolInterface $cache
     * @return ClientDecorator
     */
    private function setCache(CacheItemPoolInterface $cache): ClientDecorator
    {
        $this->cache = $cache;

        return $this;
    }

    /**
     * Sets the used client.
     * @param Client $client
     * @return ClientDecorator
     */
    private function setClient(Client $client): ClientDecorator
    {
        $this->client = $client;

        return $this;
    }

    /**
     * Sets the default response parser.
     * @param ResponseParserInterface $defaultResponseParser
     * @return ClientDecorator
     */
    private function setDefaultResponseParser(ResponseParserInterface $defaultResponseParser): ClientDecorator
    {
        $this->defaultResponseParser = $defaultResponseParser;

        return $this;
    }

    /**
     * Sets the event dispatcher.
     * @param EventDispatcherInterface $eventDispatcher
     * @return ClientDecorator
     */
    private function setEventDispatcher(EventDispatcherInterface $eventDispatcher): ClientDecorator
    {
        $this->eventDispatcher = $eventDispatcher;

        return $this;
    }
}
