<?php

namespace BestIt\ContentfulBundle\Controller;

use BestIt\ContentfulBundle\Service\Cache\CacheEntryManager;
use Contentful\Delivery\Client;
use Contentful\Delivery\DynamicEntry;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Security;
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
class CacheFillController
{
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
     * Reacts on the contentful request.
     *
     * @param Request $request The request from symfony
     * @Route("fill")
     * @Security("has_role('ROLE_USER')")
     *
     * @return Response
     */
    public function __invoke(Request $request) : Response
    {
        $entry = json_decode($request->getContent(), true);

        $success = true;
        $message = '';
        try {
            $entry = $this->client->reviveJson($entry);

            if ($entry instanceof DynamicEntry) {
                $this->cacheEntryManager->saveEntryInCache($entry);
            }
        } catch (Throwable $e) {
            $success = false;
            $message = $e->getMessage();
        }

        return new JsonResponse([
            'success' => $success,
            'message' => $message
        ]);
    }
}
