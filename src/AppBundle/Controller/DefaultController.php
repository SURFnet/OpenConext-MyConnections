<?php

namespace AppBundle\Controller;

use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Request;
use AppBundle\Entity\Connection;
use AppBundle\Connections\Service;

/**
 * Class DefaultController
 * @package AppBundle\Controller
 */
class DefaultController extends Controller
{
    /**
     * @Route("/", name="index")
     */
    public function indexAction(Request $request)
    {
        $connections = [];

        $repository = $this->get('app.service.repository');

        $user = $this->get('app.user');
        if (!$user->isLoggedIn()) {
            return $this->redirectToRoute('login');
        }

        $connected = $this->getDoctrine()
            ->getRepository('AppBundle:Connection')
            ->findBy(
                [
                    'uid' => $user->getUid()
                ]
            );

        /** @var Connection $c */
        foreach ($connected as $c) {
            try {
                /** @var Service $service */
                $service = $this->get('app.service.' . $c->getService());

                // Remove service from available services
                // since its already connected!
                $repository->removeConnection($c->getService());

                $dto = $this->get('app.service.factory')
                    ->createDto(
                        $service,
                        $user->getUsername(),
                        $c->getCuid(),
                        $c->getEstablishedAt()
                    );
                $connections[] = $dto;

            } catch (\Exception $e) {
                $this->get('logger')
                    ->addError(
                        'Service ' .
                        $c->getService() .
                        ' unavailable'
                    );
            }
        }

        $availableConnections =
            $this->get('app.service.factory')
                ->createDtos(
                    $repository,
                    null,
                    null,
                    null
                );

        return $this->render(
            'AppBundle:default:index.html.twig',
            [
                'name' => $user->getDisplayName(),
                'connections' => $connections,
                'available_connections' => $availableConnections
            ]
        );
    }

    /**
     * Login page.
     *
     * @Route("/login", name="login")
     * @param Request $request
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function loginAction(Request $request)
    {
        $repository = $this->get('app.service.repository');

        $availableConnections =
            $this->get('app.service.factory')
                ->createDtos(
                    $repository,
                    null,
                    null,
                    null
                );

        return $this->render(
            'AppBundle:default:login.html.twig',
            [
                'name' => 'Guest',
                'connections' => [],
                'available_connections' => $availableConnections
            ]
        );
    }

    /**
     * Initiate SAML auth request
     *
     * @Route("/auth", name="saml_auth")
     * @param Request $request
     * @return \Symfony\Component\HttpFoundation\RedirectResponse
     */
    public function authAction(Request $request)
    {
        $provider = $this->get('app.interactionprovider');
        $stateHandler = $this->get('app.saml.state_handler');

        $stateHandler->setCurrentRequestUri($request->getUri());
        return $provider->initiateSamlRequest();
    }

    /**
     * @Route("/auth_error", name="auth_error")
     * @param Request $request
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function authErrorAction(Request $request)
    {
        $repository = $this->get('app.service.repository');

        $availableConnections =
            $this->get('app.service.factory')
                ->createDtos(
                    $repository,
                    null,
                    null,
                    null
                );

        return $this->render(
            'AppBundle:default:auth_error.html.twig',
            [
                'name' => 'Guest',
                'connections' => [],
                'available_connections' =>  $availableConnections
            ]
        );
    }

    /**
     * Logout
     *
     * @Route("/logout", name="logout")
     */
    public function logoutAction()
    {
        $user = $this->get('app.user');
        $user->clear();

        return $this->redirectToRoute('index');
    }
}
