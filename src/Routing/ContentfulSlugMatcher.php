<?php

namespace BestIt\ContentfulBundle\Routing;

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

/**
 * "Router" to match against the contentful slugs.
 * @author lange <lange@bestit-online.de>
 * @package BestIt\ContentfulBundle\Routing
 * @version $id$
 */
class ContentfulSlugMatcher implements RequestMatcherInterface, UrlGeneratorInterface
{
    /**
     * The possible cache class.
     * @var CacheItemPoolInterface
     */
    private $cache;

    /**
     * The contentful client.
     * @var ClientDecorator
     */
    private $client;

    /**
     * The loaded url collection.
     * @var void|RouteCollection
     */
    protected $collection = null;

    /**
     * The request context.
     *
     * Filled by the setter.
     * @var RequestContext|void
     */
    private $context;

    /**
     * What is the id of the field with the controller info.
     * @var string
     */
    private $controllerField;

    /**
     * The ids of the routable types.
     * @var array
     */
    private $routableTypes = [];

    /**
     * The name of the field with the slug.
     * @var string
     */
    private $slugField;

    /**
     * ContentfulSlugMatcher constructor.
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
        $this
            ->setCache($cache)
            ->setClient($client)
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
     * Returns the cache if there is one.
     * @return void|CacheItemPoolInterface
     */
    protected function getCache()
    {
        return $this->cache;
    }

    /**
     * Returns the client.
     * @return ClientDecorator
     */
    public function getClient(): ClientDecorator
    {
        return $this->client;
    }

    /**
     * Gets the request context.
     * @return RequestContext The context
     */
    public function getContext(): RequestContext
    {
        return $this->context;
    }

    /**
     * Returns the id of the field with the logica name of the controller.
     * @return string
     */
    public function getControllerField(): string
    {
        return $this->controllerField;
    }

    /**
     * Returns a matching entry for the request uri.
     * @param string $requestUri
     * @return array|void
     */
    protected function getMatchingEntry(string $requestUri)
    {
        if (strlen($requestUri) > 0 && $requestUri[0] !== '/') {
            $requestUri = '/' . $requestUri;
        }

        $cache = $this->getCache();
        $cacheHit = $cache->getItem($cacheId = sha1($requestUri));

        if (!$cacheHit->isHit()) {
            $entry = null;

            foreach ($this->getRoutableTypes() as $routableType) {
                $entries = $this->client->getEntries(function (Query $query) use ($requestUri, $routableType) {
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

            if ($entry) {
                $cache->save($cacheHit->set($entry));
            }
        }

        return $cacheHit->get();
    }

    /**
     * Returns the ids of the routable types.
     * @return array
     */
    public function getRoutableTypes(): array
    {
        return $this->routableTypes;
    }

    /**
     * Gets the RouteCollection instance associated with this Router.
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
     * @param array $entry
     * @return string
     */
    public function getRouteNameForEntry(array $entry)
    {
        return 'contentful_' . $entry['_contentType']->getId() . '_' . $entry['_id'];
    }

    /**
     * Returns the name of the slug field.
     * @return string
     */
    public function getSlugField(): string
    {
        return $this->slugField;
    }

    /**
     * Loads the route collection.
     * @return void
     * @todo Add a logger and log the exception.
     */
    protected function loadRouteCollection()
    {
        $cache = $this->getCache();
        $cacheHit = $cache->getItem('route_collection');

        if ($cacheHit->isHit()) {
            $this->collection = $cacheHit->get();
        } else {
            $this->collection = new RouteCollection();

            array_map(function (string $routableType) {
                try {
                    $entries = $this->client->getEntries(function (Query $query) use ($routableType) {
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
     * Sets the possible cache for the results.
     * @param CacheItemPoolInterface $cache
     * @return ContentfulSlugMatcher
     */
    protected function setCache(CacheItemPoolInterface $cache): ContentfulSlugMatcher
    {
        $this->cache = $cache;

        return $this;
    }

    /**
     * Sets the client.
     * @param ClientDecorator $client
     * @return ContentfulSlugMatcher
     */
    protected function setClient(ClientDecorator $client): ContentfulSlugMatcher
    {
        $this->client = $client;

        return $this;
    }

    /**
     * Sets the request context.
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
     * @param string $controllerField
     * @return ContentfulSlugMatcher
     */
    protected function setControllerField(string $controllerField): ContentfulSlugMatcher
    {
        $this->controllerField = $controllerField;

        return $this;
    }

    /**
     * Sets the ids of the routable types.
     * @param array $routableTypes
     * @return ContentfulSlugMatcher
     */
    public function setRoutableTypes(array $routableTypes): ContentfulSlugMatcher
    {
        $this->routableTypes = $routableTypes;

        return $this;
    }

    /**
     * Sets the name of the slug field.
     * @param string $slugField
     * @return ContentfulSlugMatcher
     */
    protected function setSlugField(string $slugField): ContentfulSlugMatcher
    {
        $this->slugField = $slugField;

        return $this;
    }
}
