<?php

declare(strict_types=1);

namespace BestIt\ContentfulBundle\Routing;

use BestIt\ContentfulBundle\Delivery\SimpleResponseParser;
use Contentful\Delivery\Resource\Entry;
use function ucfirst;

/**
 * A special parser for the routing which skips every child.
 *
 * @author blange <lange@bestit-online.de>
 * @package BestIt\ContentfulBundle\Routing
 */
class RouteCollectionResponseParser extends SimpleResponseParser
{
    use RoutableTypesAwareTrait;

    /**
     * RoutingResponseParser constructor.
     *
     * @param string $slugFieldName
     */
    public function __construct(string $slugFieldName)
    {
        $this->setSlugField($slugFieldName);
    }

    /**
     * Skips the childs and returns only the data which is needed for the routing of the direct requested entry.
     *
     * @param Entry $entry
     *
     * @return array
     */
    protected function resolveEntry(Entry $entry): array
    {
        foreach (['id', 'contentType'] as $key) {
            $return['_' . $key] = $entry->{'get' . ucfirst($key)}();
        }

        $return[$this->getSlugField()] = $entry->{'get' . ucfirst($this->getSlugField())}();

        return $return;
    }
}
