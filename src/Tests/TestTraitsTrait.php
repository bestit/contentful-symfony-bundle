<?php

declare(strict_types=1);

namespace BestIt\ContentfulBundle\Tests;

/**
 * Makes it easier to test traits.
 *
 * @author blange <lange@bestit-online.de>
 * @package BestIt\ContentfulBundle\Tests
 */
trait TestTraitsTrait
{
    /**
     * @var null|mixed The tested class.
     */
    protected $fixture;

    /**
     * Asserts that a haystack contains a needle.
     *
     * @param mixed $needle
     * @param mixed $haystack
     * @param string $message
     * @param bool $ignoreCase
     * @param bool $checkForObjectIdentity
     * @param bool $checkForNonObjectIdentity
     *
     * @return void
     */
    abstract public static function assertContains(
        $needle,
        $haystack,
        $message = '',
        $ignoreCase = false,
        $checkForObjectIdentity = true,
        $checkForNonObjectIdentity = false
    );

    /**
     * Checks if the trait is used.
     *
     * @param string $traitName
     * @param mixed $object
     *
     * @return mixed
     */
    static public function assertTraitUsage(string $traitName, $object)
    {
        return static::assertContains($traitName, class_uses($object));
    }

    /**
     * Returns the names of the used traits.
     *
     * @return array
     */
    abstract protected function getUsedTraitNames(): array;

    /**
     * Checks the used traits.
     *
     * @return void
     */
    public function testUsedTraits()
    {
        array_map(
            function (string $traitName) {
                static::assertTraitUsage($traitName, $this->fixture);
            },
            $this->getUsedTraitNames()
        );
    }
}
