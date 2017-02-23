<?php

namespace BestIt\ContentfulBundle\DependencyInjection\Compiler;

use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * Reads the content types which have the matching routable field and saves their ids as a parameter.
 * @author lange <lange@bestit-online.de>
 * @package BestIt\ContentfulBundle
 * @subpackage DependencyInjection\Compiler
 * @version $id$
 */
class RoutableTypesCompilerPass implements CompilerPassInterface
{
    /**
     * Process the containerbuilder.
     * @param ContainerBuilder $container
     * @return void
     */
    public function process(ContainerBuilder $container)
    {
        $allTypes = $container->getParameter('best_it_contentful.content_types');
        $routingFieldId = $container->getParameter('best_it_contentful.routing_field');

        $routableTypes = array_filter($allTypes, function (array $type) use ($routingFieldId) {
            return array_filter($type['fields'], function (string $fieldId) use ($routingFieldId) {
                return $fieldId === $routingFieldId;
            }, ARRAY_FILTER_USE_KEY);
        });

        $container->setParameter('best_it_contentful.routable_types', array_keys($routableTypes));
    }
}
