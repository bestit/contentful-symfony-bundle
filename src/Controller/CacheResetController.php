<?php

namespace BestIt\ContentfulBundle\Controller;

use BestIt\ContentfulBundle\Service\CacheResetService;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use Psr\Log\NullLogger;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Controller to reset the cache for contentful.
 *
 * @author lange <lange@bestit-online.de>
 * @package BestIt\ContentfulBundle\Controller
 * @subpackage Service
 */
class CacheResetController implements LoggerAwareInterface
{
    use LoggerAwareTrait;

    /**
     * The cache reset service.
     *
     * @var CacheResetService
     */
    protected $resetService;

    /**
     * CacheResetController constructor.
     *
     * @param CacheResetService $resetService
     */
    public function __construct(CacheResetService $resetService)
    {
        $this->resetService = $resetService;
        $this->logger = new NullLogger();
    }

    /**
     * Reacts on the contentful request.
     *
     * @param Request $request
     *
     * @return Response
     */
    public function __invoke(Request $request): Response
    {
        @$entry = json_decode($request->getContent());

        return new Response(
            $entry
                ? json_encode($this->resetService->resetEntryCache($entry) ? true : false)
                : json_encode(false)
        );
    }
}
