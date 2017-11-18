<?php

declare(strict_types=1);

namespace BestIt\ContentfulBundle\Routing;

use BestIt\ContentfulBundle\ClientDecoratorAwareTrait;
use BestIt\ContentfulBundle\Service\Delivery\ClientDecorator;
use Contentful\Delivery\Query;
use Contentful\Exception\NotFoundException;
use DomainException;
use Exception;
use Psr\Cache\CacheItemPoolInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Exception\ResourceNotFoundException;
use Symfony\Component\Routing\Exception\RouteNotFoundException;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Routing\Matcher\RequestMatcherInterface;
use Symfony\Component\Routing\RequestContext;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;
use function array_key_exists;
use function array_map;
use function array_walk;
use function current;
use function method_exists;
use function sha1;
use function strlen;

/**
 * "Router" to match against the contentful slugs.
 *
 * @author lange <lange@bestit-online.de>
 * @package BestIt\ContentfulBundle\Routing
 */
class ContentfulSlugMatcher implements RequestMatcherInterface, UrlGeneratorInterface
{
    use ClientDecoratorAwareTrait;
    use RoutableTypesAwareTrait;

    /**
     * @var string The used cache key for creating and tagging the route collection.
     */
    const COLLECTION_CACHE_KEY = 'route_collection';

    /**
     * @var string The prefix for the route-hash to create a cache key.
     */
    const ROUTE_CACHE_KEY_PREFIX = 'contentful-routing-';

    /**
     * @var CacheItemPoolInterface The possible cache class.
     */
    private $cache;

    /**
     * @var void|RouteCollection The loaded url collection.
     */
    protected $collection = null;

    /**
     * The request context.
     *
     * Filled by the setter.
     *
     * @var RequestContext|void
     */
    private $context;

    /**
     * @var string What is the id of the field with the controller info.
     */
    private $controllerField;

    /**
     * ContentfulSlugMatcher constructor.
     *
     * @param CacheItemPoolInterface $cache
     * @param ClientDecorator $client
     * @param string $controllerField
     * @param string $slugField
     */
    public function __construct(
        CacheItemPoolInterface $cache,
        ClientDecorator $client,
        string $controllerField,
        string $slugField
    ) {
        $this->cache = $cache;

        $this
            ->setClientDecorator($client)
            ->setControllerField($controllerField)
            ->setSlugField($slugField);
    }

    /**
     * Generates a URL or path for a specific route based on the given parameters.
     *
     * Parameters that reference placeholders in the route pattern will substitute them in the
     * path or host. Extra params are added as query string to the URL.
     *
     * When the passed reference type cannot be generated for the route because it requires a different
     * host or scheme than the current one, the method will return a more comprehensive reference
     * that includes the required params. For example, when you call this method with $referenceType = ABSOLUTE_PATH
     * but the route requires the https scheme whereas the current scheme is http, it will instead return an
     * ABSOLUTE_URL with the https scheme and the current host. This makes sure the generated URL matches
     * the route in any case.
     *
     * If there is no route with the given name, the generator must throw the RouteNotFoundException.
     *
     * @param string $name The name of the route
     * @param mixed $parameters An array of parameters
     * @param int $referenceType The type of reference to be generated (one of the constants)
     *
     * @return string The generated URL
     *
     * @throws RouteNotFoundException              If the named route doesn't exist
     * @throws MissingMandatoryParametersException When some parameters are missing that are mandatory for the route
     * @throws InvalidParameterException           When a parameter value for a placeholder is not correct because
     *                                             it does not match the requirement
     * @todo Still to dirty!
     */
    public function generate($name, $parameters = [], $referenceType = self::ABSOLUTE_PATH)
    {
        $collection = $this->getRouteCollection();

        if (!$foundRoute = $collection->get($name)) {
            throw new RouteNotFoundException();
        }

        return $foundRoute->getPath();
    }

    /**
     * Gets the request context.
     *
     * @return RequestContext The context
     */
    public function getContext(): RequestContext
    {
        return $this->context;
    }

    /**
     * Returns the id of the field with the logica name of the controller.
     *
     * @return string
     */
    public function getControllerField(): string
    {
        return $this->controllerField;
    }

