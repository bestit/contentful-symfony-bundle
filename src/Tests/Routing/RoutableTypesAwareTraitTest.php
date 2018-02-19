<?php

declare(strict_types=1);

namespace BestIt\ContentfulBundle\Tests\Routing;

use BestIt\ContentfulBundle\Routing\RoutableTypesAwareTrait;
use PHPUnit\Framework\TestCase;
use PHPUnit_Framework_MockObject_MockObject;

/**
 * Class RoutableTypesAwareTraitTest
 *
 * @author b3nl <code@b3nl.de>
 * @package BestIt\ContentfulBundle\Tests\Routing
 */
class RoutableTypesAwareTraitTest extends TestCase
{
    /**
     * @var RoutableTypesAwareTrait|void|PHPUnit_Framework_MockObject_MockObject
     */
    private $fixture;

    /**
     * Sets up the test.
     *
     * @return void
     */
    protected function setUp()
    {
        $this->fixture = $this->getMockForTrait(RoutableTypesAwareTrait::class);
    }

    /**
     * Checks if the getter and setter change the routable types.
     *
     * @return void
     */
    public function testGetAndSetRoutableTypes()
    {
        static::assertSame([], $this->fixture->getRoutableTypes(), 'Wrong default return.');
        static::assertSame($this->fixture, $this->fixture->setRoutableTypes($types = [uniqid()]), 'Fluent broken.');
        static::assertSame($types, $this->fixture->getRoutableTypes(), 'Not persisted.');
    }

    /**
     * Checks if the getter and setter change the slug field.
     *
     * @return void
     */
    public function testGetAndSetSlugField()
    {
        static::assertSame('', $this->fixture->getSlugField(), 'Wrong default return.');
        static::assertSame($this->fixture, $this->fixture->setSlugField($field = uniqid()), 'Fluent broken.');
        static::assertSame($field, $this->fixture->getSlugField(), 'Not persisted.');
    }
}
