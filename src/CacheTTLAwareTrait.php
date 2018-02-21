<?php

declare(strict_types=1);

namespace BestIt\ContentfulBundle;

/**
 * Trait to help using the cache ttl.
 *
 * @author blange <lange@bestit-online.de>
 * @package BestIt\ContentfulBundle
 */
trait CacheTTLAwareTrait
{
    /**
     * @var int The used cache ttl.
     */
    private $cacheTTL = 0;

    /**
     * Returns the cache ttl.
     *
     * @return int
     */
    public function getCacheTTL(): int
    {
        return $this->cacheTTL;
    }

    /**
     * Sets the ttl for the cache.
     *
     * @param int $cacheTTL
     *
     * @return $this
     */
    public function setCacheTTL(int $cacheTTL): self
    {
        $this->cacheTTL = $cacheTTL;

        return $this;
    }
}