    /**
     * Returns a matching entry for the request uri.
     *
     * @param string $requestUri
     * @return array|null
     * @todo Add Support for query. Add helper to create the cache key.
     */
    private function getMatchingEntry(string $requestUri)
    {
        if (strlen($requestUri) > 0 && $requestUri[0] !== '/') {
            $requestUri = '/' . $requestUri;
        }

        $cache = $this->cache;
        $cacheHit = $cache->getItem($cacheId = self::ROUTE_CACHE_KEY_PREFIX . sha1($requestUri));
        $entry = null;

        if (!$cacheHit->isHit()) {
            foreach ($this->getRoutableTypes() as $routableType) {
                $entries = $this->clientDecorator->getEntries(function (Query $query) use ($requestUri, $routableType) {
                    $query
                        ->setContentType($routableType)
                        ->setLimit(1)
                        ->where('fields.' . $this->getSlugField(), $requestUri);
                });

                if ($entries) {
                    $entry = current($entries);
                    break;
                }
            }

            if (method_exists($cacheHit, 'tag')) {
                $cacheHit->tag([$cacheId]);
            }

            $cache->save($cacheHit->set($entry));
        }

        return $cacheHit->get();
    }

    /**
     * Gets the RouteCollection instance associated with this Router.
     *
     * @return RouteCollection A RouteCollection instance
     */
    public function getRouteCollection(): RouteCollection
    {
        if (!$this->collection) {
            $this->loadRouteCollection();
        }

        return $this->collection;
    }

    /**
     * Returns the route name for the given contentful entry.
     *
     * @param array $entry
     * @return string
     */
    public function getRouteNameForEntry(array $entry)
    {
        return 'contentful_' . $entry['_contentType']->getId() . '_' . $entry['_id'];
    }

    /**
     * Loads the route collection.
     *
     * @return void
     * @todo Add a logger and log the exception. Add Cache-Tags or add this tag to every cache entry.
     */
    protected function loadRouteCollection()
    {
        $cache = $this->cache;
        $cacheHit = $cache->getItem(self::COLLECTION_CACHE_KEY);

        if ($cacheHit->isHit()) {
            $this->collection = $cacheHit->get();
        } else {
            $this->collection = new RouteCollection();

            array_map(function (string $routableType) {
                try {
                    $entries = $this->clientDecorator->getEntries(function (Query $query) use ($routableType) {
                        $query->setContentType($routableType);
                        $query->setLimit(1000);
                    });

                    array_walk($entries, function ($entry) {
                        $this->collection->add($this->getRouteNameForEntry($entry), new Route($entry[$this->slugField]));
                    });
                } catch (NotFoundException $clientException) {
                    // Do nothing at the moment with an error by the contentful sdk
                } catch (Exception $exception) {
                    throw $exception;
                }
            }, $this->getRoutableTypes());

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
     * @return array An array of parameters
     * @throws ResourceNotFoundException If no matching resource could be found
     * @todo ErrorManagement
     */
    public function matchRequest(Request $request): array
    {
        $requestUri = $request->getRequestUri();

        if ($requestUri !== '/') {
            if ($entry = $this->getMatchingEntry($requestUri)) {
                $controllerField = $this->getControllerField();

                if (!((array_key_exists($controllerField, $entry)) && ($controller = $entry[$controllerField]))) {
                    throw new DomainException('The found content does not provide a controller field.');
                }

                return [
                    '_controller' => $controller,
                    '_route' => $this->getRouteNameForEntry($entry),
                    'data' => $entry
                ];
            }
        }

        throw new ResourceNotFoundException('Contentful slugs did not match the request.');
    }

    /**
     * Sets the request context.
     *
     * @param RequestContext $context The context
     * @return ContentfulSlugMatcher
     */
    public function setContext(RequestContext $context): ContentfulSlugMatcher
    {
        $this->context = $context;

        return $this;
    }

    /**
     * Sets the id of the field with the logica name of the controller.
     *
     * @param string $controllerField
     * @return ContentfulSlugMatcher
     */
    protected function setControllerField(string $controllerField): ContentfulSlugMatcher
    {
        $this->controllerField = $controllerField;

        return $this;
    }
}
