<?php

declare(strict_types=1);

namespace BestIt\ContentfulBundle\Tests\Service\Delivery;

use BestIt\ContentfulBundle\CacheTTLAwareTrait;
use BestIt\ContentfulBundle\CacheTagsGetterTrait;
use BestIt\ContentfulBundle\ClientEvents;
use BestIt\ContentfulBundle\Delivery\ResponseParserInterface;
use BestIt\ContentfulBundle\Service\Delivery\ClientDecorator;
use BestIt\ContentfulBundle\Tests\TestTraitsTrait;
use Contentful\Core\Resource\ResourceArray;
use Contentful\Delivery\Client;
use Contentful\Delivery\Query;
use Contentful\Delivery\Resource\Asset;
use Contentful\Delivery\Resource\ContentType;
use Contentful\Delivery\Resource\ContentType\Field;
use Contentful\Delivery\Resource\Entry;
use Doctrine\Common\Cache\ArrayCache;
use GuzzleHttp\Exception\RequestException;
use PHPUnit\Framework\TestCase;
use PHPUnit_Framework_MockObject_MockObject;
use Psr\Cache\InvalidArgumentException;
use Psr\Log\LoggerAwareTrait;
use Psr\Log\LoggerInterface;
use Symfony\Component\Cache\Adapter\DoctrineAdapter;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\EventDispatcher\GenericEvent;

/**
 * Tests the service for cache resetting.
 *
 * @author lange <lange@bestit-online.de>
 * @package BestIt\ContentfulBundle\Tests\Service\Delivery
 */
class ClientDecoratorTest extends TestCase
{
    use TestTraitsTrait;

    /**
     * @var Client|PHPUnit_Framework_MockObject_MockObject|null The mocked client.
     */
    private $client;

    /**
     * @var EventDispatcherInterface|PHPUnit_Framework_MockObject_MockObject|null The mocked event dispatcher.
     */
    private $eventDispatcher;

    /**
     * @var ClientDecorator|null The tested class.
     */
    protected $fixture;

    /**
     * @var ResponseParserInterface|PHPUnit_Framework_MockObject_MockObject|null The used parser.
     */
    private $parser;

    /**
     * Returns an injection parser to test and its response.
     *
     * @return array
     */
    public function getParserToFetch()
    {
        $mockParser = $this->createMock(ResponseParserInterface::class);

        $mockParser
            ->expects($this->once())
            ->method('toArray')
            ->with($mockEntry = $this->createMock(Entry::class))
            ->willReturn($result = [uniqid()]);

        return [
            [null],
            [$mockParser, $result]
        ];
    }

    /**
     * Returns the names of the used traits.
     *
     * @return array
     */
    protected function getUsedTraitNames(): array
    {
        return [CacheTagsGetterTrait::class, CacheTTLAwareTrait::class, LoggerAwareTrait::class];
    }

    /**
     * Sets up the test.
     *
     * @return void
     */
    protected function setUp()
    {
        $this->fixture = new ClientDecorator(
            $this->client = $this->createMock(Client::class),
            new DoctrineAdapter(new ArrayCache()),
            $this->eventDispatcher = $this->createMock(EventDispatcherInterface::class),
            $this->createMock(LoggerInterface::class),
            $this->parser = $this->createMock(ResponseParserInterface::class)
        );
    }

    /**
     * Checks if the magical getter calls the base client.
     *
     * @return void
     */
    public function testCallSuccess()
    {
        $this->client
            ->expects($this->once())
            ->method('getAsset')
            ->with($id = uniqid())
            ->willReturn($return = $this->createMock(Asset::class));

        static::assertSame($return, $this->fixture->getAsset($id));
    }

    /**
     * Checks if the entries getter is cached and its response simplified.
     *
     * @return void
     *
     * @throws InvalidArgumentException
     */
    public function testGetEntriesFullWithCache()
    {
        $this->client
            ->expects($this->once())
            ->method('getEntries')
            ->willReturn(
                $resourceArray = new ResourceArray(
                    [$mockEntry = $this->createMock(Entry::class)],
                    1,
                    1,
                    0
                )
            );

        $this->parser
            ->expects($this->once())
            ->method('toArray')
            ->with($resourceArray)
            ->willReturn($result = [uniqid()]);

        $mockEntry
            ->method('getContentType')
            ->willReturn($mockType = $this->createMock(ContentType::class));

        $callback = function ($query) {
            static::assertInstanceOf(Query::class, $query);
        };

        $this->eventDispatcher
            ->expects($this->at(0))
            ->method('dispatch')
            ->with(ClientEvents::LOAD_CONTENTFUL_ENTRIES, $this->isInstanceOf(GenericEvent::class));

        $this->eventDispatcher
            ->expects($this->at(1))
            ->method('dispatch')
            ->with(ClientEvents::LOAD_CONTENTFUL_ENTRY, $this->isInstanceOf(GenericEvent::class));

        static::assertSame(
            $result,
            $this->fixture->getEntries($callback, $cacheId = uniqid(), $this->parser),
            'The first response was not correct.'
        );

        static::assertSame(
            $result,
            $this->fixture->getEntries($callback, $cacheId),
            'The second response should be cached and the same.'
        );
    }

