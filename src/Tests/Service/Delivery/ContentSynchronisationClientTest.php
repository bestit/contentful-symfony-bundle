<?php

declare(strict_types=1);

namespace BestIt\ContentfulBundle\Tests\Service\Delivery;

use BestIt\ContentfulBundle\Service\Delivery\ContentSynchronisationClient;
use Contentful\Delivery\Client;
use Contentful\Delivery\DynamicEntry;
use Contentful\Delivery\Synchronization\Manager;
use Contentful\Delivery\Synchronization\Query;
use Contentful\Delivery\Synchronization\Result;
use PHPUnit\Framework\TestCase;

/**
 * Tests for the sync client
 *
 * @author Martin Knoop <martin.knoop@bestit-online.de>
 * @package BestIt\ContentfulBundle\Tests\Service\Delivery
 */
class ContentSynchronisationClientTest extends TestCase
{
    /**
     * Test that the generator is returned
     *
     * @return void
     */
    public function testThatTheGeneratorIsReturned()
    {
        $fixture = new ContentSynchronisationClient(
            $client = $this->createMock(Client::class)
        );

        $client
            ->method('getSynchronizationManager')
            ->willReturn($syncManager = $this->createMock(Manager::class));

        $syncManager
            ->expects(self::once())
            ->method('startSync')
            ->with(self::callback(function (Query $query) {
                static::assertSame('initial=1&type=Entry', $query->getQueryString());

                return true;
            }))
            ->willReturn($this->buildResult(5, false));

        $syncManager
            ->method('continueSync')
            ->with('token')
            ->willReturnOnConsecutiveCalls(
                $this->buildResult(5, false),
                $this->buildResult(2, false),
                $this->buildResult(1, true)
            );

        $entries = iterator_to_array($fixture->getSyncEntries());
        foreach ($entries as $entry) {
            static::assertInstanceOf(DynamicEntry::class, $entry);
        }

        static::assertCount(13, $entries);
    }

    /**
     * Build a result object
     *
     * @param int $countItems
     * @param bool $isDone
     *
     * @return Result
     */
    private function buildResult(int $countItems, bool $isDone):Result
    {
        $items = [];
        for ($i = 0; $i < $countItems; $i++) {
            $items[] = $this->createMock(DynamicEntry::class);
        }

        return new Result($items, 'token', $isDone);
    }
}
