<?php

namespace BestIt\ContentfulBundle\Controller;

use BestIt\ContentfulBundle\Service\CacheResetService;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Security;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Controller to reset the cache for contentful.
 *
 * @author lange <lange@bestit-online.de>
 * @package BestIt\ContentfulBundle\Controller
 * @subpackage Service
 */
class CacheResetController extends Controller
{
    /**
     * The cache reset service.
     *
     * @var CacheResetService
     */
    protected $resetService = null;

    /**
     * Returns the reset service.
     *
     * @return CacheResetService
     */
    public function getResetService(): CacheResetService
    {
        if (!$this->resetService) {
            $this->setResetService($this->get('best_it_contentful.delivery.cache.reset_service'));
        }

        return $this->resetService;
    }

    /**
     * Reacts on the contentful request.
     *
     * @param Request $request
     * @Route("reset")
     * @Security("has_role('ROLE_USER')")
     *
     * @return Response
     */
    public function postAction(Request $request): Response
    {
        @$entry = json_decode($request->getContent());

        return new Response(
            $entry
                ? json_encode($this->getResetService()->resetEntryCache($entry) ? true : false)
                : json_encode(false)
        );
    }

    /**
     * @param CacheResetService $resetService
     *
     * @return CacheResetController
     */
    public function setResetService(CacheResetService $resetService): CacheResetController
    {
        $this->resetService = $resetService;

        return $this;
    }
}
