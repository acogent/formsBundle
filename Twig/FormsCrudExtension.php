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
            'sgn_forms_crud_twig_template' =>  $this->container->getParameter('sgn_forms.twig_template'),
            'sgn_forms_crud_twig_style'    =>  $this->container->getParameter('sgn_forms.twig_style'),
            'sgn_forms_crud_twig_bestof'   =>  $this->container->getParameter('sgn_forms.twig_bestof')
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
