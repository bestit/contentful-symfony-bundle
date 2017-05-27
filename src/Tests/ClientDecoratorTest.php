<?php

namespace BestIt\ContentfulBundle\Tests;

use BestIt\ContentfulBundle\ClientDecoratorAwareTrait;
use BestIt\ContentfulBundle\Service\Delivery\ClientDecorator;
use PHPUnit\Framework\TestCase;

/**
 * Class ClientDecoratorTest
 * @author blange <lange@bestit-online.de>
 * @category Tests
 * @package BestIt\ContentfulBundle
 * @version $id$
 */
class ClientDecoratorTest extends TestCase
{
    /**
     * The client decorator aware trait.
     * @var ClientDecoratorAwareTrait
     */
    private $fixture = null;

    /**
     * Sets up the test.
     */
    protected function setUp()
    {
        $this->fixture = static::getMockForTrait(ClientDecoratorAwareTrait::class);
    }

    /**
     * Checks the getter and setter.
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
