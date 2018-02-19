<?php

namespace BestIt\ContentfulBundle\Tests\DependencyInjection;

use BestIt\ContentfulBundle\Delivery\SimpleResponseParser;
use BestIt\ContentfulBundle\DependencyInjection\BestItContentfulExtension;
use BestIt\ContentfulBundle\Service\CacheResetService;
use BestIt\ContentfulBundle\Service\Delivery\ClientDecorator;
use BestIt\ContentfulBundle\Service\MarkdownParser;
use BestIt\ContentfulBundle\Twig\ContentfulExtension;
use BestIt\ContentfulBundle\Twig\MarkdownExtension;
use Matthias\SymfonyDependencyInjectionTest\PhpUnit\AbstractExtensionTestCase;
use Symfony\Component\Cache\Adapter\FilesystemAdapter;

/**
 * Class BestItContentfulExtensionTest
 * @author blange <lange@bestit-online.de>
 * @package BestIt\ContentfulBundle\Tests\DependencyInjection
 */
class BestItContentfulExtensionTest extends AbstractExtensionTestCase
{

    /**
     * Returns the container extensions to test.
     * @return BestItContentfulExtension[]
     */
    protected function getContainerExtensions(): array
    {
        return [new BestItContentfulExtension()];
    }

    /**
     * Returns assertions for checking declared services.
     * @return array
     */
    public function getDeclaredServices(): array
    {
        return [
            // Service id, optional class name, tag
            ['best_it_contentful.markdown.twig_extension', MarkdownExtension::class, 'twig.extension'],
            ['best_it_contentful.contentful.twig_extension', ContentfulExtension::class, 'twig.extension'],
            ['best_it_contentful.delivery.cache.reset_service', CacheResetService::class],
            ['best_it_contentful.delivery.client', ClientDecorator::class],
            ['best_it_contentful.markdown.parser', MarkdownParser::class],
            ['best_it_contentful.delivery.response_parser.default', SimpleResponseParser::class]
        ];
    }

    /**
     * Returns the minimal config.
     * @return array
     */
    protected function getMinimalConfiguration(): array
    {
        $this->registerService($cacheService = 'cache.' . uniqid(), FilesystemAdapter::class);

        return [
            'caching' => [
                'content' => $cacheService,
                'parameter_against_routing_cache' => 'foobar',
                'routing' => $cacheService,
            ]
        ];
    }

    /**
     * Sets up the test.
     * @return void
     */
    protected function setUp()
    {
        parent::setUp();

        $this->load();
    }

    /**
     * Checks if a declared service exists.
     * @dataProvider getDeclaredServices
     * @param string $serviceId
     * @param string $serviceClass
     * @param string $tag Should there be a tag.
     * @return void
     */
    public function testDeclaredServices(string $serviceId, string $serviceClass = '', string $tag = '')
    {
        $this->assertContainerBuilderHasService($serviceId, $serviceClass ?: null);

        if ($tag) {
            $this->assertContainerBuilderHasServiceDefinitionWithTag($serviceId, $tag);
        }
    }
}
