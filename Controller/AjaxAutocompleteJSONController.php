<?php

namespace SGN\FormsBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Template;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\Component\HttpFoundation\Response;

class AjaxAutocompleteJSONController extends Controller
{

    public function getJSONAction()
    {
        $em           = $this->get('doctrine')->getManager();
        $request      = $this->getRequest();
        $entities     = $this->get('service_container')->getParameter('sgn_forms.autocomplete_entities');
        $entity_alias = $request->get('entity_alias');
        $entity_inf   = $entities[$entity_alias];
        if ( false === $this->get('security.context')->isGranted($entity_inf['role']) )
        {
            throw new AccessDeniedException();
        }
        $letters      = $request->get('letters');
        $maxRows      = $request->get('page_limit');

        $property = $entity_inf['property'];
        $value    = $entity_inf['value'];
        $filter   = $entity_inf['filter'];

        switch ( $entity_inf['target'] )
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
        if ( $entity_inf['case_insensitive'] )
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
             FROM '.$entity_inf['class'].' e 
             WHERE '.$filter.' AND '.
             $where_clause.' '.
            'ORDER BY e.'.$property)
            ->setParameter('like', $like)
            ->setMaxResults($maxRows)
            ->getScalarResult();

        $res = array();

        foreach ($results as $r)
        {
            switch ( $entity_inf['show'] )
            {
                case "property":
                    $show = $r[$property];
                    break;
                case "value":
                    $show = $r[$value];
                break;
                case "property_value":
                    $show = $r[$property]." (".$r[$value].")";
                    break;
                case "value_property":
                    $show = $r[$value]." (".$r[$property].")";
                    break;
                default:
                    throw new \Exception('Unexpected value of parameter “show”.');
            }
            $res[] = array("id"=>$r[$value],"text"=>$show);
        }
        if (count($results) == 1)
        {
            switch ( $entity_inf['show'] )
            {
                case "property":
                    $show = $r[$property];
                    break;
                case "value":
                    $show = $r[$value];
                break;
                case "property_value":
                    $show = $r[$property]." (".$r[$value].")";
                    break;
                case "value_property":
                    $show = $r[$value]." (".$r[$property].")";
                    break;
                default:
                    throw new \Exception('Unexpected value of parameter “show”.');
            }
            $res = array("id"=>$r[$value],"text"=>$show);
        }
        return new Response(json_encode($res));
    }
}
