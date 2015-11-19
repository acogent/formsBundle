<?php

namespace SGN\FormsBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\Component\HttpFoundation\Response;

class AjaxAutocompleteJSONController extends Controller
{


    public function getJSONAction($init)
    {
        $em           = $this->get('doctrine')->getManager();
        $request      = $this->getRequest();
        $entity_alias = $request->get('entity_alias');

        $entities    = $this->get('service_container')->getParameter('sgn_forms.autocomplete_entities');
        $entity_info = $entities[$entity_alias];

        if (false === $this->get('security.context')->isGranted($entity_info['role'])) {
            throw new AccessDeniedException();
        }

        // mauvaise construction des parametres, on s'en va
        if (($entity_info['show'] == 'property_value' || $entity_info['show'] == 'value_property') && $entity_info['target'] != 'both') {
            throw new \Exception('Inconsistency between values of parameters "target" and "show".');
        }

        // fonction __toString à éviter !!
        if ($entity_info['property'] === '__toString') {
            return $this->toString($entity_info, $init);
        }

        // Alternative à __toString !! : création d'un champ pas en base des getet set + une fonction dans repository
        if (isset($entity_info['method']) === true) {
            return $this->method($entity_info, $init);
        }

        // cas général
        return $this->sql($entity_info, $init);
    }

    private function toString($entity_info, $init)
    {
        $em      = $this->get('doctrine')->getManager();
        $request = $this->getRequest();

        $letters = $request->get('letters');

        $class            = $entity_info['class'];
        $show             = $entity_info['show'];
        $entities         = $em->getRepository($class)->findAll();
        $case_insensitive = $entity_info['case_insensitive'];

        $letters = trim($letters, '%');
        if (strlen($letters) === 0) {
            $res = array(
                    'id'   => null,
                    'text' => '(no letters)',
                   );

            return new Response(json_encode($res));
        }

        $res = array();

        foreach ($entities as $entity) {
            $id       = $entity->getId().'';
            $toString = $entity->__toString();
            switch ($show) {
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

    private function method($entity_info, $init)
    {
        $em      = $this->get('doctrine')->getManager();
        $request = $this->getRequest();
        // dump($request);
        $class   = $entity_info['class'];
        $method  = $entity_info['method'];
        $like    = $this->getLike($entity_info);
        $maxRows = $request->get('page_limit');
        $filter  = $request->get('filter');
        if (isset($filter) === true) {
            $filter = ' AND '.$filter;
        }

        $resMethod = $em->getRepository($class)->$method();
        if (is_object($resMethod) === true) {
            $queryBuilder = $resMethod;
            $query        = $queryBuilder->setMaxResults($maxRows);
            $results      = $query->getQuery()->getResult();
        } else {
            $sql     = $resMethod.$filter;
            $query   = $em->createQuery($sql)->setParameter('like', $like)->setMaxResults($maxRows);
            $results = $query->getScalarResult();
        }

        // dump( $query->getSql() ) ;
        $res = array();

        // dump($results);
        foreach ($results as $r) {
            $res[] = array(
                      'id'   => $r['id'],
                      'text' => $r['value'],
                     );
        }

        if ($init == '1') {
            if (empty($res) === true) {
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

    private function sql($entity_info, $init)
    {
        $em = $this->get('doctrine')->getManager();

        $request = $this->getRequest();
        $filter  = $request->get('filter');
        $maxRows = $request->get('page_limit');

        $property = $entity_info['property'];

        // Cas des “property” spéciaux, avec un préfixe :
        if (strpos($property, '.') !== false) {
            $prop_query = $property; // utilisé dans les requêtes
            $foo        = explode('.', $property);
            $property   = $foo[1]; // property débarrassé de son préfixe
        } else {
            $prop_query = 'e.'.$property; // utilisé dans les requêtes
        }

        $like         = $this->getLike($entity_info);
        $where_clause = $this->getWhereClause($entity_info);

        $query = $entity_info['query'];
        $class = $entity_info['class'];
        $value = $entity_info['value'];
        $show  = $entity_info['show'];

        if ($query === 'class') {
            $sql = 'SELECT e.'.$property.', e.'.$value.' FROM '.$class.' e WHERE '.$filter.' AND '.$where_clause.' '.'ORDER BY '.$prop_query;
        } else {
            $sql = $query.'AND '.$filter.' AND '.$where_clause.' '.'ORDER BY '.$prop_query;
        }

        $req = $em->createQuery($sql)->setParameter('like', $like)->setMaxResults($maxRows);
        // print_r( $req->getSql() ) ; exit();
        $results = $req->getScalarResult();

        $res = array();
        foreach ($results as $r) {
            switch ($show) {
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
            if (empty($res) === true) {
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

    private function getLike($entity_info)
    {
        $request = $this->getRequest();
        $letters = $request->get('letters');
        switch ($entity_info['search']) {
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

        return $like;
    }

    private function getWhereClause($entity_info)
    {
        $case_insensitive = $entity_info['case_insensitive'];
        $target           = $entity_info['target'];
        $property         = $entity_info['property'];
        $value            = $entity_info['value'];
        // Cas des “property” spéciaux, avec un préfixe :
        if (strpos($property, '.') !== false) {
            $prop_query = $property; // utilisé dans les requêtes
            $foo        = explode('.', $property);
            $property   = $foo[1]; // property débarrassé de son préfixe
        } else {
            $prop_query = 'e.'.$property; // utilisé dans les requêtes
        }

        switch ($target) {
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

        return $where_clause;
    }
}
