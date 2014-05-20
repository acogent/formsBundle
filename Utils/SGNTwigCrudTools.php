<?php

namespace SGN\FormsBundle\Utils;

use Doctrine\ORM\Mapping\ClassMetadataInfo;

class SGNTwigCrudTools
{


    /**
     * getMenuTabEntities
     *
     */
    public static function getMenuTabEntities($me, $bundle, $select_entity = array())
    {
        $em = $me->getDoctrine()->getManager();
        $metadatas = $em->getMetadataFactory()->getAllMetadata();
        $tab_entities = array();
        foreach ($metadatas as $metadata) {
            $bundleShortName = SGNTwigCrudTools::getBundleShortName($metadata->getName());
            if ( $bundleShortName <> $bundle  && $bundle <> '*') continue;

            $project_name = SGNTwigCrudTools::getProjectName($bundle);
            $entity_name  = SGNTwigCrudTools::getName( $metadata->getName());
            if (empty($select_entity) || in_array($project_name.".".$entity_name, $select_entity))
            {
                $tab_entity               = array();
                $tab_entity['project']    = $project_name;
                $tab_entity['name']       = $entity_name;
                $tab_entity['identifier'] = $metadata->getIdentifier();
               // $tab_entity['label']      = SGNTwigCrudTools::getEntityTrans($tab_entity, 'label');
                $tab_entity['link']       = SGNTwigCrudTools::getEntityLink($me,$tab_entity['name'], $bundle);
                $tab_entities[strtolower($tab_entity['name'])] = $tab_entity;
            }
        }

        ksort($tab_entities);
        return $tab_entities;
    }
    /**
     * getBundleName
     * @param  string $name
     * @return string
     */
    public static function getProjectName($bundle)
    {
        return $bundle;
    }

    /**
     * getEntityLink
     * @param  string $name
     * @return string
     */
    private static function getEntityLink($me, $name, $bundle)
    {
        $url = $me->get('router')->generate(
            'sgn_forms_formscrud_show',
            array(
                'bundle' => $bundle ,
                'table'  => $name  ,
                'format'=>'html'
                ),
            true
        );



        return $url;
    }

    /**
     * getBundleName
     * @param  string $name
     * @return string
     */
    private static function getBundleName($name)
    {
        return ($p1 = strpos($ns = $name, '\\')) === false ? $ns :
            substr($ns, 0, ($p2 = strpos($ns, 'Bundle\\', $p1 + 1)) === false ? strlen($ns) : $p2+6);
    }
    /**
     * getBundleShortName description]
     * @param  [type] $name [description]
     * @return [type]       [description]
     */
    private static function getBundleShortName($name)
    {
        return str_replace('\\', '', SGNTwigCrudTools::getBundleName($name));
    }
    /**
     * getEntityTrans
     * @param  array $tab_entity
     * @param  string $type label or desc
     * @return string
     */
    private static function getEntityTrans($tab_entity, $type)
    {
        return  strtolower($tab_entity['name'].'.'.$type);
    }

/**
     * get the short name
     * @param  string $name
     * @return string
     */
    private static function getName($name)
    {
        $namespaceParts = explode('\\', $name);
        return end($namespaceParts);
    }


