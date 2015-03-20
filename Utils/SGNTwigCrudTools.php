<?php

namespace SGN\FormsBundle\Utils;

use Doctrine\ORM\Query\ResultSetMappingBuilder;
use Symfony\Component\HttpFoundation\Response;
use SGN\FormsBundle\Utils\Serializor;

class SGNTwigCrudTools
{

    /**
     * getMenuTabEntities
     *
     */
    public static function getMenuTabEntities($me, $bundle, $select_entity = array())
    {
        $eManager = $me->getDoctrine()->getManager();
        $metadatas = $eManager->getMetadataFactory()->getAllMetadata();
        $tab_entities = array();
        foreach ($metadatas as $metadata) {
            $bundleShortName = self::getBundleShortName($metadata->getName());
            if ( $bundleShortName <> $bundle  && $bundle <> '*') continue;

            $entity_name  = self::getName( $metadata->getName());
            if (empty($select_entity) 
                || $select_entity[0] == '*'
                || in_array($bundle.".".$entity_name, $select_entity))
            {
                $tab_entity               = array();
                $tab_entity['project']    = $bundle;
                $tab_entity['name']       = $entity_name;
                $tab_entity['identifier'] = $metadata->getIdentifier();
                $tab_entity['link']       = self::getEntityLink($me,$tab_entity['name'], $bundle);
                $tab_entities[strtolower($tab_entity['name'])] = $tab_entity;
            }
        }

        ksort($tab_entities);
        return $tab_entities;
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
     * get the short name
     * @param  string $name
     * @return string
     */
    public static function getName($name)
    {
        $namespaceParts = explode('\\', $name);
        return end($namespaceParts);
    }

    /**
     * Renvoie les données au format json
     * @param  Entity Manager $eManager
     * @param  string $bundle Le nom du bundle
     * @param  string $table
     * @param  array $filters le tableau issu de l'ajax
     * @param  string $params les paramtres contenus dans l'URL
     * @return json         Les données filtrées au format json
     */
    public static function getFormatJson($eManager, $bundle, $table, $filters, $params)
    {
        $search      = 'false';
        $searchField = 'false';
        $entity      = $bundle.':'.$table;

        if (isset($filters['_search']) === true) {
            $search = $filters['_search'];
        }

        if (isset($filters['searchField']) === true) {
            $searchField = $filters['searchField'];
        }

        // on a lancé une recherche par la barre de recherche
        if ($search === 'true' && $searchField === 'false') {
            return self::searchBar($eManager, $entity, $filters);
        }

        // On a lancé une recherche par la boite de dialogue
        if ($search === 'true' && $searchField !== 'false') {
            return self::searchDialog($eManager, $entity, $filters);
        }

        // on n'a pas lancé de recherche
        return self::noSearch($eManager, $entity, $filters, $params);
    }


    private static function noSearch($eManager, $entity, $filters, $params)
    {
        $totalPages = 0;
        $orderBy    = array();
        $criteria   = self::getCriteriaFromParams($params);
        $limit      = 10;
        $sord       = 'ASC';
        $sidx       = 'id';
        $page       = 0;

        if (isset($filters['rows']) === true) {
            $limit = $filters['rows'];
        }

        if (isset($filters['sidx']) === true) {
            $sidx = $filters['sidx'];
        }

        if (isset($filters['sord']) === true) {
            $sord = $filters['sord'];
        }

        if (isset($filters['page']) === true) {
            $page = $filters['page'];
        }

        $orderBy[$sidx] = $sord;

        $builder = $eManager->getRepository($entity)->createQueryBuilder('a')->select('count(a)');
        $builder = self::getWhereFromParams($params, $builder);
        $count   = $builder->getQuery()->getSingleScalarResult();

        if ($count > 0 && $limit > 0) {
            $totalPages = ceil($count / $limit);
        }

        $start = ($limit * $page - $limit);
        if ($start < 0) {
            $start = 0;
        }

        $data   = $eManager->getRepository($entity)->findBy($criteria, $orderBy, $limit, $start);
        $result = array();
        // $result['debug'] = print_r('search false', true);

        $result['page']    = $page;
        $result['records'] = $count;
        $result['total']   = $totalPages;
        $result['rows']    = Serializor::toArray($data);

        $result   = json_encode($result);
        $response = new Response($result);
        $response->headers->set('Content-Type', 'application/json');

        return $response;
    }


    private static function searchDialog($eManager, $entity, $filters)
    {
        $result = array();
        $page   = 0;

        $searchField  = $filters['searchField'];
        $searchString = $filters['searchString'];
        $searchOper   = $filters['searchOper'];
        if (isset($filters['page']) === true) {
            $page = $filters['page'];
        }

        $repository = $eManager->getRepository($entity);
        $builder    = $repository->createQueryBuilder('u')->where('1  = 1');
        $builder    = self::addWhere($eManager, $entity, $builder, $searchField, $searchString, $searchOper);
        if (get_class($builder) === 'Doctrine\ORM\QueryBuilder') {
            $query = $builder->getQuery();

            // pour le debugage
            $result['debug']   = print_r(array('sql' => $query->getSQL(), 'parameters' => $query->getParameters()), true);
            $result['page']    = $page;
            $result['records'] = '';
            $result['total']   = '';
            $data = $query->getResult();

            $result['rows'] = Serializor::toArray($data);
        }

        if (get_class($builder) === 'Doctrine\ORM\NativeQuery') {
            $data = $builder->getResult();

            $result['debug'] = print_r(array('sql' => $builder->getSQL(), 'parameters' => $builder->getParameters()), true);
            $result['rows']  = Serializor::toArray($data);
        }

        $result   = json_encode($result);
        $response = new Response($result);
        $response->headers->set('Content-Type', 'application/json');

        return $response;
    }


    private static function searchBar($eManager, $entity, $filters)
    {
        $result     = array();
        $totalPages = 0;
        $limit      = 10;
        $page       = 0;

        if (isset($filters['rows']) === true) {
            $limit = $filters['rows'];
        }

        if (isset($filters['page']) === true) {
            $page = $filters['page'];
        }

        // Pour le nombre de pages
        $builder = $eManager->getRepository($entity)->createQueryBuilder('a')->select('count(a)');
        $builder = self::getWhereFromFilters($filters, $builder);
        $query   = $builder->getQuery();
        $count   = $builder->getQuery()->getSingleScalarResult();

        if ($count > 0 && $limit > 0) {
            $totalPages = ceil($count / $limit);
        }

        if ($page > $totalPages) {
            $page = $totalPages;
        }

        $start = ($limit * $page - $limit);
        if ($start < 0) {
            $start = 0;
        }

        // Pour le résultat (data)
        $builder = $eManager->getRepository($entity)->createQueryBuilder('a')->select('a');
        $builder = self::getWhereFromFilters($filters, $builder, true);

        $query = $builder->getQuery();
        $query->setFirstResult($start);
        $query->setMaxResults($limit);

        $data = $query->getResult();

        $result['page']    = $page;
        $result['records'] = $count;
        $result['total']   = $totalPages;
        $result['rows']    = Serializor::toArray($data);

        $result   = json_encode($result);
        $response = new Response($result);

        $response->headers->set('Content-Type', 'application/json');

        return $response;
    }

    /**
     * Renvoie un tableau contenant les critères de sélection contenu dans l'URL
     * @param  string $params la chaine de caractère contenant les parametres
     * @return array         Un tableau avec les paramètres de sélection
     */
    private static function getCriteriaFromParams($params)
    {
        $criteria = array();
        $tParams  = explode('/', $params);
        array_pop($tParams);
        while (count($tParams) > 1 && $tParams[0] <> '') {
            if ($tParams[0] === 'orderby') {
                if ((strtolower($tParams[2]) === 'asc' || strtolower($tParams[2]) === 'desc' )) {
                    $tParams = array_slice($tParams, 3);
                } else {
                    $tParams = array_slice($tParams, 2);
                }
            } elseif ($tParams[0] === 'limit') {
                $tParams = array_slice($tParams, 2);
            } elseif ($tParams[0] === 'all') {
                $tParams = array_slice($tParams, 1);
            }

            if (isset($tParams[1]) === true && isset($tParams[0]) === true) {
                $criteria[$tParams[0]] = $tParams[1];
                $tParams = array_slice($tParams, 2);
            }
        }

        return $criteria;
    }

    /**
     * Renvoie une clause WHERE à partir des critères de sélection d'un ajax
     * @param  string $params la chaine de caractère contenant les parametres
     * @return builder
     */
    private static function getWhereFromFilters($filters, $builder, $order = false)
    {
        $arrayExclude = array(
                          'rows',
                          'page',
                          'nd',
                          'sord',
                          'sidx',
                          'source',
                          'sourceId',
                          '_search',
                          'searchField',
                         );
        $builder->where('1=1');

        if (array_key_exists('sidx', $filters) === true && $order === true) {
            $builder->orderBy('a.'.$filters['sidx'], $filters['sord']);
        }

        foreach ($filters as $champ => $val) {
            if (in_array($champ, $arrayExclude) === false) {
                if (strpos('&'.$val, '%') !== false || strpos($val, '?') !== false) {
                        $builder->andWhere("a.$champ like '$val'");
                } elseif (strpos('&'.$val, '>') === 1) {
                    $val = str_replace('>', '', $val);
                    $builder->andWhere("a.$champ > '$val'");
                } elseif (strpos('&'.$val, '=') === 1) {
                    $val = str_replace('=', '', $val);
                    $builder->andWhere("a.$champ = '$val'");
                } elseif (strpos('&'.$val, '<') === 1) {
                    $val = str_replace('<', '', $val);
                    $builder->andWhere("a.$champ < '$val'");
                } elseif ($val === 'NULL' || $val === 'null') {
                    $builder->andWhere("a.$champ is NULL");
                } elseif ($val === 'NOT NULL' || $val === 'not null') {
                    $builder->andWhere("a.$champ is NOT NULL");
                } else {
                    $builder->andWhere("a.$champ = '$val'");
                }
            }
        }

        return $builder;
    }

    /**
     * Renvoie une clause WHERE à partir des critères de sélection contenu dans l'URL
     * @param  string $params la chaine de caractère contenant les parametres
     * @return builder
     */
    public static function getWhereFromParams($params, $builder)
    {
        $tParams = explode('/', $params);
        $builder->where('1=1');
        array_pop($tParams);
        while (count($tParams) > 1 && $tParams[0] <> '') {
            if ($tParams[0] === 'orderby') {
                if ((strtolower($tParams[2]) === 'asc' || strtolower($tParams[2]) === 'desc' )) {
                    $tParams = array_slice($tParams, 3);
                } else {
                    $tParams = array_slice($tParams, 2);
                }
            } elseif ($tParams[0] === 'limit') {
                $tParams = array_slice($tParams, 2);
            } elseif ($tParams[0] === 'all') {
                $tParams = array_slice($tParams, 1);
            } else {
                if (isset($tParams[1]) === true && isset($tParams[0]) === true) {
                    $builder->andWhere("a.$tParams[0] = '$tParams[1]'");
                    $tParams = array_slice($tParams, 2);
                }
            }
        }

        return $builder;
    }

    /**
     * Renvoie un tableau contenant les parametres "limit" contenu dans l'URL
     * @param  string $params la chaine de caractère contenant les parametres
     * @return array         Un tableau avec la limite et la liste de filtre
     */
    public static function getLimitsFromParams($params)
    {
        $Limit   = array(
                    10,
                    '[10, 20, 30, 40]',
                   );
        $tParams = explode('/', $params);
        array_pop($tParams);
        while (count($tParams) > 1 && $tParams[0] <> '') {
            if ($tParams[0] === 'orderby' && isset($tParams[1]) === true) {
                if (isset($tParams[2]) === true && (strtolower($tParams[2]) === 'asc' or strtolower($tParams[2]) === 'desc' )) {
                    $tParams = array_slice($tParams, 3);
                } else {
                    $tParams = array_slice($tParams, 2);
                }
            } elseif ($tParams[0] === 'limit' && isset($tParams[1]) === true) {
                $lim1     = $tParams[1];
                $lim2     = ($lim1 * 2);
                $lim3     = ($lim1 * 3);
                $lim4     = ($lim1 * 4);
                $rowsList = "[$lim1, $lim2, $lim3, $lim4]";
                $Limit    = array(
                             $lim1,
                             $rowsList,
                            );
                return $Limit;
            } elseif ($tParams[0] === 'all') {
                $tParams = array_slice($tParams, 1);
            } else {
                if (isset($tParams[1]) === true && isset($tParams[0]) === true) {
                    $tParams = array_slice($tParams, 2);
                }
            }
        }

        return $Limit;
    }

    /**
     * Fabrique le "WHERE" issus des filtres choisis
     * @param entityManager $eManager
     * @param string $entity       le nom de l'entité
     * @param QueryBuilder $builder
     * @param string $searchField  Le champ recherché
     * @param string $searchString la valeur
     * @param string $searchOper   l'opérateur
     * @return   le QueryBuilder avec un where de plus
     */
    private static function addWhere($eManager, $entity, $builder, $searchField, $searchString, $searchOper)
    {
        $metadata = $eManager->getClassMetadata($entity);
        if ($metadata->getTypeOfField($searchField) === false) {
            return null;
        }

        $numeric = array(
                    'integer',
                    'double',
                    'boolean',
                   );
        $date    = array(
                    'date',
                    'datetime',
                   );

        $operator = array(
                     'eq' => ' = ',
                     'ne' => ' <> ',
                     'lt' => ' < ',
                     'le' => ' <= ',
                     'gt' => ' > ',
                     'ge' => ' >= ',

                     'bw' => ' LIKE ',
                     'bn' => ' NOT LIKE ',
                     'in' => ' IN ',
                     'ni' => ' NOT IN ',
                     'ew' => ' LIKE ',
                     'en' => ' NOT LIKE ',
                     'cn' => ' LIKE ',
                     'nc' => ' NOT LIKE ',
                     'nu' => ' IS NULL',
                     'nn' => ' IS NOT NULL',
                    );
        switch ($searchOper) {
            case 'bw':
            //'begins with'
                if (in_array($metadata->getTypeOfField($searchField), $numeric) === true) {
                    return self::getNativeQuery($eManager, $entity, $searchField.'::text', $searchString.'%', ' LIKE ');
                }

                if (in_array($metadata->getTypeOfField($searchField), $date) === true) {
                    return self::getNativeQuery($eManager, $entity, $searchField.'::text', $searchString.'%', ' LIKE ');
                }

                $searchString = $searchString.'%';
                $where        = 'u.'.$searchField." LIKE :$searchField";
                $builder->andWhere($where)->setParameter($searchField, $searchString);

                return $builder;

            case 'bn':
            //'does not begin with'
                if (in_array($metadata->getTypeOfField($searchField), $numeric) === true) {
                    return self::getNativeQuery($eManager, $entity, $searchField.'::text', $searchString.'%', ' NOT LIKE ');
                }

                $searchString = $searchString.'%';
                $where        = 'u.'.$searchField." NOT LIKE :$searchField";
                $builder->andWhere($where)->setParameter($searchField, $searchString);

                return $builder;

            case 'in':
            //'is in'
                $searchString = explode(',', $searchString);
                $where        = 'u.'.$searchField." IN (:$searchField)";
                $builder->andWhere($where)->setParameter($searchField, $searchString);

                return $builder;

            case 'ni':
            //'is not in'
                $searchString = explode(',', $searchString);
                $where        = 'u.'.$searchField." NOT IN ( : $searchField )";
                $builder->whereNotIn($where)->setParameter($searchField, $searchString);

                return $builder;

            case 'ew':
            //'ends with'
                if (in_array($metadata->getTypeOfField($searchField), $numeric) === true) {
                    return self::getNativeQuery($eManager, $entity, $searchField.'::text', '%'.$searchString, ' LIKE ');
                }

                $searchString = '%'.$searchString;
                $where        = 'u.'.$searchField." LIKE :$searchField";
                $builder->andWhere($where)->setParameter($searchField, $searchString);

                return $builder;

            case 'en':
            //'does not end with'
                if (in_array($metadata->getTypeOfField($searchField), $numeric) === true) {
                    return self::getNativeQuery($eManager, $entity, $searchField.'::text', '%'.$searchString, ' NOT LIKE ');
                }

                $searchString = '%'.$searchString;
                $where        = 'u.'.$searchField."  NOT LIKE :$searchField";
                $builder->andWhere($where)->setParameter($searchField, $searchString);

                return $builder;

            case 'cn':
            //'contains'
                if (in_array($metadata->getTypeOfField($searchField), $numeric) === true) {
                    return self::getNativeQuery($eManager, $entity, $searchField.'::text', '%'.$searchString.'%', ' LIKE ');
                }

                $searchString = '%'.$searchString.'%';
                $where        = 'u.'.$searchField." LIKE :$searchField";
                $builder->andWhere($where)->setParameter($searchField, $searchString);

                return $builder;

            case 'nc':
            //'does not contain'
                if (in_array($metadata->getTypeOfField($searchField), $numeric) === true) {
                    return self::getNativeQuery($eManager, $entity, $searchField.'::text', '%'.$searchString.'%', ' NOT LIKE ');
                }

                $searchString = '%'.$searchString.'%';
                $where        = 'u.'.$searchField."  NOT LIKE :$searchField";
                $builder->andWhere($where)->setParameter($searchField, $searchString);

                return $builder;

            default:
                $where = 'u.'.$searchField.' '.$operator[$searchOper].':'.$searchField;
                $builder->andWhere($where)->setParameter($searchField, $searchString);

                return $builder;
        }
    }

    /**
     * Fabrique une requete "native"
     * @param entityManager $eManager
     * @param string $entity       le nom de l'entité
     * @param QueryBuilder $builder
     * @param string $searchField  Le champ recherché
     * @param string $searchString la valeur
     * @param string $searchOper   l'opérateur
     * @return NativeQuery
     */
    private static function getNativeQuery($eManager, $entity, $searchField, $searchString, $searchOper)
    {
        $metadata = $eManager->getClassMetadata($entity);
        $table    = $metadata->getTableName();
        $col      = $metadata->getColumnName($searchField);
        if (strpos($searchField, '::') > 0) {
            list($field, $type) = explode('::', $searchField);
            $col = $metadata->getColumnName($field).'::'.$type;
        }

        $rsm = new ResultSetMappingBuilder($eManager);
        $rsm->addRootEntityFromClassMetadata($entity, 'u');
        // visiblement, on ne peut pas nommer les parametres
        $sql   = 'SELECT *  FROM '.$table.' WHERE '.$col.' '.$searchOper.' ?';
        $query = $eManager->createNativeQuery($sql, $rsm);
        $query->setParameter(1, $searchString);

        return $query;

    }

    /**
     * Fabrique le modèles des colonnes d'une grille
     * @param  array $data    tableau issu d'une sérialisation du résultat d'une requete
     * @param  entityManager $eManager
     * @param  string $entity  le nom de l'entité
     * @return string         le tableau au format json du modèle des colonnes
     */
    public static function getColumnModel($data, $eManager = null, $entity = null, $tableFieldsHidden = null)
    {
        $columnModel = '';
        if ($eManager !== null && $entity !== null) {

            $metadata = $eManager->getClassMetadata($entity);

            $allFields = array_merge($metadata->getFieldNames(), $metadata->getAssociationNames());

            $bundle_name = self::getBundleShortName($metadata->getName());
            $entity_name  = self::getName( $metadata->getName());
            $short_name = $bundle_name.':'.$entity_name;

            if (isset($tableFieldsHidden) === true and array_key_exists($short_name, $tableFieldsHidden) === true) {
                $selects = explode(',', $tableFieldsHidden[$short_name]);
                foreach ($selects as $sel) {
                    if (array_search(trim($sel), $allFields) == true) {
                        unset($allFields[array_search(trim($sel), $allFields)]);
                    }
                }
                $allFields = array_values($allFields);
            }

            foreach ($allFields as $champ) {
                if ($metadata->hasAssociation($champ)) {
                    if ($metadata->isSingleValuedAssociation($champ)) {
                        $columnModel .= "{ name: '".$champ."' , index: '".$champ."' , search: false },";
                    }
                } else {
                    $columnModel .= "{ name: '".$champ."' , index: '".$champ."', search: true },";
                }

            }

            return '['.substr($columnModel, 0, -1).']';
        }

        foreach ($data as $champ => $val) {
            if (is_array($val) === false) {
                $columnModel .= "{ name: '".$champ."' , index: '".$champ."', search: false },";
            }
        }

        return '['.substr($columnModel, 0, -1).']';
    }

}
