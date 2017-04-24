<?php

namespace BestIt\ContentfulBundle;

use BestIt\ContentfulBundle\Service\Delivery\ClientDecorator;

/**
 * Helps providing the client decorator.
 * @author blange <lange@bestit-online.de>
 * @package BestIt\ContentfulBundle
 * @version $id$
 */
trait ClientDecoratorAwareTrait
{
    /**
     * The client decorator.
     * @var ClientDecorator|null
     */
    private $clientDecorator = null;

    /**
     * Returns the client decorator.
     * @return ClientDecorator
     */
    public function getClientDecorator(): ClientDecorator
    {
        return $this->clientDecorator;
    }

    /**
     * Sets the client decorator.
     * @param ClientDecorator $clientDecorator
     * @return $this
     */
    public function setClientDecorator(ClientDecorator $clientDecorator)
    {
        $this->clientDecorator = $clientDecorator;
        return $this;
    }
}
