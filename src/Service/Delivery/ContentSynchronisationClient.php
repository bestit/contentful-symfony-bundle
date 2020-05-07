<?php

declare(strict_types=1);

namespace BestIt\ContentfulBundle\Service\Delivery;

use Contentful\Delivery\Client;
use Contentful\Delivery\DynamicEntry;
use Contentful\Delivery\Synchronization\Query;
use Generator;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use Psr\Log\NullLogger;

/**
 * Client to fetch all entries from the syncronisation api
 *
 * @author Martin Knoop <martin.knoop@bestit-online.de>
 * @package BestIt\ContentfulBundle\Service\Delivery
 */
class ContentSynchronisationClient implements LoggerAwareInterface
{
    use LoggerAwareTrait;

    /**
     * The contenful client
     *
     * @var Client
     */
    private $client;

    /**
     * ContentSynchronisationClient constructor.
     *
     * @param Client $client
     */
    public function __construct(Client $client)
    {
        $this->client = $client;
        $this->logger = new NullLogger();
    }

    /**
     * Get all sync entries from contentful
     *
     * @return Generator
     */
    public function getSyncEntries(): Generator
    {
        $this->logger->notice('Start initial content sync via synchronisation api');
        $syncManager = $this->client->getSynchronizationManager();

        $result = $syncManager->startSync((new Query())->setType('Entry'));
        $syncToken = $result->getToken();

        $count = 0;
        do {
            if ($result === null) {
                $this->logger->debug('Continue sync via synchronisation api', ['token' => $syncToken]);
                $result = $syncManager->continueSync($syncToken);
            }

            /** @var DynamicEntry $item */
            foreach ($result->getItems() as $item) {
                $this->logger->debug('Fetched dynamic entry with id ' . $item->getId(), ['count' => $count++]);
                yield $item;
            }

            $isDone = $result->isDone();
            $syncToken = $result->getToken();
            $result = null;
        } while(!$isDone);

        $this->logger->notice('Finished initial content sync via synchronisation api');
    }
}
