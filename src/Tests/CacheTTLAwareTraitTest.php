<?php

declare(strict_types=1);

namespace BestIt\ContentfulBundle;

use PHPUnit\Framework\TestCase;
use function mt_rand;

/**
 * Class CacheTTLAwareTraitTest
 *
 * @author blange <lange@bestit-online.de>
 * @package BestIt\ContentfulBundle
 */
class CacheTTLAwareTraitTest extends TestCase
{
    /**
     * @var CacheTTLAwareTrait|void The tested class.
     */
    private $fixture;

    /**
     * Sets up the test.
     *
     * @return void
     */
    protected function setUp()
    {
        $this->fixture = $this->getMockForTrait(CacheTTLAwareTrait::class);
    }

    /**
     * Returns the cache ttl default.
     *
     * @return void
     */
    public function testGetCacheTTLDefault()
    {
        static::assertSame(0, $this->fixture->getCacheTTL());
    }

    /**
     * Checks the setter of the ttl.
     *
     * @return void
     */
    public function testSetAndGetCacheTTL()
    {
        static::assertSame($this->fixture, $this->fixture->setCacheTTL($ttl = mt_rand(1, 10000)));
        static::assertSame($ttl, $this->fixture->getCacheTTL());
    }
}
