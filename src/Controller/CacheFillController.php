<?php

declare(strict_types=1);

namespace BestIt\ContentfulBundle\Controller;

use BestIt\ContentfulBundle\Service\Cache\CacheEntryManager;
use Contentful\Delivery\Client;
use Contentful\Delivery\DynamicEntry;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use Psr\Log\NullLogger;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

/**
 * Controller to fill the content ful cache after via webhook
 *
 * @author Martin Knoop <martin.knoop@bestit-online.de>
 * @package BestIt\ContentfulBundle\Controller
 */
class CacheFillController implements LoggerAwareInterface
{
    use LoggerAwareTrait;

    /**
     * The allowed events of the webhook
     */
    const ALLOWED_EVENTS = ['create', 'publish', 'save'];

    /**
     * The client from contentful
     *
     * @var Client
     */
    private $client;

    /**
     * The manager to handle the cache entries
     *
     * @var CacheEntryManager
     */
    private $cacheEntryManager;

    /**
     * CacheFillController constructor.
     *
     * @param Client $client
     * @param CacheEntryManager $cacheEntryManager
     */
    public function __construct(Client $client, CacheEntryManager $cacheEntryManager)
    {
        $this->client = $client;
        $this->cacheEntryManager = $cacheEntryManager;
        $this->logger = new NullLogger();
    }

    /**
     * Reacts on the contentful request.
     *
     * @param Request $request The request from symfony
     *
     * @return Response
     */
    public function __invoke(Request $request): Response
    {
        $response = [
            'success' => false
        ];
        if ($this->isRequestValid($request)) {
            try {
                $entry = $this->client->reviveJson($request->getContent());

                if ($entry instanceof DynamicEntry) {
                    $this->cacheEntryManager->saveEntryInCache($entry);
                    $response['success'] = true;
                }
            } catch (Throwable $e) {
                $this->logger->error('Error at processing webhook', ['exception' => $e]);
                $response['message'] = $e->getMessage();
                $response['trace'] = $e->getTrace();
            }
        }

        return new JsonResponse($response);
    }

    /**
     * Check if the given request is valid
     *
     * @param Request $request
     *
     * @return bool
     */
    private function isRequestValid(Request $request):bool
    {
        $topic = explode('.', $request->headers->get('X-Contentful-Topic', ''));
        $event = array_pop($topic);

        $valid = false;
        if ($topic !== null && $event !== null && in_array($event, self::ALLOWED_EVENTS)) {
            $valid = true;
            if (!$this->client->isPreview() && $topic !== 'publish') {
                $valid = false;
            }
        }

        return $valid;
    }
}
