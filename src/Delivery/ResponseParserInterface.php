<?php

namespace BestIt\ContentfulBundle\Delivery;

use Contentful\Core\Resource\ResourceArray;
use Contentful\Delivery\Resource\Entry;

/**
 * Simplifies the delivery response to get it cached.
 *
 * There is no High level api to get the raw data to write it to a persistent cache and simple serializing of the
 * response objects does not work because of the nested objects in the response.
 *
 * @author lange <lange@bestit-online.de>
 * @package BestIt\ContentfulBundle\Delivery
 * @subpackage Delivery
 */
interface ResponseParserInterface
{
    /**
     * Makes a simple array out of the response to cache it and make it more independent.
     *
     * @param Entry|ResourceArray|array $result
     *
     * @return array
     */
    public function toArray($result): array;
}
