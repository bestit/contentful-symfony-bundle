<?php

namespace BestIt\ContentfulBundle;

use BestIt\ContentfulBundle\DependencyInjection\Compiler\RoutableTypesCompilerPass;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpKernel\Bundle\Bundle;

/**
 * Class BestItContentfulBundle
 *
 * @author lange <lange@bestit-online.de>
 * @package BestIt\ContentfulBundle
 */
class BestItContentfulBundle extends Bundle
{
    /**
     * Build dependency container
     *
     * @param ContainerBuilder $container
     */
    public function build(ContainerBuilder $container)
    {
        $container->addCompilerPass(new RoutableTypesCompilerPass());
    }
}
