<?php

declare(strict_types=1);

namespace BestIt\ContentfulBundle\Routing;

use BestIt\ContentfulBundle\CacheTTLAwareTrait;
use BestIt\ContentfulBundle\Delivery\ResponseParserInterface;
use Contentful\Delivery\Client;
use Psr\Cache\CacheItemPoolInterface;
use Psr\Cache\InvalidArgumentException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Exception\ResourceNotFoundException;

/**
 * Child of the contentful slug matcher that implements caching
 *
 * @author André Varelmann <andre.varelmann@bestit-online.de>
 * @package BestIt\ContentfulBundle\Routing
 */
class CachingContentfulSlugMatcher extends ContentfulSlugMatcher
{
    use CacheTTLAwareTrait;

    /**
     * @var string The used cache key for creating and tagging the route collection.
     */
    const COLLECTION_CACHE_KEY = 'route_collection';

    /**
     * @var string If the value of this parameter is true, then the cache is ignored.
     */
    private $ignoreCacheKey;

    /**
     * @var bool Is the routing cache ignored?
     */
    private $isCacheIgnored = false;

    /**
     * @var CacheItemPoolInterface The possible cache class.
     */
    private $cache;

    /**
     * @var ResponseParserInterface $simpleResponseParser
     */
    private $simpleResponseParser;

    /**
     * ContentfulSlugMatcherDecorator constructor.
     *
     * @param CacheItemPoolInterface $cache
     * @param Client $client
     * @param string $controllerField
     * @param string $slugField
     * @param ResponseParserInterface $routeCollectionResponseParser
     * @param ResponseParserInterface $simpleResponseParser
     * @param string $ignoreCacheKey
     * @param int $includeLevelForMatching
     */
    public function __construct(
        CacheItemPoolInterface $cache,
        Client $client,
        string $controllerField,
        string $slugField,
        ResponseParserInterface $routeCollectionResponseParser,
        ResponseParserInterface $simpleResponseParser,
        string $ignoreCacheKey = '',
        int $includeLevelForMatching = 10
    ) {
        parent::__construct(
            $client,
            $controllerField,
            $slugField,
            $routeCollectionResponseParser,
            $includeLevelForMatching
        );

        $this->cache = $cache;
        $this->ignoreCacheKey = $ignoreCacheKey;
        $this->simpleResponseParser = $simpleResponseParser;
    }

    /**
     * @param string $requestUri
     *
     * @throws InvalidArgumentException
     * @throws ResourceNotFoundException If no matching resource could be found
     *
     * @return array|mixed|null
     */
    protected function getMatchingEntry(string $requestUri)
    {
        $cache = $this->cache;
        $cacheHit = $cache->getItem($this->getRoutingCacheId($requestUri));

        $entry = null;

        if ($this->isCacheIgnored || !$cacheHit->isHit()) {
            if (!$entry = parent::getMatchingEntry($requestUri)) {
                throw new ResourceNotFoundException('Contentful slugs did not match the request.');
            }

            $cacheHit->set($this->simpleResponseParser->toArray($entry));

            if ($cacheTTL = $this->getCacheTTL()) {
                $cacheHit->expiresAfter($cacheTTL);
            }

            if (!$this->isCacheIgnored) {
                if (method_exists($cacheHit, 'tag')) {
                    $cacheHit->tag($this->getCacheTags($entry));
                }
                $cache->save($cacheHit);
            }
        }

        return $cacheHit->get();
    }

    /**
     * Loads the route collection.
     *
     * @throws InvalidArgumentException
     * @todo Add a logger and log the exception.
     *
     * @return void
     */
    protected function loadRouteCollection()
    {
        $cache = $this->cache;
        $cacheHit = $cache->getItem(self::COLLECTION_CACHE_KEY);

        if ($cacheHit->isHit()) {
            $this->collection = $cacheHit->get();
        } else {
            parent::loadRouteCollection();

            if (method_exists($cacheHit, 'tag')) {
                // It is easier to add this tag to every entry, then to try to fetch every id, for every possible
                // contentful entry.
                $cacheHit->tag(self::COLLECTION_CACHE_KEY);
            }

            $cache->save($cacheHit->set($this->collection));
        }
    }

    /**
     * Tries to match a request with a set of routes.
     *
     * If the matcher can not find information, it must throw one of the exceptions documented below.
     *
     * @param Request $request The request to match
     * @throws ResourceNotFoundException If no matching resource could be found
     * @todo ErrorManagement
     *
     * @return array An array of parameters
     */
    public function matchRequest(Request $request): array
    {
        $this->isCacheIgnored = $this->ignoreCacheKey && (bool) $request->get($this->ignoreCacheKey, false);

        return parent::matchRequest($request);
    }
}
