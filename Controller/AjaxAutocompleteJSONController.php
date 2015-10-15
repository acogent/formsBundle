<?php

namespace SGN\FormsBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Template;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\Component\HttpFoundation\Response;

class AjaxAutocompleteJSONController extends Controller
{


    public function getJSONAction($init)
    {
        $em           = $this->get('doctrine')->getManager();
        $request      = $this->getRequest();
        $entities     = $this->get('service_container')->getParameter('sgn_forms.autocomplete_entities');
        $entity_alias = $request->get('entity_alias');
        $filter       = $request->get('filter');
        $entity_info  = $entities[$entity_alias];

        if (false === $this->get('security.context')->isGranted($entity_info['role'])) {
            throw new AccessDeniedException();
        }

        $letters = $request->get('letters');
        $maxRows = $request->get('page_limit');

        $class    = $entity_info['class'];
        $property = $entity_info['property'];
        $method   = $entity_info['method'];
        $value    = $entity_info['value'];
        $target   = $entity_info['target'];
        $show     = $entity_info['show'];
        $case_insensitive = $entity_info['case_insensitive'];
        $query            = $entity_info['query'];
        $minLength        = $entity_info['minLength'];


        // Cas des “property” spéciaux, avec un préfixe :
        if (strpos($property, '.') !== false) {
            $prop_query = $property; // utilisé dans les requêtes
            $foo        = explode('.', $property);
            $property   = $foo[1]; // property débarrassé de son préfixe
            // $property   = explode(".", $property)[1]; // property débarrassé de son préfixe
        } else {
            $prop_query = 'e.'.$property; // utilisé dans les requêtes
        }

        if (($show == 'property_value' || $show == 'value_property') && $target != 'both') {
            throw new \Exception('Inconsistency between values of parameters "target" and "show".');
        }

        $res = array();

        // fonction __toString à éviter !!
        if ($property == '__toString') {
            $entities = $em->getRepository($class)->findAll();
            $letters  = trim($letters, '%');

            foreach ($entities as $entity) {
                $id       = $entity->getId().'';
                $toString = $entity->__toString();
                switch ($show)
                {
                    case 'value':
                        $showtext = $id;
                        break;

                    case 'property':
                        $showtext = $toString;
                        break;

                    case 'property_value':
                        $showtext = $toString.' ('.$id.')';
                        break;

                    case 'value_property':
                        $showtext = $id.' ('.$toString.')';
                        break;

                    default:
                        throw new \Exception('Unexpected value of parameter "show".');
                }

                $showtextup = $showtext;
                if ($case_insensitive) {
                    $letters    = strtoupper($letters);
                    $toString   = strtoupper($toString);
                    $showtextup = strtoupper($showtextup);
                }

                if (strpos($toString, $letters) === false
                    && strpos($id, $letters) === false
                    && strpos($showtextup, $letters) === false
                ) {
                    continue;
                }

                $res[] = array(
                          'id'   => $id,
                          'text' => $showtext,
                         );
                if ($init == '1') {
if (empty($res)) {
                        $res = array(
                                'id'   => null,
                                'text' => '(not found)',
                               );
} else {
                        $res = $res[0];
}
                }

                return new Response(json_encode($res));
            }
        }


        switch ($target)
        {
            case 'property':
                $target1 = $prop_query;
                $target2 = null;
                break;

            case 'value':
                $target1 = 'e.'.$value;
                $target2 = null;
                break;

            case 'both':
                $target1 = $prop_query;
                $target2 = 'e.'.$value;
                break;

            default:
                throw new \Exception('Unexpected value of parameter "target".');
        }

        switch ($entity_info['search'])
        {
            case 'begins_with':
                $like = $letters.'%';
                break;

            case 'ends_with':
                $like = '%'.$letters;
                break;

            case 'contains':
                $like = '%'.$letters.'%';
                break;

            case 'equals':
                $like = $letters;
                break;

            default:
                throw new \Exception('Unexpected value of parameter "search".');
        }

        $where_clause_lhs2 = '';
        $where_clause_rhs2 = '';
        if ($case_insensitive) {
            $where_clause_lhs1 = 'LOWER('.$target1.')';
            $where_clause_rhs1 = 'LIKE LOWER(:like)';
            if ($target2 != null) {
                $where_clause_lhs2 = 'LOWER('.$target2.')';
                $where_clause_rhs2 = 'LIKE LOWER(:like)';
            }
        } else {
            $where_clause_lhs1 = $target1;
            $where_clause_rhs1 = 'LIKE :like';
            if ($target2 != null) {
                $where_clause_lhs2 = $target2;
                $where_clause_rhs2 = 'LIKE :like';
            }
        }

        $where_clause = $where_clause_lhs1.' '.$where_clause_rhs1;
        if ($where_clause_lhs2 != '' && $where_clause_rhs2 != '') {
            $where_clause = '('.$where_clause_lhs1.' '.$where_clause_rhs1.' OR '.$where_clause_lhs2.' '.$where_clause_rhs2.')';
        }

        // Alternative à __toString !! : création d'un champ pas en base des getet set + une fonction dans repository
        if (isset($method)) {
            $sql   = $em->getRepository($class)->$method();
            $query = $em->createQuery($sql)->setParameter('like', $like)->setParameter('like', $like)->setMaxResults($maxRows);

            // var_dump("entity_alias $entity_alias");
            // var_dump("filter $filter");
            // var_dump($entity_info);
            // var_dump($like);
            // var_dump("where_clause $where_clause");
            // var_dump( $query->getSql() ) ;

            $results = $query->getScalarResult();
            foreach ($results as $r) {
                $res[] = array(
                          'id'   => $r['id'],
                          'text' => $r['value'],
                         );
            }

            if ($init == '1') {
if (empty($res)) {
                    $res = array(
                            'id'   => null,
                            'text' => '(not found)',
                           );
} else {
                    $res = $res[0];
}
            }

            return new Response(json_encode($res));
        }

        if ($query == 'class') {
            $query = $em->createQuery(
                'SELECT e.'.$property.', e.'.$value.'
                                FROM '.$class.' e
                                WHERE '.$filter.' AND '.$where_clause.' '.'ORDER BY '.$prop_query
            )->setParameter('like', $like)->setMaxResults($maxRows);
            // print_r( $query->getSql() ) ; exit();
            $results = $query->getScalarResult();
        } else {
            $query = $em->createQuery($query.'
                                AND '.$filter.' AND '.$where_clause.' '.'ORDER BY '.$prop_query)->setParameter('like', $like)->setMaxResults($maxRows);
            // print_r( $query->getSql() ) ; exit();
            $results = $query->getScalarResult();
        }


        foreach ($results as $r) {
        switch ($show)
            {
            case 'property':
                    $showtext = $r[$property];
                break;

            case 'value':
                    $showtext = $r[$value];
                break;

            case 'property_value':
                    $showtext = $r[$property].' ('.$r[$value].')';
                break;

            case 'value_property':
                    $showtext = $r[$value].' ('.$r[$property].')';
                break;

            default:
                throw new \Exception('Unexpected value of parameter "show".');
        }

            $res[] = array(
                      'id'   => $r[$value],
                      'text' => $showtext,
                     );
        }

        if ($init == '1') {
if (empty($res)) {
                $res = array(
                        'id'   => null,
                        'text' => '(not found)',
                       );
} else {
                $res = $res[0];
}
        }

        return new Response(json_encode($res));
    }


}
