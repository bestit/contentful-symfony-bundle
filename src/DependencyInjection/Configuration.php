<?php

declare(strict_types=1);

namespace BestIt\ContentfulBundle\DependencyInjection;

use Symfony\Component\Config\Definition\Builder\ArrayNodeDefinition;
use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

/**
 * Configuration class for this bundle.
 *
 * @author blange <lange@bestit-online.de>
 * @package BestIt\ContentfulBundle\DependencyInjection
 */
class Configuration implements ConfigurationInterface
{
    /**
     * Returns the node to config the validation of an contentful element.
     *
     * @return ArrayNodeDefinition
     */
    protected function getValidationConfig(): ArrayNodeDefinition
    {
        $node = (new TreeBuilder())->root('validations');

        $node->prototype('array')
            ->children()
                ->arrayNode('size')
                    ->children()
                        ->integerNode('max')->end()
                        ->integerNode('min')->end()
                    ->end()
                ->end()
                ->arrayNode('regexp')
                    ->children()
                        ->scalarNode('pattern')->end()
                        ->scalarNode('flags')->end()
                    ->end()
                ->end()
                ->arrayNode('in')
                    ->prototype('scalar')->end()
                ->end()
                ->arrayNode('linkContentType')
                    ->prototype('scalar')->end()
                ->end()
                ->booleanNode('unique')->end()
            ->end()
        ->end();

        return $node;
    }

    /**
     * Adds the caching types to the contentful config.
     *
     * @return ArrayNodeDefinition
     */
    protected function getCachingConfig(): ArrayNodeDefinition
    {
        $node = (new TreeBuilder())->root('caching');

        $node
            ->isRequired()
            ->children()
                ->arrayNode('content')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->scalarNode('service_id')
                            ->info('Please provider your service id for caching contentful contents.')
                            ->isRequired()
                            ->cannotBeEmpty()
                        ->end()
                        ->integerNode('cache_time')
                            ->defaultValue(0)
                            ->info('Please provide the ttl for your content cache in seconds. 0 means forever.')
                        ->end()
                    ->end()
                ->end()
                ->arrayNode('routing')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->scalarNode('service_id')
                            ->info('Please provider your service id for caching contentful routings.')
                            ->isRequired()
                            ->cannotBeEmpty()
                        ->end()
                        ->integerNode('cache_time')
                            ->defaultValue(0)
                            ->info('Please provide the ttl for your routing cache in seconds. 0 means forever.')
                        ->end()
                        ->scalarNode('parameter_against_routing_cache')
                            ->defaultValue('ignore-contentful-routing-cache')
                            ->info(
                                'If the requested url contains this query parameter, the routing cache will be ignored.'
                            )
                        ->end()
                    ->end()
                ->end()
                ->booleanNode('complete_clear_on_webhook')
                    ->info('Should the whole contentful cache be cleared every time on an entry reset request?')
                    ->defaultValue(false)
                ->end()
                ->arrayNode('collection_consumer')
                    ->info('Which cache ids should be resetted everytime?')
                    ->prototype('scalar')
                ->end()
            ->end();

        $node->end();

        return $node;
    }

    /**
     * Parses the config.
     *
     * @return TreeBuilder
     */
    public function getConfigTreeBuilder()
    {
        $builder = new TreeBuilder();

        $builder->root('best_it_contentful')
            ->children()
                ->scalarNode('controller_field')
                    ->info('This field indicates which controller should be used for the routing.')
                    ->defaultValue('controller')
                    ->cannotBeEmpty()
                ->end()
                ->scalarNode('routing_field')
                    ->info('Which field is used to mark the url of the contentful entry?')
                    ->defaultValue('slug')
                    ->cannotBeEmpty()
                ->end()
                ->arrayNode('routable_types')
                    ->info('This content types have a routable page in the project.')
                    ->prototype('scalar')
                    ->end()
                ->end()
                ->append($this->getCachingConfig())
                ->append($this->getContentTypes())
            ->end();

        return $builder;
    }

