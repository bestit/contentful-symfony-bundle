<?php

declare(strict_types=1);

namespace BestIt\ContentfulBundle\Tests;

use BestIt\ContentfulBundle\CacheTagsGetterTrait;
use Contentful\Delivery\Resource\Entry;
use Traversable;

/**
 * Helps you testing the trait.
 *
 * @author b3nl <code@b3nl.de>
 * @package BestIt\ContentfulBundle\Tests
 */
class CacheTagsGetterStub
{
    use CacheTagsGetterTrait;

    /**
     * Makes the gtter public.
     *
     * @param Entry|Traversable|mixed $contentfulResult
     *
     * @return array
     */
    public function getCacheTagsPublic($contentfulResult): array
    {
        return $this->getCacheTags($contentfulResult);
    }

    /**
     * Sets the default tags.
     *
     * @param array $defaultTags
     *
     * @return $this
     */
    public function setDefaultTags(array $defaultTags): self
    {
        $this->defaultTags = $defaultTags;

        return $this;
    }
}
