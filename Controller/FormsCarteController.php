<?php

namespace SGN\FormsBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;

/**
 * @SuppressWarnings(PHPMD.StaticAccess)
 */
class FormsCarteController extends Controller
{
    /**
     * @Route("/carte/{lon}/{lat}/{zoom}")
     * @Route("/carte/")
     */
    public function CarteAction($lon = 2.0, $lat = 40.0, $zoom = 12)
    {
        return $this->render(
            'SGNFormsBundle:Carte:carte.html.twig',
            array(
             'lon'  => $lon,
             'lat'  => $lat,
             'zoom' => $zoom,
            )
        );
    }


    /**
     * @Route("/carte/bdg/{lon}/{lat}/{zoom}")
     */
    public function CarteBDGAction($lon, $lat, $zoom)
    {
        return $this->render(
            'SGNFormsBundle:Carte:carte.bdg.html.twig',
            array(
             'lon'  => $lon,
             'lat'  => $lat,
             'zoom' => $zoom,
            )
        );
    }
}
