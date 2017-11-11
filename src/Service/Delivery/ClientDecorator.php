<?php

declare(strict_types=1);

namespace BestIt\ContentfulBundle\Service\Delivery;

use BestIt\ContentfulBundle\ClientEvents;
use BestIt\ContentfulBundle\Delivery\ResponseParserInterface;
use Contentful\Delivery\Asset;
use Contentful\Delivery\Client;
use Contentful\Delivery\ContentTypeField;
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
use Traversable;
use function array_filter;
use function array_merge;
use function array_walk;
use function get_class;
use function is_array;
use function method_exists;
use function sprintf;
use function ucfirst;

/**
 * Extends the logics for the contentful delivery.
 *
 * @author lange <lange@bestit-online.de>
 * @method Asset getAsset(string $id, string | null $locale = null)
 * @package BestIt\ContentfulBundle\Service
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
     * ClientDecorator constructor.
     *
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
        $this->cache = $cache;
        $this->client = $client;
        $this->defaultResponseParser = $responseParser;
        $this->eventDispatcher = $eventDispatcher;

        $this->setLogger($logger);
    }

    /**
     * Delegates to the original client.
     *
     * @param string $method
     * @param array $args
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
     * @return array
     */
    public function getEntries(
        callable $buildQuery,
        string $cacheId = '',
        ResponseParserInterface $parser = null
    ): array {
        $cache = $this->cache;
        $cacheItem = null;
        $cacheHit = false;
        $entries = null;
        $logger = $this->logger;
        $query = $this->getBaseQuery();

        $buildQuery($query);

        if ($cacheId) {
            $cacheItem = $cache->getItem($cacheId);

            if ($cacheHit = $cacheItem->isHit()) {
                $entries = $cacheItem->get();
            }
        }

        if (!$cacheHit || !$cacheId) {
            $dispatcher = $this->eventDispatcher;

            if (!$parser) {
                $parser = $this->defaultResponseParser;
            }

            try {
                $logger->debug(
                    'Loading contentful elements.',
                    ['cacheId' => $cacheId, 'parser' => get_class($parser)]
                );

                /** @var ResourceArray $entries */
                $entries = $this->client->getEntries($query);
                $entryIds = $this->getEntryIds($entries);

                $dispatcher->dispatch(ClientEvents::LOAD_CONTENTFUL_ENTRIES, new GenericEvent($entries));

                foreach ($entries as $entry) {
                    $dispatcher->dispatch(ClientEvents::LOAD_CONTENTFUL_ENTRY, new GenericEvent($entry));
                }

                $entries = $parser->toArray($entries);

                $logger->notice(
                    'Found contentful elements.',
                    ['cacheId' => $cacheId, 'entries' => $entries, 'parser' => get_class($parser)]
                );
            } catch (RequestException $exception) {
                $this->logger->critical(
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
     *
     * @param string $id
     * @param ResponseParserInterface|null $parser
     * @return array
     */
    public function getEntry(string $id, ResponseParserInterface $parser = null): array
    {
        $cache = $this->cache;
        $cacheItem = $cache->getItem($id);
        $entry = [];
        $logger = $this->logger;

        if (!$parser) {
            $parser = $this->defaultResponseParser;
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

                $entry = $this->client->getEntry($id);
                $entryIds = $this->getEntryIds($entry);

                $this->eventDispatcher->dispatch(ClientEvents::LOAD_CONTENTFUL_ENTRY, new GenericEvent($entry));

                $entry = $parser->toArray($entry);

                $logger->notice(
                    sprintf('Found contentful element with ID %s.', $id),
                    ['id' => $id, 'entry' => $entry, 'parser' => get_class($parser)]
                );

                if (array_filter($entryIds) && method_exists($cacheItem, 'tag')) {
                    $cacheItem->tag($entryIds);
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
     * Returns the entry id for the given contentful result.
     *
     * @param DynamicEntry|ResourceArray|array|mixed $contentfulResult The result for a contentful query.
     * @return array
     */
    private function getEntryIds($contentfulResult): array
    {
        $ids = [];

        if ($contentfulResult instanceof DynamicEntry) {
            $ids[] = $contentfulResult->getId();
            $fields = $contentfulResult->getContentType()->getFields() ?: [];

            array_walk($fields, function (ContentTypeField $field) use ($contentfulResult, &$ids) {
                $ids = array_merge($ids, $this->getEntryIds($contentfulResult->{'get' . ucfirst($field->getId())}()));
            });
        } else {
            if ((is_array($contentfulResult)) || ($contentfulResult instanceof Traversable)) {
                foreach ($contentfulResult as $entry) {
                    $ids = array_merge($ids, $this->getEntryIds($entry));
                }
            }
        }

        return array_unique($ids);
    }
}
