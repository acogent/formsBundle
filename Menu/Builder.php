<?php

namespace SGN\FormsBundle\Menu;

use Knp\Menu\FactoryInterface;
use Symfony\Component\DependencyInjection\ContainerAware;

class Builder extends ContainerAware
{
    public function formsMenu(FactoryInterface $factory, array $options)
    {
        $menu = $factory->createItem('forms_menu');

        $menu->addChild('AdminDB', array(
            'route' => 'sgn_forms_formscrud_show_4',
            'label' => '<span class="glyphicon glyphicon-hdd"></span> AdminDB',
            'extras' => array('safe_label' => true),
        ));

        return $menu;
    }
}