    /**
     * Adds the content types to the contentful config.
     *
     * @return ArrayNodeDefinition
     *
     * @todo https://www.contentful.com/developers/docs/references/content-management-api/#/reference/content-types
     * @todo https://www.contentful.com/developers/docs/references/content-management-api/#/reference/editor-interface
     * @todo Not Every Field Type, Content Type, Asset, Editor Interface etc. is tested.
     */
    protected function getContentTypes(): ArrayNodeDefinition
    {
        $node = (new TreeBuilder())->root('content_types');

        $node
            ->info(
                'Add the content types mainly documented under: ' .
                '<https://www.contentful.com/developers/docs/references/content-management-api/#/reference/' .
                'content-types>'
            )
            ->normalizeKeys(false)
            ->requiresAtLeastOneElement()
            ->useAttributeAsKey('id')
            ->prototype('array')
                ->children()
                    ->scalarNode('description')->isRequired()->end()
                    ->scalarNode('displayField')->isRequired()->end()
                    ->scalarNode('name')->isRequired()->end()
                    ->scalarNode('controller')
                        ->info(
                            'Give the logical controller name for the routing, like document under <' .
                            'http://symfony.com/doc/current/routing.html#controller-string-syntax>'
                        )
                    ->end()
                    ->arrayNode('fields')
                        ->isRequired()
                        ->requiresAtLeastOneElement()
                        ->normalizeKeys(true)
                        ->useAttributeAsKey('id')
                        ->prototype('array')
                            ->children()
                                ->enumNode('linkType')->values(['Asset', 'Entry'])->end()
                                ->scalarNode('name')->isRequired()->end()
                                ->booleanNode('omitted')->defaultValue(false)->end()
                                ->booleanNode('required')->defaultValue(false)->end()
                                ->arrayNode('items')
                                    ->children()
                                        ->enumNode('type')->values(['Link', 'Symbol'])->end()
                                        ->enumNode('linkType')->values(['Asset', 'Entry'])->end()
                                        ->append($this->getValidationConfig())
                                    ->end()
                                ->end()
                                ->enumNode('type')
                                    ->isRequired()
                                    ->values([
                                        'Array',
                                        'Boolean',
                                        'Date',
                                        'Integer',
                                        'Location',
                                        'Link',
                                        'Number',
                                        'Object',
                                        'Symbol',
                                        'Text'
                                    ])
                                ->end()
                                // region control
                                ->arrayNode('control')
                                    ->info(
                                        'Shortcut to handle the editor interface for this field, documentation ' .
                                        'can be found here: <https://www.contentful.com/developers/docs/' .
                                        'references/content-management-api/#/reference/editor-interface>'
                                    )
                                    ->isRequired()
                                    ->children()
                                        ->enumNode('id')
                                            ->isRequired()
                                            ->values([
                                                'assetLinkEditor',
                                                'assetLinksEditor',
                                                'assetGalleryEditor',
                                                'boolean',
                                                'datePicker',
                                                'entryLinkEditor',
                                                'entryLinksEditor',
                                                'entryCardEditor',
                                                'entryCardsEditor',
                                                'numberEditor',
                                                'rating',
                                                'locationEditor',
                                                'objectEditor',
                                                'urlEditor',
                                                'slugEditor',
                                                'ooyalaEditor',
                                                'kalturaEditor',
                                                'kalturaMultiVideoEditor',
                                                'listInput',
                                                'checkbox',
                                                'tagEditor',
                                                'multipleLine',
                                                'markdown',
                                                'singleLine',
                                                'dropdown',
                                                'radio'
                                            ])
                                        ->end()
                                        ->arrayNode('settings')
                                            ->isRequired()
                                            ->children()
                                                ->integerNode('ampm')->end()
                                                ->scalarNode('falseLabel')->end()
                                                ->scalarNode('format')->end()
                                                ->scalarNode('helpText')->isRequired()->end()
                                                ->integerNode('stars')->end()
                                                ->scalarNode('trueLabel')->end()
                                            ->end()
                                        ->end()
                                    ->end()
                                ->end()
                                // endregion
                                ->append($this->getValidationConfig())
                            ->end()
                         ->end()
                    ->end()
                ->end()
            ->end()
        ->end();

        return $node;
    }
}
