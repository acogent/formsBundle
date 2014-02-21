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
        $entity_inf   = $entities[$entity_alias];
        if ( FALSE === $this->get('security.context')->isGranted($entity_inf['role']) )
        {
            throw new AccessDeniedException();
        }
        $letters      = $request->get('letters');
        $maxRows      = $request->get('page_limit');

        $class            = $entity_inf['class'];
        $property         = $entity_inf['property'];
        $value            = $entity_inf['value'];
        $filter           = $entity_inf['filter'];
        $target           = $entity_inf['target'];
        $show             = $entity_inf['show'];
        $case_insensitive = $entity_inf['case_insensitive'];

        if ( $property == "__toString" )
        {
            $res = array();
            
            $entities = $em->getRepository($class)->findAll();
            $letters  = trim($letters, '%');

            foreach ( $entities as $entity )
            {
                $id       = $entity->getId()."";
                $toString = $entity->__toString();
                switch ( $show )
                {
                    case 'value':
                        $showtext = $id;
                        break;
                    case 'property':
                        $showtext = $toString;
                        break;
                    case 'property_value':
                        $showtext = $toString." (".$id.")";
                        break;
                    case 'value_property':
                        $showtext = $id." (".$toString.")";
                        break;
                    default:
                        throw new \Exception('Unexpected value of parameter “show”.');
                }
                $showtextup = $showtext;
                if ( $case_insensitive )
                {
                    $letters    = strtoupper($letters);
                    $toString   = strtoupper($toString);
                    $showtextup = strtoupper($showtextup);
                }

                if ( strpos($toString,   $letters) === FALSE 
                  && strpos($id,         $letters) === FALSE 
                  && strpos($showtextup, $letters) === FALSE
                ) continue;

                $res[] = array("id" => $id, "text" => $showtext);
            }

        }else{

            switch ( $target )
            {
                case "property":
                    $target1 = "e.".$property;
                    $target2 = NULL;
                    break;
                case "value":
                    $target1 = "e.".$value;
                    $target2 = NULL;
                    break;
                case "both":
                    $target1 = "e.".$property;
                    $target2 = "e.".$value;
                    break;
                default:
                    throw new \Exception('Unexpected value of parameter “target”.');
            }

            switch ( $entity_inf['search'] )
            {
                case "begins_with":
                    $like = $letters . '%';
                    break;
                case "ends_with":
                    $like = '%' . $letters;
                    break;
                case "contains":
                    $like = '%' . $letters . '%';
                    break;
                default:
                    throw new \Exception('Unexpected value of parameter “search”.');
            }

            $where_clause_lhs2 = '';
            $where_clause_rhs2 = '';
            if ( $case_insensitive )
            {
                $where_clause_lhs1 = 'LOWER('.$target1.')';
                $where_clause_rhs1 = 'LIKE LOWER(:like)';
                if ( $target2 != NULL )
                {
                    $where_clause_lhs2 = 'LOWER('.$target2.')';
                    $where_clause_rhs2 = 'LIKE LOWER(:like)';
                }
            } else {
                $where_clause_lhs1 = $target1;
                $where_clause_rhs1 = 'LIKE :like';
                if ( $target2 != NULL )
                {
                    $where_clause_lhs2 = $target2;
                    $where_clause_rhs2 = 'LIKE :like';
                }
            }
            $where_clause = $where_clause_lhs1.' '.$where_clause_rhs1;
            if ( $where_clause_lhs2 != '' && $where_clause_rhs2 != '' )
            {
                $where_clause = '('.$where_clause_lhs1.' '.$where_clause_rhs1.' OR '.$where_clause_lhs2.' '.$where_clause_rhs2.')';
            }

            $results = $em->createQuery(
                'SELECT e.'.$property.', e.'.$value.'
                 FROM '.$class.' e 
                 WHERE '.$filter.' AND '.
                 $where_clause.' '.
                'ORDER BY e.'.$property)
                ->setParameter('like', $like)
                ->setMaxResults($maxRows)
                ->getScalarResult();

            $res = array();

            foreach ($results as $r)
            {
                switch ( $show )
                {
                    case "property":
                        $showtext = $r[$property];
                        break;
                    case "value":
                        $showtext = $r[$value];
                    break;
                    case "property_value":
                        $showtext = $r[$property]." (".$r[$value].")";
                        break;
                    case "value_property":
                        $showtext = $r[$value]." (".$r[$property].")";
                        break;
                    default:
                        throw new \Exception('Unexpected value of parameter “show”.');
                }
                $res[] = array("id" => $r[$value], "text" => $showtext);
            }
        }

        if ( $init == TRUE || $init == '1' ) $res = $res[0];
        return new Response(json_encode($res));
    }
}