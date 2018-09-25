<?php

namespace BestIt\ContentfulBundle\DependencyInjection;

use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;
use Symfony\Component\HttpKernel\DependencyInjection\Extension;

/**
 * Loads the config for the contentful bundle.
 *
 * @author lange <lange@bestit-online.de>
 * @package BestIt\ContentfulBundle\DependencyInjection
 */
class BestItContentfulExtension extends Extension
{
    /**
     * Loads the bundle config.
     * @param array $configs
     * @param ContainerBuilder $container
     * @return void
     */
    public function load(array $configs, ContainerBuilder $container)
    {
        $loader = new YamlFileLoader($container, new FileLocator(__DIR__ . '/../Resources/config'));
        $loader->load('services.yml');

        $config = $this->processConfiguration(new Configuration(), $configs);

        $container->setParameter('best_it_contentful.content_types', $config['content_types'] ?? []);
        $container->setParameter('best_it_contentful.controller_field', $config['controller_field']);
        $container->setParameter('best_it_contentful.routing_field', $config['routing_field']);

        $container->setParameter(
            'best_it_contentful.collection_consumer',
            $config['caching']['collection_consumer'] ?? []
        );

        $container->setParameter(
            'best_it_contentful.complete_clear_on_webhook',
            $config['caching']['complete_clear_on_webhook'] ?? []
        );

        $container->setParameter(
            'best_it_contentful.cache.content.cache_ttl',
            $config['caching']['content']['cache_time']
        );

        $container->setParameter(
            'best_it_contentful.cache.routing.cache_ttl',
            $config['caching']['routing']['cache_time']
        );

        $container->setParameter(
            'best_it_contentful.cache.routing.parameter_against_routing_cache',
            $config['caching']['routing']['parameter_against_routing_cache']
        );

        $container->setAliases([
            'best_it_contentful.cache.pool.delivery' => $config['caching']['content']['service_id'],
            'best_it_contentful.cache.pool.routing' => $config['caching']['routing']['service_id'],
            'best_it_contentful.event_dispatcher' => $config['event_dispatcher'],
            'best_it_contentful.logger' => $config['logger'],
            'best_it_contentful.client' => $config['client']
        ]);
    }
}