    /**
     * Checks if the entries getter is not cached and its response simplified.
     *
     * @return void
     *
     * @throws InvalidArgumentException
     */
    public function testGetEntriesFullWithoutCache()
    {
        $this->client
            ->expects($this->exactly(2))
            ->method('getEntries')
            ->willReturn(
                $resourceArray = new ResourceArray(
                    [$mockEntry = $this->createMock(Entry::class)],
                    1,
                    1,
                    0
                )
            );

        $this->parser
            ->expects($this->exactly(2))
            ->method('toArray')
            ->with($resourceArray)
            ->willReturn($result = [uniqid()]);

        $mockEntry
            ->method('getContentType')
            ->willReturn($mockType = $this->createMock(ContentType::class));

        $mockEntry
            ->method('getContentType')
            ->willReturn($mockType = $this->createMock(ContentType::class));

        $callback = function ($query) {
            static::assertInstanceOf(Query::class, $query);
        };

        $this->eventDispatcher
            ->expects($this->at(0))
            ->method('dispatch')
            ->with(ClientEvents::LOAD_CONTENTFUL_ENTRIES, $this->isInstanceOf(GenericEvent::class));

        $this->eventDispatcher
            ->expects($this->at(1))
            ->method('dispatch')
            ->with(ClientEvents::LOAD_CONTENTFUL_ENTRY, $this->isInstanceOf(GenericEvent::class));

        static::assertSame(
            $result,
            $this->fixture->getEntries($callback),
            'The first response was not correct.'
        );

        static::assertSame(
            $result,
            $this->fixture->getEntries($callback),
            'The second response should be cached and the same.'
        );
    }

    /**
     * Checks if get entries method returning always an array
     *
     * @return void
     *
     * @throws InvalidArgumentException
     */
    public function testGetEntriesWithWrongType()
    {
        $this->client
            ->expects($this->once())
            ->method('getEntries')
            ->willThrowException($this->createMock(RequestException::class));

        $callback = function ($query) {
            static::assertInstanceOf(Query::class, $query);
        };

        static::assertSame(
            [],
            $this->fixture->getEntries($callback),
            'The first response was not correct.'
        );
    }

    /**
     * Checks if the entry getter is cached and its response simplified.
     *
     * @param ResponseParserInterface $parser
     *
     * @return void
     */
    public function testGetEntryFull(ResponseParserInterface $parser = null)
    {
        if (!$parser) {
            $this->parser
                ->expects($this->once())
                ->method('toArray')
                ->with($mockEntry = $this->createMock(Entry::class))
                ->willReturn($result = [uniqid()]);
        }

        $this->client
            ->expects($this->once())
            ->method('getEntry')
            ->with($id = uniqid())
            ->willReturn($mockEntry);

        $mockEntry
            ->method('getContentType')
            ->willReturn($mockType = $this->createMock(ContentType::class));

        $this->eventDispatcher
            ->expects($this->once())
            ->method('dispatch')
            ->with(ClientEvents::LOAD_CONTENTFUL_ENTRY, $this->isInstanceOf(GenericEvent::class));

        static::assertSame($result, $this->fixture->getEntry($id), 'The first response was not correct.');
        static::assertSame(
            $result,
            $this->fixture->getEntry($id),
            'The second response should be cached and the same.'
        );
    }

    /**
     * Checks the simplification of the response.
     *
     * @return void
     */
    public function testSimplifyResponse()
    {
        $this->markTestIncomplete('Still needed to check.');

        $cache = new DoctrineAdapter(new ArrayCache());
        $client = $this->createMock(Client::class);

        $this->fixture = new class($client, $cache) extends ClientDecorator
        {
            /**
             * Magical getter to make the protecteds public.
             *
             * @param string $method
             * @param array $args
             *
             * @return mixed
             */
            public function __call(string $method, array $args = [])
            {
                return $this->$method(...$args);
            }
        };

        $deep = [
            $rootEntry = $this->createMock(Entry::class)
        ];

        $rootEntry
            ->expects($this->once())
            ->method('getContentType')
            ->willReturn(new ContentType(
                uniqid(),
                uniqid(),
                [
                    $simpleField = new Field('desc', 'desc', 'text'),
                    $imageField = new Field()
                ]
            ));

        static::assertSame(
            [],
            $this->fixture->simplifyResponse($deep)
        );
    }
}
