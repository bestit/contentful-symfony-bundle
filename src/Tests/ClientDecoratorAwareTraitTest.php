<?php

declare(strict_types=1);

namespace BestIt\ContentfulBundle\Tests;

use BestIt\ContentfulBundle\ClientDecoratorAwareTrait;
use BestIt\ContentfulBundle\Service\Delivery\ClientDecorator;
use PHPUnit\Framework\TestCase;

/**
 * Class ClientDecoratorTest.
 *
 * @author blange <lange@bestit-online.de>
 * @package BestIt\ContentfulBundle\Tests
 */
class ClientDecoratorAwareTraitTest extends TestCase
{
    /**
     * @var ClientDecoratorAwareTrait|null The client decorator aware trait.
     */
    private $fixture = null;

    /**
     * Sets up the test.
     *
     * @reteurn void
     */
    protected function setUp()
    {
        $this->fixture = static::getMockForTrait(ClientDecoratorAwareTrait::class);
    }

    /**
     * Checks the getter and setter.
     *
     * @return void
     */
    public function testGetAndSetClientDecorator()
    {
        static::assertSame(
            $this->fixture,
            $this->fixture->setClientDecorator($mock = $this->createMock(ClientDecorator::class)),
            'Fluent interface broken.'
        );

        static::assertSame($mock, $this->fixture->getClientDecorator(), 'Value not persisted.');
    }
}
