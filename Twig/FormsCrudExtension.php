<?php

namespace SGN\FormsBundle\Twig;

use Symfony\Bundle\TwigBundle\DependencyInjection\TwigExtension;
use Symfony\Component\HttpKernel\Event\GetResponseEvent;
use Symfony\Component\HttpKernel\HttpKernel;
use \Twig_Function_Method;
use \Twig_Filter_Method;


class FormsCrudExtension extends \Twig_Extension
{
    private $container;

    public function getGlobals()
    {
        return array(
            'sgn_forms_crud_twig_style'    =>  $this->container->getParameter('sgn_forms.twig_style'),
            'sgn_forms_crud_bestof_entity' =>  $this->container->getParameter('sgn_forms.bestof_entity'),
            'sgn_forms_crud_orm'           =>  $this->container->getParameter('sgn_forms.orm'),
            'sgn_forms_bundles'            =>  $this->container->getParameter('sgn_forms.bundles')
        );
    }

    public function getName()
    {
        return 'forms_crud_twig_extension';
    }

    public function __construct($container)
    {
        $this->container = $container;
    }


}
