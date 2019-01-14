<?php

declare(strict_types=1);

namespace BestIt\ContentfulBundle\Tests;

use BestIt\ContentfulBundle\CacheTagsGetterTrait;
use Contentful\Core\Resource\ResourceArray;
use Contentful\Delivery\Resource\ContentType;
use Contentful\Delivery\Resource\ContentType\Field;
use Contentful\Delivery\Resource\Entry;
use Contentful\Delivery\Resource\Locale;
use Contentful\Delivery\Resource\Space;
use Contentful\Delivery\SystemProperties;
use PHPUnit\Framework\TestCase;
use ReflectionException;
use function md5;
use function uniqid;

/**
 * Checks the getter for cache tags.
 *
 * @author b3nl <code@b3nl.de>
 * @package BestIt\ContentfulBundle\Tests
 */
class CacheTagsGetterTraitTest extends TestCase
{
    use TestTraitsTrait;

    /**
     * @var CacheTagsGetterStub The tested class.
     */
    protected $fixture;

    /**
     * Returns the names of the used traits.
     *
     * @return array
     */
    protected function getUsedTraitNames(): array
    {
        return [CacheTagsGetterTrait::class];
    }

    /**
     * Sets up the test.
     *
     * @return void
     */
    protected function setUp()
    {
        $this->fixture = new CacheTagsGetterStub();
    }

    /**
     * Checks the default return of the method.
     *
     * @return void
     */
    public function testGetCacheTagsDefault()
    {
        $this->fixture->setDefaultTags($tags = [uniqid()]);

        static::assertSame($tags, $this->fixture->getCacheTagsPublic([]));
    }

    /**
     * Checks if the getter returns the id and the slug value as a minimum.
     *
     * @return void
     */
    public function testGetCacheTagsFull()
    {
        $this->fixture
            ->setRoutableTypes([$type = uniqid()])
            ->setSlugField($slugField = 'slug');

        $resources = new ResourceArray(
            [$entry = $this->createMock(Entry::class)],
            1,
            1,
            0
        );

        $entry
            ->expects(static::once())
            ->method('getId')
            ->willReturn($id = uniqid());

        $entry
            ->expects(static::once())
            ->method('getContentType')
            ->willReturn($contentType = $this->createMock(ContentType::class));

        $entry
            ->expects(static::once())
            ->method('offsetGet')
            ->with(ucfirst($slugField))
            ->willReturn($slugValue = uniqid());

        $contentType
            ->expects(static::once())
            ->method('getFields')
            ->willReturn([
                new Field('slug', 'Slug', 'Symbol'),
                new Field('title', 'Title', 'Symbol')
            ]);

        $contentType
            ->expects(static::once())
            ->method('getId')
            ->willReturn($type);

        static::assertSame(
            [$id, md5($slugValue) . '-contentful-routing'],
            $this->fixture->getCacheTagsPublic($resources)
        );
    }
}
