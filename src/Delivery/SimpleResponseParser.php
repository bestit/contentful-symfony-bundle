<?php

namespace BestIt\ContentfulBundle\Delivery;

use Contentful\Delivery\Asset;
use Contentful\Delivery\ContentTypeField;
use Contentful\Delivery\DynamicEntry;
use Contentful\ResourceArray;

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
class SimpleResponseParser implements ResponseParserInterface
{
    /**
     * Reads the values from an entry (and resolves its links) recursively.
     *
     * @param DynamicEntry $entry
     *
     * @return array
     */
    protected function resolveEntry(DynamicEntry $entry): array
    {
        foreach (['id', 'createdAt', 'locale', 'revision', 'space', 'contentType', 'updatedAt'] as $key) {
            $return['_' . $key] = $entry->{'get' . ucfirst($key)}();
        }

        $fields = $entry->getContentType()->getFields();
        $return += array_map(function (ContentTypeField $field) use ($entry) {
            $entryValue = $entry->{'get' . ucfirst($field->getId())}();

            if (is_array($entryValue)) {
                $entryValue = $this->toArray($entryValue);
            } else {
                if ($entryValue instanceof Asset) {
                    /** @var Asset $entryValue */
                    $file = $entryValue->getFile();

                    $entryValue = $file ? $file->getUrl() : '';
                } else {
                    if ($entryValue instanceof DynamicEntry) {
                        $entryValue = $this->resolveEntry($entryValue);
                    }
                }
            }

            return $entryValue;
        }, $fields);

        return array_filter($return, function ($value) {
            return (is_string($value) && $value !== '') || is_bool($value) || !is_scalar($value) || is_numeric($value);
        });
    }

    /**
     * Makes a simple array out of the response to cache it and make it more independent.
     *
     * @param DynamicEntry|ResourceArray|array $result
     *
     * @return array
     */
    public function toArray($result): array
    {
        $response = [];
        $isEntry = $result instanceof DynamicEntry;

        /** @var DynamicEntry $entry */
        foreach ($isEntry ? [$result] : $result as $key => $entry) {
            $response[$key] = is_scalar($entry) ? $entry : $this->resolveEntry($entry);
        }

        return $isEntry ? $response[0] : $response;
    }
}
