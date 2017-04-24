<?php

namespace BestIt\ContentfulBundle\Tests\DependencyInjection;

use BestIt\ContentfulBundle\DependencyInjection\BestItContentfulExtension;
use Matthias\SymfonyDependencyInjectionTest\PhpUnit\AbstractExtensionTestCase;
use Symfony\Component\Cache\Adapter\FilesystemAdapter;

/**
 * Class BestItContentfulExtensionTest
 * @author blange <lange@bestit-online.de>
 * @category Tests
 * @package BestIt\ContentfulBundle
 * @subpackage DependencyInjection
 * @version $id$
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
     * Returns the minimal config.
     * @return array
     */
    protected function getMinimalConfiguration(): array
    {
        $this->registerService($cacheService = 'cache.' . uniqid(), FilesystemAdapter::class);

        return [
            'caching' => [
                'content' => $cacheService,
                'routing' => $cacheService,
            ]
        ];
    }

    /**
     * Sets up the test.
     */
    protected function setUp()
    {
        parent::setUp();

        $this->load();
    }

    /**
     * Checks if the service is registered correctly.
     * @return void
     */
    public function testServiceDeclarationCacheResetService()
    {
        static::assertContainerBuilderHasService('best_it_contentful.delivery.cache.reset_service');
    }

    /**
     * Checks if the service is declared correctly.
     * @return void
     */
    public function testServiceDeclarationClientDecorator()
    {
        static::assertContainerBuilderHasService('best_it_contentful.delivery.client');
    }

    /**
     * Checks if the service is registered correctly.
     * @return void
     */
    public function testServiceDeclarationMarkdownParser()
    {
        static::assertContainerBuilderHasService('best_it_contentful.markdown.parser');
    }
}
