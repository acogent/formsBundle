<?php

namespace SGN\FormsBundle\Twig;

use \Twig_Function_Method;
use \Twig_Filter_Method;

class FormsCrudExtension extends \Twig_Extension
{
    private $container;


    public function __construct($container)
    {
        $this->container = $container;
    }


    public function getName()
    {
        return 'forms_crud_twig_extension';
    }


    public function getGlobals()
    {
        return array
        (
            'sgn_forms_crud_twig_style'    =>  $this->container->getParameter('sgn_forms.twig_style'),
            'sgn_forms_crud_bestof_entity' =>  $this->container->getParameter('sgn_forms.bestof_entity'),
            'sgn_forms_crud_orm'           =>  $this->container->getParameter('sgn_forms.orm'),
            'sgn_forms_bundles'            =>  $this->container->getParameter('sgn_forms.bundles'),
            'sgn_forms_template'           =>  $this->container->getParameter('sgn_forms.template')
        );

    }


    /**
     * Retourne la liste des Filtres de template à ajouter à Twig.
     *
     * @return array
     */
    public function getFilters()
    {
        return array
        (
            'json_decode'     => new Twig_Filter_Method($this, 'jsonDecodeFilter'),
            // 'dump_php'     => new Twig_Filter_Method($this, 'dumpFilter'),
            'DD2DMS'          => new Twig_Filter_Method($this, 'DD2DMSFilter'),
            'GPSWeek'         => new Twig_Filter_Method($this, 'GPSWeekFilter'),
            'DayOfYear'       => new Twig_Filter_Method($this, 'DayOfYearFilter'),
            'DayOfWeek'       => new Twig_Filter_Method($this, 'DayOfWeekFilter'),
            'YearOfDate'      => new Twig_Filter_Method($this, 'YearOfDateFilter'),
            'jsonToHTMLTable' => new Twig_Filter_Method($this, 'jsonToHTMLTableFilter'),
        );

    }


    /**
     * Retourne la liste des Fonctions de template à ajouter à Twig.
     *
     * @return array
     */
    public function getFunctions()
    {
        return array(
                     'current_uri'    => new \Twig_Function_Method($this, 'getCurrentURIFunction'),
                     'route_exists'   => new \Twig_Function_Method($this, 'routeExists'),
                     );
    }


/*****************************************************
*
*   Filtres
*
*
*****************************************************/


    /**
     * Renvoie en table HTML un json très simple
     *
     * @param json $json
     * @return html
     */
    public function jsonToHTMLTableFilter($json)
    {
        $html = "<table class = 'table_json'>";
        $tab  = json_decode($json);
        foreach ($tab as $key => $val) {
            $html .= '<tr><td>'.$key.'</td><td>'.$val.'</td></tr>';
        }

        return $html.'</table>';
    }


   /**
     * Renvoie le jour GPS
     *
     * @param  string $DateTime
     * @return string
     */
    public function GPSWeekFilter($str)
    {
        $dateTime         = new \DateTime($str);
        $originalDateTime = new \DateTime('1980-01-09');
        $interval         = $originalDateTime->diff($dateTime);
        $days  = (double) $interval->format('%a');
        $weeks = (integer) (($days / 7) + 0.5);

        return $weeks;
    }


    /**
     * Renvoie le jour de la semaine
     *
     * @param  string $DateTime
     * @return string
     */
    public function DayOfWeekFilter($str)
    {
        $dateTime  = new \DateTime($str);
        $dayOfWeek = $dateTime->format('w').' ('.$dateTime->format('l').')';

        return $dayOfWeek;
    }


    /**
     * Renvoie le numéro du jour dans l'année
     *
     * @param  string $DateTime
     * @return string
     */
    public function DayOfYearFilter($str)
    {
        $dateTime  = new \DateTime($str);
        $dayOfyear = $dateTime->format('z').' ('.$dateTime->format('Y').')';

        return $dayOfyear;
    }


    /**
     * Renvoie l'année de la date
     *
     * @param  string $DateTime
     * @return string
     */
    public function YearOfDateFilter($str)
    {
        $dateTime = new \DateTime($str);
        $year     = $dateTime->format('Y');

        return $year;
    }


    /**
     * jsonDecode
     * @param  string $string
     * @return array
     */
    public function jsonDecodeFilter($string)
    {
        $object = json_decode($string, true);

        return $object;
    }


    /**
     * Convert coord DD to DMS
     * @param double $coord1
     *
     * @return string
     */
    public function DD2DMSFilter($coord1)
    {
        $vars   = explode('.', $coord1);
        $deg    = $vars[0];
        $tempma = '0.'.$vars[1];

        $tempma = ($tempma * 3600);
        $min    = floor($tempma / 60);
        $sec    = round(($tempma - ($min * 60)), 5);

        $str = $deg.' °  '.$min." ' ".$sec.' "';

        return $str;
    }


/*****************************************************
*
*   Fonctions
*
*
*****************************************************/


    /**
     * Retourne l'URI courante.
     *
     * @return string $_SERVER['REQUEST_URI']
     */
    public function getCurrentURIFunction()
    {
        return $_SERVER['REQUEST_URI'];
    }


    public function routeExists($path)
    {
        $router = $this->container->get('router');
        $routes = $router->getRouteCollection();
        foreach ($routes as $route) {
            if ($route->getPath() === $path) {
                return true;
            }
        }
        return false;
    }
}
