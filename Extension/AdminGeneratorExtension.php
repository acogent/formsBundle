<?php

namespace SGN\FormsBundle\Extension;

use SGN\AdminInterfaceBundle\Extension\ExtensionInterface;

class AdminGeneratorExtension implements ExtensionInterface
{
    public function getMenu()
    {
        return array(
            '<span class="glyphicon glyphicon-hdd"></span> AdminDB' => array(
                'routename'         => 'sgn_forms_formscrud_show_4',
                // 'submenu'           => array(
                //     'List'      => array(
                //         'routename'         => 'admin_user_list',
                //         'index'             => 1,
                //     ),
                // ),
            ),
        );
    }
}