    /**
     * Create a queryBuilder for the entity, with filters added if necessary.
     *
     * @param array $filters    The filters from the GET query (search, source...)
     * @param string $select0   The first select clause
     * @return QueryBuilder     The builder with joins, selects and wheres added
     */
     public static function builderWithFilters($em, $entityName, array $filters, $select0 = 'u' ) {

        $metadata = $em->getClassMetadata($entityName);
        $builder = $em->getRepository($entityName)->createQueryBuilder('u');

        $inc = 0;
        $selects = array();
        $selects[] = $select0;
        $fieldsAssoc = array();
        $entitiesAssoc = array();
        foreach ($metadata->getAssociationMappings() as $fieldName => $relation) {
            if ($relation['type'] !==  ClassMetadataInfo::ONE_TO_MANY) {
                //We need the related entity name (only the name, not the whole class path)
                $relatedEntity = substr($relation['targetEntity'], strrpos($relation['targetEntity'], '\\', -1) +1);
                if ($relation['type'] === ClassMetadataInfo::MANY_TO_MANY) {
                    if (isset($filters['source']) and $relatedEntity == $filters['source']) {
                        $builder->innerJoin('u.'.$fieldName, 'z'.$inc);
                    }
                    $fieldName = substr($fieldName, 0, -1); //For a "where" clause to work, we need the entity name without the ending "s"
                } else {
                    $builder->leftJoin('u.'.$fieldName, 'z'.$inc);
                    //add a "select" for the joined field only if a "count" is not selected already :
                    if (strpos($select0, "COUNT") === false) {
                        $selects[] = 'z'.$inc.'.id AS '.$fieldName;
                    }
                }
                if ($fieldName === 'Type') {
                    $fieldName = $relatedEntity;
                }
                $entitiesAssoc[$relatedEntity] = $fieldName;
                $fieldsAssoc[$fieldName] = 'z'.$inc;
                $inc++;
            }
        }
        $builder->select($selects);

        $wheres = array();

        if (isset($filters['source'])) { //if a source entity is set...
            if(isset($entitiesAssoc[$filters['source']])) { //and is coming from a "OneToMany" or "ManyToMany" relation...
                $wheres[$fieldsAssoc[$entitiesAssoc[$filters['source']]].'.id=:source'] = array('source', $filters['sourceId']);
            }
        }

        if (isset($filters['_search']) && $filters['_search'] == 'true') { //if a search is underway...
            $fields = array();
            $fields = $metadata->getFieldNames();
            //for the entity's own fields, add a "where" clause if needed :
            foreach ($fields as $field) {
                if (!empty($filters[$field])) {
                    switch ($metadata->getTypeOfField($field)) {
                        case 'string':
                            $wheres['u.'.$field.' LIKE :u'.$field] = array('u'.$field, $filters[$field].'%');
                            break;
                        case 'integer':
                        case 'boolean':
                            $wheres['u.'.$field.' = :u'.$field] = array('u'.$field, $filters[$field]);
                            break;
                        case 'date':
                            $input = trim($filters[$field]);
                            $date_mask = substr("0000-00-00", strlen($input));
                            $date_incr = substr($input, -2) + 1;
                            $limit_low = $input.$date_mask;
                            $limit_high = substr($input, 0, -2).sprintf('%02d', $date_incr).$date_mask;
                            $wheres['u.'.$field.' >= :u'.$field.'1'] = array('u'.$field.'1', new \DateTime($limit_low));
                            $wheres['u.'.$field.' < :u'.$field.'2'] = array('u'.$field.'2', new \DateTime($limit_high));
                            break;
                        case 'datetime':
                            $input = trim($filters[$field]);
                            $date_mask = substr("0000-00-00 00:00:00", strlen($input));
                            $date_incr = substr($input, -2) + 1;
                            $limit_low = $input.$date_mask;
                            $limit_high = substr($input, 0, -2).sprintf('%02d', $date_incr).$date_mask;
                            $wheres['u.'.$field.' >= :u'.$field.'1'] = array('u'.$field.'1', new \DateTime($limit_low));
                            $wheres['u.'.$field.' < :u'.$field.'2'] = array('u'.$field.'2', new \DateTime($limit_high));
                            break;
                    }
                }
            }
            //for the association fields, add a "where" clause if needed :
            foreach ($fieldsAssoc as $fieldName => $assocName) {
                if (!empty($filters[$fieldName])) {
                    $wheres[$assocName.'.nom LIKE :'.$assocName.$fieldName] = array($assocName.$fieldName, $filters[$fieldName].'%');
                }
            }

            if (!empty($filters['searchField'])) {
                $field = $filters['searchField'];
                $string = $filters['searchString'];
                $searchOper = $filters['searchOper'];
                $operator = array('eq' => ' = ', 'ne' => ' <> ',
                                  'lt' => ' < ', 'le' => ' <= ',
                                  'gt' => ' > ', 'ge' => ' >= ',
                                  'bw' => ' LIKE ', 'bn' => ' NOT LIKE ',
                                  'in' => ' IN ', 'ni' => ' NOT IN ',
                                  'ew' => ' LIKE ', 'en' => ' NOT LIKE ',
                                  'cn' => ' LIKE ', 'nc' => ' NOT LIKE ',
                                  'nu' => ' IS NULL', 'nn' => ' IS NOT NULL');

                if ($string || $searchOper == 'nu' || $searchOper == 'nn') {
                    switch ($searchOper) {
                        case 'bw': //'begins with'
                        case 'bn': //'does not begin with'
                            $string = $string.'%';
                            break;
                        case 'in': //'is in'
                        case 'ni': //'is not in'
                            $string = '(\''.$string.'\')';
                            break;
                        case 'ew': //'ends with'
                        case 'en': //'does not end with'
                            $string = '%'.$string;
                            break;
                        case 'cn': //'contains'
                        case 'nc': //'does not contain'
                            $string = '%'.$string.'%';
                            break;
                    }
                    if (isset($fieldsAssoc[$field])) {
                        $fieldName = $fieldsAssoc[$field].'.id';
                        $placeHolder = ':'.$fieldsAssoc[$field].$field;
                    } else {
                        $fieldName = 'u.'.$field;
                        $placeHolder = ':u'.$field;
                    }
                    if ($string) {
                        $wheres[$fieldName.$operator[$searchOper].$placeHolder] = array($placeHolder, $string);
                    } else {
                        $wheres[$fieldName.$operator[$searchOper]] = array();
                    }
                }
            }
        } //... a search was underway.

        $firstClause = true;
        foreach ($wheres as $clause => $parameters) {
            if ($firstClause) {
                if (!empty($parameters)) {
                    $builder->where($clause)->setParameter($parameters[0], $parameters[1]);
                } else {
                    $builder->where($clause);
                }
                $firstClause = false;
            } else {
                if (!empty($parameters)) {
                    $builder->andWhere($clause)->setParameter($parameters[0], $parameters[1]);
                } else {
                    $builder->andWhere($clause);
                }
            }
        }

        return $builder;
    }
}
