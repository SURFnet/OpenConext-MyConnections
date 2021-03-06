<?php
namespace AppBundle\Controller;

use AppBundle\Entity\Connection;
use AppBundle\Form\ConnectionType;

use FOS\RestBundle\Controller\Annotations;
use FOS\RestBundle\Controller\FOSRestController;
use FOS\RestBundle\Util\Codes;

use Doctrine\DBAL\DBALException;

use Nelmio\ApiDocBundle\Annotation\ApiDoc;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\NotAcceptableHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Rest controller for users.
 *
 * @package AppBundle\Controller
 * @Annotations\Route(defaults={"_format": "json"})
 */
class RestController extends FOSRestController
{
    /**
     * Retrieve Connections from database by uid (userID).
     *
     * @ApiDoc(
     *   output = "ArrayCollection<AppBundle\Entity\Connection>",
     *   resource = true,
     *   requirements = {
     *      {
     *          "name" = "uid",
     *          "dataType" = "string",
     *          "description" = "UserID"
     *      }
     *   },
     *   statusCodes = {
     *     200 = "Returned when successful",
     *     404 = "Returned when the user is not found"
     *   }
     * )
     *
     * @param Request $request the request object
     * @param string     $uid      the User id
     *
     * @return array
     *
     * @throws NotFoundHttpException when no connections found
     */
    public function getConnectionsAction(Request $request, $uid)
    {
        /** @var Connection $orcid */
        $connections = $this->getDoctrine()
            ->getRepository('AppBundle:Connection')
            ->findBy(
                [
                    'uid' => $uid
                ]
            );

        if (!$connections) {
            throw new NotFoundHttpException(
                'No connections with uid: ' .
                $uid .
                ' found'
            );
        }

        return $connections;
    }

    /**
     * Creates a connection record from the submitted JSON data.
     *
     * @ApiDoc(
     *   input = "AppBundle\Form\ConnectionType",
     *   resource = true,
     *   statusCodes = {
     *     200 = "Returned when successful",
     *     404 = "Returned when the connection record is invalid"
     *   }
     * )
     *
     * @param Request $request the request object
     * @return array
     * @throws NotAcceptableHttpException
     */
    public function postConnectionAction(Request $request)
    {
        $connection = new Connection();
        $connection->setEstablishedAt(new \DateTime());

        $form = $this->createForm(new ConnectionType(), $connection);
        $form->submit($request);

        if ($form->isValid()) {

            try {
                $em = $this->getDoctrine()->getManager();

                $em->persist($connection);
                $em->flush();

                $this->get('logger')
                    ->info(
                        'Added connection for uid ' .
                        $connection->getUid() .
                        ' and service ' .
                        $connection->getService()
                    );

                return $this->routeRedirectView(
                    'get_connections',
                    [
                        'uid' => $connection->getUid()
                    ],
                    Codes::HTTP_CREATED
                );
            } catch (DBALException $e) {
                throw new NotAcceptableHttpException($e->getMessage());
            }
        }
        return [ "form" => $form ];
    }

    /**
     * Delete connection from the database.
     *
     * @ApiDoc(
     *   resource = true,
     *   requirements = {
     *      {
     *          "name" = "uid",
     *          "dataType" = "string",
     *          "description" = "UserID"
     *      },
     *      {
     *          "name" = "service",
     *          "dataType" = "string",
     *          "description" = "service machine_name"
     *      }
     *   },
     *   statusCodes = {
     *     201 = "Returned when successful",
     *     404 = "Returned when the user is not found"
     *   }
     * )
     * @param Request $request
     * @param $uid
     * @param $service
     * @return array
     */
    public function deleteConnectionServiceAction(Request $request, $uid, $service)
    {

        /** @var Connection $orcid */
        $connection = $this->getDoctrine()
            ->getRepository('AppBundle:Connection')
            ->find(
                [
                    'uid' => $uid,
                    'service' => $service
                ]
            );

        if (!$connection) {
            throw new NotFoundHttpException(
                'Connection with uid: ' .
                $uid .
                ' and service ' .
                $service .
                ' not found'
            );
        }

        $em = $this->getDoctrine()->getManager();
        $em->remove($connection);
        $em->flush();

        $this->get('logger')
            ->info(
                "Removed connection with uid: " .
                $connection->getUid() .
                ' and service ' .
                $connection->getService()
            );

        return $this->routeRedirectView(
            'get_connections',
            [
                'uid' => $uid
            ],
            Codes::HTTP_ACCEPTED
        );
    }
}
