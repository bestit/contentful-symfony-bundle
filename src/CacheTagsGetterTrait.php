<?php

declare(strict_types=1);

namespace BestIt\ContentfulBundle;

use BestIt\ContentfulBundle\Routing\RoutableTypesAwareTrait;
use Contentful\Delivery\ContentTypeField;
use Contentful\Delivery\DynamicEntry;
use Contentful\ResourceArray;
use Exception;
use Psr\Log\LoggerInterface;
use Traversable;
use function array_filter;
use function array_merge;
use function array_unique;
use function array_walk;
use function in_array;
use function md5;
use function ucfirst;

/**
 * Helps you providing every tag you need to correctly invalidate a cache entry.
 *
 * @author blange <lange@bestit-online.de>
 * @package BestIt\ContentfulBundle
 */
trait CacheTagsGetterTrait
{
    use RoutableTypesAwareTrait;

    /**
     * @var array Default tags for the entries.
     */
    protected $defaultTags = [];

    /**
     * @var LoggerInterface The optional used logger
     */
    protected $logger;

    /**
     * Returns the cache keys for every entry.
     *
     * This method adds the default tags to every tag collection.
     *
     * @param DynamicEntry|ResourceArray|array|mixed $contentfulResult The result for a contentful query.
     *
     * @return array
     */
    protected function getCacheTags($contentfulResult): array
    {
        $tags = $this->getDefaultTags();

        if ($contentfulResult instanceof DynamicEntry) {
            $tags[] = $contentfulResult->getId();
            $contentType = $contentfulResult->getContentType();
            $fields = $contentType->getFields() ?: [];

            if ((in_array($contentType->getId(), $this->getRoutableTypes()) &&
                ($slugFieldName = $this->getSlugField()) &&
                ($slugField = $contentfulResult->{'get' . ucfirst($slugFieldName)}()))) {
                $tags[] = $this->getRoutingCacheId($slugField);
            }

            foreach ($fields as $field) {
                $childTags = null;
                try {
                    $entryValue = $contentfulResult->{'get' . ucfirst($field->getId())}();
                } catch (Exception $e) {
                    if ($this->logger !== null) {
                        $this->logger->error(
                            'Error at resolving field in cache tag resolver',
                            [
                                'exception' => $e,
                                'field' => $field->getId(),
                                'entry' => $contentfulResult->getId()
                            ]
                        );
                    }
                }

                $childTags = $this->getCacheTags($entryValue);

                $tags = array_merge(
                    $tags,
                    $childTags ?? []
                );
            }
        } else {
            if ((is_array($contentfulResult)) || ($contentfulResult instanceof Traversable)) {
                foreach ($contentfulResult as $entry) {
                    $tags = array_merge($tags, $this->getCacheTags($entry));
                }
            }
        }

        return array_filter(array_unique($tags));
    }

    /**
     * Returns the default tags for the entries.
     *
     * @return array
     */
    protected function getDefaultTags(): array
    {
        return $this->defaultTags;
    }

    /**
     * Returns the caching id for the direct routing match.
     *
     * @param mixed $slugField
     *
     * @return string
     */
    private function getRoutingCacheId($slugField): string
    {
        return md5($slugField) . '-contentful-routing';
    }
}
