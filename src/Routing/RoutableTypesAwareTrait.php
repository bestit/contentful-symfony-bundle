<?php

declare(strict_types=1);

namespace BestIt\ContentfulBundle\Routing;

/**
 * Helps you to inject the content type name which are routable.
 *
 * @author blange <bjoern.lange@bestit-online.de>
 * @package BestIt\ContentfulBundle\Routing
 */
trait RoutableTypesAwareTrait
{
    /**
     * @var array The ids of the routable types.
     */
    private $routableTypes = [];

    /**
     * @var string The name of the field with the slug.
     */
    private $slugField = '';

    /**
     * Returns the ids of the routable types.
     *
     * @return array
     */
    public function getRoutableTypes(): array
    {
        return $this->routableTypes;
    }

    /**
     * Returns the name of the slug field.
     *
     * @return string
     */
    public function getSlugField(): string
    {
        return $this->slugField;
    }

    /**
     * Sets the ids of the routable types.
     *
     * @param array $routableTypes
     *
     * @return $this
     */
    public function setRoutableTypes(array $routableTypes): self
    {
        $this->routableTypes = $routableTypes;

        return $this;
    }

    /**
     * Sets the name of the slug field.
     *
     * @param string $slugField
     *
     * @return $this
     */
    public function setSlugField(string $slugField): self
    {
        $this->slugField = $slugField;

        return $this;
    }
}
