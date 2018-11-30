<?php

declare(strict_types=1);

namespace BestIt\ContentfulBundle;

use BestIt\ContentfulBundle\Service\Delivery\ClientDecorator;

/**
 * Helps providing the client decorator.
 *
 * @author blange <lange@bestit-online.de>
 * @package BestIt\ContentfulBundle
 */
trait ClientDecoratorAwareTrait
{
    /**
     * @var ClientDecorator|null The client decorator.
     */
    protected $clientDecorator = null;

    /**
     * Returns the client decorator in a type safe way.
     *
     * @return ClientDecorator
     */
    public function getClientDecorator(): ClientDecorator
    {
        return $this->clientDecorator;
    }

    /**
     * Sets the client decorator.
     *
     * @param ClientDecorator $clientDecorator
     *
     * @return $this
     */
    public function setClientDecorator(ClientDecorator $clientDecorator): self
    {
        $this->clientDecorator = $clientDecorator;

        return $this;
    }
}
