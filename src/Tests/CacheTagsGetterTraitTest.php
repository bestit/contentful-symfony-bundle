<?php

declare(strict_types=1);

namespace BestIt\ContentfulBundle\Tests;

use BestIt\ContentfulBundle\CacheTagsGetterTrait;
use Contentful\Delivery\ContentType;
use Contentful\Delivery\ContentTypeField;
use Contentful\Delivery\DynamicEntry;
use Contentful\Delivery\Locale;
use Contentful\Delivery\Space;
use Contentful\Delivery\SystemProperties;
use Contentful\ResourceArray;
use PHPUnit\Framework\TestCase;
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
            ->setSlugField('slug');

        $resources = new ResourceArray(
            [new DynamicEntry(
                $fields = [
                    'slug' => [null => $slugValue = uniqid()],
                    'title' => [null => $titleValue = uniqid()]
                ],
                new SystemProperties(
                    $id = uniqid(),
                    'sys',
                    new Space(
                        uniqid(),
                        [new Locale('de', 'german', 'en')],
                        new SystemProperties(uniqid(), uniqid())
                    ),
                    new ContentType(
                        uniqid(),
                        uniqid(),
                        [
                            new ContentTypeField('slug', 'Slug', 'Symbol'),
                            new ContentTypeField('title', 'Title', 'Symbol')
                        ],
                        'title',
                        new SystemProperties($type, uniqid())
                    )
                )
            )],
            1,
            1,
            0
        );

        static::assertSame(
            [$id, md5($slugValue) . '-contentful-routing'],
            $this->fixture->getCacheTagsPublic($resources)
        );
    }
}
