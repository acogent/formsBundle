<?php

namespace SGN\FormsBundle\Controller;

use Doctrine\ORM\Mapping\ClassMetadataInfo;
use Doctrine\ORM\Query\ResultSetMapping;
use Doctrine\ORM\Query\ResultSetMappingBuilder;
use SGN\FormsBundle\Utils\SGNTwigCrudTools;
use SGN\FormsBundle\Utils\Serializor;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Template;
use Sensio\Bundle\GeneratorBundle\Command\Validators;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * @SuppressWarnings(PHPMD.StaticAccess)
 */
class FormsCRUDController extends Controller
{


    /**
     *
     * @Route("/{bundle}/{table}/{format}/show/{params}", requirements= { "params"=".+"})
     * @Route("/{bundle}/{table}/{format}/show/" )
     * @Route("/{bundle}/{table}" )
     * @Route("/{bundle}/" )
     * @Route("/" )
     *
     * @Template()
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function showAction($bundle = 'x', $table = 'x', $format = 'html', $params = 'limit/10')
    {
        if ($bundle === 'x') {
            $bundles = $this->container->getParameter('sgn_forms.bundles');
            $bundle  = $bundles[0];
        }

        if (strpos($bundle, 'Bundle') === false) {
            $bundle .= 'Bundle';
        }

        if ($table === 'x') {
            $tables = $this->container->getParameter('sgn_forms.bestof_entity');
            foreach ($tables as $ta) {
                if (substr($ta, 0, strpos($ta, '.')) === $bundle) {
                    $table = substr($ta, (strpos($ta, '.') + 1));
                    break;
                }
            }
        }

        $eManager = $this->getDoctrine()->getManager($this->container->getParameter('sgn_forms.orm'));
        // c'est pour retourner des données
        if ($format === 'json') {
            $request = $this->getRequest();
            $filters = $request->query->all();

            return $this->getFormatJson($eManager, $bundle, $table, $filters, $params);
        }

        // c'est juste pour construire l'interface et donner des param à la grille
        if ($format === 'html') {
            return $this->getFormatHtml($eManager, $bundle, $table, $params);
        }

        throw $this->createNotFoundException('Le produit n\'existe pas');

    }


    /**
     * Renvoie les éléments utiles à la construction HTML de la page
     * @param  Entity Manager $eManager
     * @param  string $bundle Le nom du bundle
     * @param  string $table
     * @param  string $params les paramtres contenus dans l'URL
     * @return array         Les éléments utiles à la page
     *
     * @SuppressWarnings(PHPMD.StaticAccess)
     */
    private function getFormatHtml($eManager, $bundle, $table, $params)
    {
        $request  = $this->getRequest();
        $entity   = $bundle.':'.$table;
        $limits   = $this->getLimitsFromParams($params);
        $limit    = $limits[0];
        $rowsList = $limits[1];
        // pour les liens de droite
        $tables = $this->container->getParameter('sgn_forms.bestof_entity');
        foreach ($tables as $ta) {
            if (substr($ta, 0, strpos($ta, '.')) === $bundle) {
                $bestofEntity[] = substr($ta, (strpos($ta, '.') + 1));
            }
        }

        $tabEntities = SGNTwigCrudTools::getMenuTabEntities($this, $bundle, $this->container->getParameter('sgn_forms.select_entity'));

        // Pour jQgrid
        $metadata = $eManager->getClassMetadata($entity);
        $fields   = array_keys($metadata->fieldMappings);
        $associationMappings = $metadata->associationMappings;

        $keyAssoc = array();
        $colNames = array();
        foreach ($associationMappings as $key => $assoc) {
            if (isset ($assoc['joinColumns']) === true) {
                $keyAssoc[] = $key;
            } else {
                $colNames[$key] = $assoc;
            }
        }

        $fields = array_unique(array_merge($fields, $keyAssoc));
        foreach ($fields as $field) {
            if (in_array($field, $keyAssoc) === true) {
                $sFields[] = 'IDENTITY(s.'.$field.') as '.$field;
            } else {
                $sFields[] = 's.'.trim($field);
            }
        }

        $allFields = $sFields;
        // pour personnaliser les tables jQGrid
        $tableFields = $this->container->getParameter('sgn_forms.entities_fields');
        if (array_key_exists($entity, $tableFields) === true) {
            $selects = explode(',', $tableFields[$entity]);
            foreach ($selects as $sel) {
                $sels[] = 's.'.trim($sel);
            }

            $allFields = array_unique(array_merge($sels, $sFields));
        }

        $tableFieldsHidden = $this->container->getParameter('sgn_forms.entities_fields_hidden');
        if (array_key_exists($entity, $tableFieldsHidden) === true) {
            $selects = explode(',', $tableFieldsHidden[$entity]);
            foreach ($selects as $sel) {
                if (array_search('s.'.trim($sel), $allFields) === true) {
                    unset($allFields[array_search('s.'.trim($sel), $allFields)]);
                }
            }

            $allFields = array_values($allFields);
        }

        $select  = implode(' , ', $allFields);
        $builder = $eManager->getRepository($entity)->createQueryBuilder('s')->select($select);
        $builder = $this->getWhereFromParams($params, $builder);
        $query   = $builder->getQuery();
        $query->setMaxResults($limit);

        if (true === $this->get('security.context')->isGranted('ROLE_ADMIN')) {
            $request = $this->getRequest();
            $request->getSession()->getFlashBag()->add('notice', $query->getSQL());
        }

       // $sql     = $query->getSql(); var_dump($sql);
        $result = $query->getResult();

        if ($result !== false) {
            $builder  = $eManager->getRepository($entity)->createQueryBuilder('a')->select('count(a)');
            $$builder = $this->getWhereFromParams($params, $builder);
            $query    = $builder->getQuery();
            // $sql      = $query->getSql();
            $count = $query->getSingleScalarResult();

            if ($count < $limit) {
                $limit = $count;
            }

            $collectionNames = $this->getCollectionNames($colNames, $bundle, $table);
            $columnModel     = $this->getColumnModel($result[0], $eManager, $entity);
            $urlShowOne      = $this->generateUrl('sgn_forms_formscrud_showone', array('bundle' => $bundle, 'table' => $table, 'id' => '#'), true);
            $urlEdit         = $this->generateUrl('sgn_forms_formscrud_edit', array('bundle' => $bundle, 'table' => $table, 'id' => '#'), true);
            $urlDelete       = $this->generateUrl('sgn_forms_formscrud_delete', array('bundle' => $bundle, 'table' => $table, 'id' => '#'));
            $urlNew          = $this->generateUrl('sgn_forms_formscrud_new', array('bundle' => $bundle, 'table' => $table), true);

            return array(
                    'project'         => $bundle,
                    'columnModel'     => $columnModel,
                    'collectionNames' => $collectionNames,
                    'entity'          => $table,
                    'count'           => $count,
                    'bestof_entity'   => $bestofEntity,
                    'entities'        => $tabEntities,
                    'limit'           => $limit,
                    'rowsList'        => $rowsList,
                    'url_new'         => $urlNew,
                    'url_showone'     => $urlShowOne,
                    'url_edit'        => $urlEdit,
                    'url_delete'      => $urlDelete,
                    'params'          => $params,
                   );
        }

        return array(
                'project'         => $bundle,
                'columnModel'     => '[]',
                'collectionNames' => null,
                'entity'          => $table,
                'count'           => 0,
                'bestof_entity'   => $bestofEntity,
                'entities'        => $tabEntities,
                'limit'           => $limit,
                'rowsList'        => $rowsList,
                'url_new'         => null,
                'url_showone'     => null,
                'url_edit'        => null,
                'url_delete'      => null,
                'params'          => $params,
               );

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
    private function getFormatJson($eManager, $bundle, $table, $filters, $params)
    {
        $search      = 'false';
        $searchField = 'false';
        $entity      = $bundle.':'.$table;

        if (isset($filters['_search']) === true) {
            $search = $filters['_search'];
        }

        if (isset($filters['searchField']) === true) {
            $search = $filters['searchField'];
        }

        // on a lancé une recherche par la barre de recherche
        if ($search === 'true' && $searchField === 'false') {
            return $this->searchBar($entity, $filters);
        }

        // On a lancé une recherche par la boite de dialogue
        if ($search === 'true' && $searchField !== 'false') {
            return $this->searchDialog($eManager, $entity, $filters);
        }

        // on n'a pas lancé de recherche
        return $this->noSearch($eManager, $entity, $filters, $params);
    }


    private function noSearch($eManager, $entity, $filters, $params)
    {
        $totalPages = 0;
        $orderBy    = array();
        $criteria   = $this->getCriteriaFromParams($params);
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
        $builder = $this->getWhereFromParams($params, $builder);
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


    private function searchDialog($eManager, $entity, $filters)
    {
        $result = array();
        $page   = 0;

        $searchField  = $filters['searchField'];
        $searchString = $filters['searchString'];
        $searchOper   = $filters['searchOper'];
        if (isset($filters['page']) === true) {
            $page = $filters['page'];
        }

        $repository = $this->getDoctrine()->getRepository($entity);
        $builder    = $repository->createQueryBuilder('u')->where('1  = 1');
        $builder    = $this->addWhere($eManager, $entity, $builder, $searchField, $searchString, $searchOper);
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


    private function searchBar($entity, $filters)
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
        $builder = $this->getWhereFromFilters($filters, $builder);
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
        $builder = $this->getWhereFromFilters($filters, $builder, true);

        $query = $builder->getQuery();
        $query->setFirstResult($start);
        $query->setMaxResults($limit);

        $data = $query->getResult();


        $result['debug']   = print_r(array('sql' => $query->getSQL(), 'parameters' => $criteria), true);
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
    private function getCriteriaFromParams($params)
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
    private function getWhereFromFilters($filters, $builder, $order = false)
    {
        $array_exclude = array(
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
                if (strpos('&'.$val, '%') === true || strpos($val, '?') === tr) {
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
                }

                $builder->andWhere("a.$champ = '$val'");
            }
        }

        return $builder;
    }


    /**
     * Renvoie une clause WHERE à partir des critères de sélection contenu dans l'URL
     * @param  string $params la chaine de caractère contenant les parametres
     * @return builder
     */
    private function getWhereFromParams($params, $builder)
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
     * Renvoie un tableau contenant les paramètres "order by" contenn dans l'URL
     * @param  string $params la chaine de caractère contenant les parametres
     * @return array         Un tableau avec les paramètres de l'order by
     */
    private function getOrderByFromParams($params)
    {
        $orderBy = array();
        $tParams = explode('/', $params);
        array_pop($tParams);
        while (count($tParams) > 1 && $tParams[0] <> '') {
            if ($tParams[0] === 'orderby') {
                if ((strtolower($tParams[2]) === 'asc' || strtolower($tParams[2]) === 'desc' )) {
                        $orderBy[$tParams[1]] = $tParams[2];
                        $tParams = array_slice($tParams, 3);
                } else {
                        $orderBy[$tParams[1]] = 'asc';
                        $tParams = array_slice($tParams, 2);
                }

                    return $orderBy;
            } elseif ($tParams[0] === 'limit') {
                $tParams = array_slice($tParams, 2);
            } elseif ($tParams[0] === 'all') {
                $tParams = array_slice($tParams, 1);
            } else {
                if (isset($tParams[1]) === true && isset($tParams[0]) === true) {
                    $tParams = array_slice($tParams, 2);
                }
            }
        }

        return $orderBy;
    }


    /**
     * Renvoie un tableau contenant les parametres "limit" contenu dans l'URL
     * @param  string $params la chaine de caractère contenant les parametres
     * @return array         Un tableau avec la limite et la liste de filtre
     */
    private function getLimitsFromParams($params)
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
     *
     * @Route("/{bundle}/{table}/new/")
     * @Route("/{bundle}/{table}/new/{ajax}")
     *
     * @Template()
     */
    public function newAction($bundle, $table, Request $request, $ajax = '')
    {
        $classBundleName  = Validators::validateBundleName($bundle);
        $classBundleValid = $this->get('Kernel')->getBundle($classBundleName);
        $classDir         = $classBundleValid->getNamespace();

        $class = $classDir.'\Entity\\'.$table;
        $type  = $classDir.'\Form\\'.$table.'Type';

        if ($this->container->hasParameter('sgn_forms.forms.'.$bundle) === true) {
            $formBundle = $this->container->getParameter('sgn_forms.forms.'.$bundle);
            if ($formBundle !== '@service') {
                $formBundleName  = Validators::validateBundleName($formBundle);
                $formBundleValid = $this->get('Kernel')->getBundle($formBundleName);
                $formDir         = $formBundleValid->getNamespace();
                $type            = $formDir.'\Form\\'.$table.'Type';
            }
        }

        $obj  = new $class();
        $form = $this->createForm(
            $type != '' ? new $type() : strtolower($table).'_type',
            $obj,
            array(
             'action' => $this->generateUrl(
                 'sgn_forms_formscrud_new',
                 array(
                  'bundle' => $bundle,
                  'table'  => $table,
                 )
             ),
            )
        );

        $form->handleRequest($request);
        $eManager = $this->getDoctrine()->getManager($this->container->getParameter('sgn_forms.orm'));

        // Cas 'normal'
        if ($ajax === '' && $form->isValid() === true) {
            $eManager->persist($obj);
            $eManager->flush();
            $request->getSession()->getFlashBag()->add('info', 'Enregistrement ajouté.');

            return $this->redirect($this->generateUrl(
                'sgn_forms_formscrud_show',
                array(
                 'bundle' => $bundle,
                 'table'  => $table,
                )
            ));
        }

        // $ajax === 'dynamic' => le formulaire est modifié dynamiquement.
        if ($ajax !== 'dynamic' && $form->isValid() === true) {
            $eManager->persist($obj);
            $eManager->flush();
            $request->getSession()->getFlashBag()->add('info', 'Enregistrement ajouté.');

            // Si on valide le formulaire par Ajax, la redirection se fait en JQuery.
            return new Response($this->generateUrl(
                'sgn_forms_formscrud_show',
                array(
                 'bundle' => $bundle,
                 'table'  => $table,
                )
            ));
        }

        // ajax pas valide
        if ($ajax === 'validate' && $form->isValid() === false) {
            return array( 'form' => $form->createView() );
        }

        // Pb des formulaires avec file et ajax, on est obligé de bricoler !!!!
        // @todo : voir si on ne peux pas faire mieux !!
        if ($request->isXmlHttpRequest() === true) {
            $errors = $form->get('file')->getErrors();
            $request->getSession()->getFlashBag()->add('info', 'Fichier ajouté.');
            $response = new Response();
            $output   = array(
                         'success' => false,
                         'errors'  => $errors[0]->getMessage(),
                        );
            $response->headers->set('Content-Type', 'application/json');
            $response->setContent(json_encode($output));

            return $response;
        }

        return array( 'form' => $form->createView() );
    }


    /**
     *
     * @Route("/{bundle}/{table}/edit/{id}/")
     * @Route("/{bundle}/{table}/edit/{id}/{ajax}")
     *
     * @Template()
     */
    public function editAction($bundle, $table, $id, Request $request, $ajax = '')
    {
        $classBundleName  = Validators::validateBundleName($bundle);
        $classBundleValid = $this->get('Kernel')->getBundle($classBundleName);
        $classDir         = $classBundleValid->getNamespace();

        // $class = $classDir.'\Entity\\'.$table;
        $type = $classDir.'\Form\\'.$table.'Type';

        if ($this->container->hasParameter('sgn_forms.forms.'.$bundle) === true) {
            $formBundle = $this->container->getParameter('sgn_forms.forms.'.$bundle);
            if ($formBundle !== '@service') {
                $formBundleName  = Validators::validateBundleName($formBundle);
                $formBundleValid = $this->get('Kernel')->getBundle($formBundleName);
                $formDir         = $formBundleValid->getNamespace();
                $type            = $formDir.'\Form\\'.$table.'Type';
            }
        }

        $eManager = $this->getDoctrine()->getManager($this->container->getParameter('sgn_forms.orm'));
        $obj      = $eManager->getRepository($bundle.':'.$table)->findOneById($id);
        if ($obj === false) {
            throw $this->createNotFoundException('Aucun enr trouvé pour cet id : '.$id);
        }

        $form = $this->createForm(
            $type !== '' ? new $type() : strtolower($table).'_type',
            $obj,
            array(
             'action' => $this->generateUrl(
                 'sgn_forms_formscrud_edit',
                 array(
                  'bundle' => $bundle,
                  'table'  => $table,
                  'id'     => $id,
                 )
             ),
            )
        );

        $form->handleRequest($request);

        // Cas 'normal'
        if ($ajax === '' && $form->isValid() === true) {
            $eManager->flush();
            $request->getSession()->getFlashBag()->add('info', 'Enregistrement modifé.');

            return $this->redirect($this->generateUrl(
                'sgn_forms_formscrud_show',
                array(
                 'bundle' => $bundle,
                 'table'  => $table,
                )
            ));
        }

        // $ajax === 'dynamic' => le formulaire est modifié dynamiquement.
        if ($ajax !== 'dynamic' && $form->isValid() === true) {
            $eManager->flush();
            $request->getSession()->getFlashBag()->add('info', 'Enregistrement modifé.');
            // Si on valide le formulaire par Ajax, la redirection se fait en JQuery.
            return new Response($this->generateUrl(
                'sgn_forms_formscrud_show',
                array(
                 'bundle' => $bundle,
                 'table'  => $table,
                )
            ));
        }

        return array(
                'form' => $form->createView(),
               );
    }


    /**
     *
     * @Route("/{bundle}/{table}/showone/{id}/")
     *
     * @Template()
     */
    public function showoneAction($bundle, $table, $id, Request $request)
    {
        $eManager = $this->getDoctrine()->getManager($this->container->getParameter('sgn_forms.orm'));
        $obj      = $eManager->getRepository($bundle.':'.$table)->findOneById($id);
        $metaData = $eManager->getClassMetadata($bundle.':'.$table);

        if ($obj === false) {
            throw $this->createNotFoundException('Aucun enr trouvé pour cet id : '.$id);
        }

        foreach ($metaData->fieldNames as $value) {
            $fields[$value] = $obj->{'get'.ucfirst($value)}();

            if ($metaData->fieldMappings[$value]['type'] === 'date') {
                if (! $obj->{'get'.ucfirst($value)}() ) $fields[$value]  = '';
                else $fields[$value]  = $obj->{'get'.ucfirst($value)}()->format('Y-m-d');
            } elseif ($metaData->fieldMappings[$value]['type'] === 'datetime') {
                if (! $obj->{'get'.ucfirst($value)}() ) $fields[$value]  = '';
                else $fields[$value]  = $obj->{'get'.ucfirst($value)}()->format('Y-m-d H:i:s');
            }
        }

        return array('obj' => $fields);
    }


    /**
     *
     * @Route("/{bundle}/{table}/delete/{id}/")
     *
     * @Template()
     */
    public function deleteAction($bundle, $table, $id, Request $request)
    {
        $classBundleName  = Validators::validateBundleName($bundle);
        $classBundleValid = $this->get('Kernel')->getBundle($classBundleName);
        $classDir         = $classBundleValid->getNamespace();

        $type = $classDir.'\Form\\'.$table.'Type';
        if ($this->container->hasParameter('sgn_forms.forms.'.$bundle) === true) {
            $formBundle = $this->container->getParameter('sgn_forms.forms.'.$bundle);
            if ($formBundle !== '@service') {
                $formBundleName  = Validators::validateBundleName($formBundle);
                $formBundleValid = $this->get('Kernel')->getBundle($formBundleName);
                $formDir         = $formBundleValid->getNamespace();
                $type            = $formDir.'\Form\\'.$table.'Type';
            }
        }

        $eManager = $this->getDoctrine()->getManager($this->container->getParameter('sgn_forms.orm'));
        $obj      = $eManager->getRepository($bundle.':'.$table)->findOneById($id);

        $form = $this->createFormBuilder($obj)->setAction($this->generateUrl('sgn_forms_formscrud_delete', array('bundle' => $bundle, 'table' => $table, 'id' => $id)))->getForm();
        $form->handleRequest($request);

        // Valider un objet qui va etre détruit ;-) On maintient à cause des relations OneToMany & co
        // On considere donc que les données à supprimer sont bonnes
        if ($form->isValid() === true) {
            $entity = $eManager->getRepository($bundle.':'.$table)->findOneById($id);

            if ($entity === false) {
                throw $this->createNotFoundException('Unable to find entity.');
            }

            $eManager->remove($entity);
            $eManager->flush();

            return $this->redirect($this->generateUrl(
                'sgn_forms_formscrud_show',
                array(
                 'bundle' => $bundle,
                 'table'  => $table,
                )
            ));
        }

        return array(
                'form' => $form->createView(),
               );
    }


    /**
     * Permet de récupérer une collection d'un objet quand on le sélectionne dans une grille
     * @Route("/{bundle}/{table}/{format}/select/JQG/{collection}/" )
     *
     * @Template()
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function selectJqGridAction($bundle, $table, $format, $collection)
    {
        $request         = $this->getRequest();
        $filters         = $request->query->all();
        $columnModel     = '[]';
        $columnNames     = '';
        $collectionNames = null;
        $result          = array();
        $datas           = array();
        $limit           = 10;
        $page            = 0;
        $sourceId        = null;
        $count           = 0;
        $totalPages      = 0;

        if (isset($filters['rows']) === true) {
            $limit = $filters['rows'];
        }

        if (isset($filters['page']) === true) {
            $page = $filters['page'];
        }

        if (isset($filters['sourceId']) === true) {
            $sourceId = $filters['sourceId'];
        }

        $eManager = $this->getDoctrine()->getManager($this->container->getParameter('sgn_forms.orm'));
        if (isset($sourceId) === true && $sourceId !== 'undefined') {
            if ($collection === 'Audit') {
                $datas = $this->getAudit($bundle, $table, $sourceId);
                $count = count($datas);
                if ($count > 0) {
                    for ($i = 0; $i < $limit; $i++) {
                        $index = ($i + ($page * $limit) - $limit);
                        if ($index === $count) {
                            break;
                        }

                        $result[] = $datas[$index];
                    }

                    $columnNames     = $this->getColumnNames($result[0]);
                    $collectionNames = $this->getCollectionNames($result[0], $bundle, $table);
                    $columnModel     = $this->getColumnModel($result[0]);
                }
            }

            if ($collection !== 'Audit') {
                $enr        = $eManager->getRepository($bundle.':'.$table)->findOneById($sourceId);
                $methodName = 'get'.$collection;
                if (method_exists($enr, $methodName) === true) {
                    $datas = $enr->$methodName();
                }

                $count = count($datas);
                if ($count > 0) {
                    for ($i = 0; $i < $limit; $i++) {
                        $index = ($i + ($page * $limit) - $limit);
                        if ($index === $count) {
                            break;
                        }

                        $result[] = Serializor::toArray($datas[$index]);
                    }

                    $columnNames     = $this->getColumnNames($result[0]);
                    $collectionNames = $this->getCollectionNames($result[0], $bundle, $table);
                    $columnModel     = $this->getColumnModel($result[0]);
                }
            }
        }

        if ($format === 'json') {
            if ($count > 0 && $limit > 0) {
                $totalPages = ceil($count / $limit);
            }

            $start = ($limit * $page - $limit);
            if ($start < 0) {
                $start = 0;
            }

            $res            = array();
            $res['page']    = $page;
            $res['records'] = $count;
            $res['total']   = $totalPages;
            $res['rows']    = $result;

            $json     = json_encode($res);
            $response = new Response($json);
            $response->headers->set('Content-Type', 'application/json');

            return $response;
        }

        return array(
                'project'         => $bundle,
                'columnModel'     => $columnModel,
                'table'           => $table,
                'collectionNames' => $collectionNames,
                'entity'          => $collection,
                'limit'           => 10,
                'rowsList'        => 1,
               );

    }


    private function getAudit($bundle, $table, $ident)
    {
        $auditManager = $this->container->get('simplethings_entityaudit.manager');
        $bundleName   = Validators::validateBundleName($bundle);
        $bundleValid  = $this->get('Kernel')->getBundle($bundleName);
        $dir          = $bundleValid->getNamespace();
        $tableAudit   = $dir.'\Entity\\'.$table;

        if ($auditManager->getMetadataFactory()->isAudited($tableAudit) === false) {
            return array();
        }

        $eManager  = $this->getDoctrine()->getManager($this->container->getParameter('sgn_forms.orm'));
        $entity    = $bundle.':'.$table;
        $class     = $eManager->getClassMetadata($entity);
        $champIdBd = 'id';
        if (isset($class->fieldMappings['id']['columnName']) === true) {
            $champIdBd = $class->fieldMappings['id']['columnName'];
        }

        $tableName = $class->table['name'].'_audit';

        $query  = 'SELECT * FROM '.$tableName.' e WHERE e.'.$champIdBd.' = '.$ident.' ORDER BY e.rev DESC';
        $result = $eManager->getConnection()->fetchAll($query);

        return $result;
    }


    /**
     * Permet de créer la grille d'une collection quand on sélectionne un objet dans une grille
     * @Route("/{bundle}/{table}/{format}/select/JQG/{collection}/{id}/" )
     *
     * @Template("SGNFormsBundle:FormsCRUD:selectJqGrid.html.twig")
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function createJqGridAction($bundle, $table, $format, $collection, $id = 0)
    {
        $request         = $this->getRequest();
        $columnModel     = '[]';
        $collectionNames = null;
        $datas           = array();
        if ($request->isXmlHttpRequest() === true) {
            $eManager = $this->getDoctrine()->getManager($this->container->getParameter('sgn_forms.orm'));
            if (isset($id) === true && $id > 0) {
                if ($collection === 'Audit') {
                    $datas = $this->getAudit($bundle, $table, $id);
                    if (count($datas) > 0) {
                        foreach ($datas as $data) {
                            $result[] = $data;
                        }

                        $collectionNames = $this->getCollectionNames($result[0], $bundle, $table);
                        $columnModel     = $this->getColumnModel($result[0]);
                    }
                } else {
                    $class           = $eManager->getClassMetadata($bundle.':'.$table)->getAssociationTargetClass($collection);
                    $collectionNames = $this->getCollectionNames(array(), $bundle, $table);
                    $columnModel     = $this->getColumnModel(array(), $eManager, $class);
                }
            }
        }

        return array(
                'project'         => $bundle,
                'columnModel'     => $columnModel,
                'table'           => $table,
                'collectionNames' => $collectionNames,
                'entity'          => $collection,
                'limit'           => 10,
                'rowsList'        => 1,
               );

    }


    /**
     * Renvoie un tableau contenant les URL utiles à la fabrication des sous-formulaires
     * @param  array $data    tableau issu d'une sérialisation du résultat d'une requete
     * @param  string $project le nom du projet
     * @param  string $entity  le nom de l'entité
     * @return array          le tableau avec les URL
     */
    private function getCollectionNames($data, $project, $entity)
    {
        $collectionNames = array();
        $auditManager    = $this->container->get('simplethings_entityaudit.manager');

        $bundleName  = Validators::validateBundleName($project);
        $bundleValid = $this->get('Kernel')->getBundle($bundleName);
        $dir         = $bundleValid->getNamespace();
        $tableAudit  = $dir.'\Entity\\'.$entity;
        foreach ($data as $champ => $val) {
            if (is_array($val) === true) {
                $url = $this->get('router')->generate(
                    'sgn_forms_formscrud_createjqgrid',
                    array(
                     'bundle'     => $project,
                     'format'     => 'html',
                     'table'      => $entity,
                     'collection' => $champ,
                     'id'         => '#',
                    ),
                    true
                );
                $collectionNames[$champ] = $url;
            }
        }

        if ($auditManager->getMetadataFactory()->isAudited($tableAudit) === true) {
            $url = $this->get('router')->generate(
                'sgn_forms_formscrud_createjqgrid',
                array(
                 'bundle'     => $project,
                 'format'     => 'html',
                 'table'      => $entity,
                 'collection' => 'Audit',
                 'id'         => '#',
                ),
                true
            );
            $collectionNames['Audit'] = $url;
        }

        return $collectionNames;
    }


    /**
     * Fabrique le tableau contenant les noms des champs de la grille sans les relations oneToMany
     * @param  array $data tableau issu d'une sérialisation du résultat d'une requete
     * @return array       la liste des champs sous forme de tableau
     */
    private function getColumnNames($data)
    {
        $columnNames = array();
        foreach ($data as $champ => $val) {
            if (is_array($val) === false) {
                $columnNames[] = $champ;
            }
        }

        return $columnNames;
    }


    /**
     * Fabrique le modèles des colonnes d'une grille
     * @param  array $data    tableau issu d'une sérialisation du résultat d'une requete
     * @param  entityManager $eManager
     * @param  string $entity  le nom de l'entité
     * @return string         le tableau au format json du modèle des colonnes
     */
    private function getColumnModel($data, $eManager = null, $entity = null)
    {
       // $columnModel = "{name:'act',index:'act', width:75,sortable:false},";
        $columnModel = '';
        if ($eManager !== null && $entity !== null) {
            $metadata = $eManager->getClassMetadata($entity);
            foreach ($metadata->getFieldNames() as $champ) {
                $columnModel .= "{ name: '".$champ."' , index: '".$champ."', search: true },";
            }

            foreach ($metadata->getAssociationNames() as $champ) {
                if ($metadata->isSingleValuedAssociation($champ) === true) {
                    $columnModel .= "{ name: '".$champ."' , index: '".$champ."' , search: false },";
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
    private function addWhere($eManager, $entity, $builder, $searchField, $searchString, $searchOper)
    {
        $metadata = $eManager->getClassMetadata($entity);
        if ($metadata->getTypeOfField($searchField) === false) {
            return null;
            //return $this->addWhereAssoc($eManager, $entity, $builder, $searchField, $searchString, $searchOper);
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
                    return $this->getNativeQuery($eManager, $entity, $searchField.'::text', $searchString.'%', ' LIKE ');
                }

                if (in_array($metadata->getTypeOfField($searchField), $date) === true) {
                    return $this->getNativeQuery($eManager, $entity, $searchField.'::text', $searchString.'%', ' LIKE ');
                }

                $searchString = $searchString.'%';
                $where        = 'u.'.$searchField." LIKE :$searchField";
                $builder->andWhere($where)->setParameter($searchField, $searchString);

                return $builder;

            case 'bn':
            //'does not begin with'
                if (in_array($metadata->getTypeOfField($searchField), $numeric) === true) {
                    return $this->getNativeQuery($eManager, $entity, $searchField.'::text', $searchString.'%', ' NOT LIKE ');
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
                    return $this->getNativeQuery($eManager, $entity, $searchField.'::text', '%'.$searchString, ' LIKE ');
                }

                $searchString = '%'.$searchString;
                $where        = 'u.'.$searchField." LIKE :$searchField";
                $builder->andWhere($where)->setParameter($searchField, $searchString);

                return $builder;

            case 'en':
            //'does not end with'
                if (in_array($metadata->getTypeOfField($searchField), $numeric) === true) {
                    return $this->getNativeQuery($eManager, $entity, $searchField.'::text', '%'.$searchString, ' NOT LIKE ');
                }

                $searchString = '%'.$searchString;
                $where        = 'u.'.$searchField."  NOT LIKE :$searchField";
                $builder->andWhere($where)->setParameter($searchField, $searchString);

                return $builder;

            case 'cn':
            //'contains'
                if (in_array($metadata->getTypeOfField($searchField), $numeric) === true) {
                    return $this->getNativeQuery($eManager, $entity, $searchField.'::text', '%'.$searchString.'%', ' LIKE ');
                }

                $searchString = '%'.$searchString.'%';
                $where        = 'u.'.$searchField." LIKE :$searchField";
                $builder->andWhere($where)->setParameter($searchField, $searchString);

                return $builder;

            case 'nc':
            //'does not contain'
                if (in_array($metadata->getTypeOfField($searchField), $numeric) === true) {
                    return $this->getNativeQuery($eManager, $entity, $searchField.'::text', '%'.$searchString.'%', ' NOT LIKE ');
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
     * Fabrique le "WHERE" pour une association
     * @param entityManager $eManager
     * @param string $entity       le nom de l'entité
     * @param QueryBuilder $builder
     * @param string $searchField  Le champ recherché
     * @param string $searchString la valeur
     * @param string $searchOper   l'opérateur
     * @return   le QueryBuilder avec un where de plus
     */
/*    private function addWhereAssoc($eManager, $entity, $builder, $searchField, $searchString, $searchOper)
    {
        // ???? return null !!
        $query     = null;
        // $metadata  = $eManager->getClassMetadata($entity);
        // $assoc     = $metadata->getAssociationMapping($searchField);
        // $joinClass = $assoc['fieldName'];
        return $query;
    }
*/

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
    private function getNativeQuery($eManager, $entity, $searchField, $searchString, $searchOper)
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


}
