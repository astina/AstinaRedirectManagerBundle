<?php

namespace Astina\Bundle\RedirectManagerBundle\Redirect;

use Astina\Bundle\RedirectManagerBundle\Entity\Map;
use Astina\Bundle\RedirectManagerBundle\Entity\MapRepository;
use Symfony\Bridge\Doctrine\RegistryInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Doctrine\ORM\EntityManager;

/**
 * Class RedirectFinder
 *
 * @package   Astina\Bundle\RedirectManagerBundle\Redirect
 * @author    Philipp Kräutli <pkraeutli@astina.ch>
 * @copyright 2014 Astina AG (http://astina.ch)
 */
class RedirectFinder implements RedirectFinderInterface
{
    /**
     * @var EntityManager
     */
    protected $entityManager;

    /**
     * @param RegistryInterface $doctrine
     */
    public function __construct(EntityManager $entityManager)
    {
        $this->entityManager = $entityManager;
    }

    /**
     * @param Request $request
     *
     * @return null|RedirectResponse
     */
    public function findRedirect(Request $request)
    {
        $requestUri = urldecode($request->getRequestUri());
        $path = str_replace($request->getBaseUrl(), '/', $requestUri);
        $url = $request->getSchemeAndHttpHost() . $requestUri;

        // find possible candidates for redirection
        /** @var MapRepository $repo */
        $repo = $this->entityManager->getRepository('AstinaRedirectManagerBundle:Map');
        $maps = $repo->findCandidatesForUrlOrPath($url, $path);

        if (empty($maps)) {
            return null;
        }

        $redirect = $this->resolveRedirect($request, $maps);

        if (null === $redirect) {
            return null;
        }

        $redirectUrl = $redirect->getRedirectUrl();

        $map = $redirect->getMap();
        if ($map->isCountRedirects()) {
            $map->increaseCount();
            $this->entityManager->persist($map);
            $this->entityManager->flush($map);
        }

        return new RedirectResponse($redirectUrl, $map->getRedirectHttpCode());
    }

    /**
     * @param Request $request
     * @param Map[] $maps
     * @return Redirect
     */
    protected function resolveRedirect(Request $request, $maps)
    {
        foreach ($maps as $map) {
            $redirect = new Redirect($request, $map);
            if ($redirect->matchesRequest()) {
                return $redirect;
            }
        }

        return null;
    }
}
