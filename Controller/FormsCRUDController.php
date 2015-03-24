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

            return SGNTwigCrudTools::getFormatJson($eManager, $bundle, $table, $filters, $params);
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
        $limits   = SGNTwigCrudTools::getLimitsFromParams($params);
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
        $fields   = $metadata->getFieldNames();
        $associations = $metadata->getAssociationNames();

        $tableFilters = $this->container->getParameter('sgn_forms.entities_filters');

        $columnModel = SGNTwigCrudTools::getColumnModel(array(), $eManager, $entity, $tableFilters);

        $keyAssoc = array();
        $colNames = array();
        foreach ($associations as $assoc) {
            if ($metadata->isSingleValuedAssociation($assoc) === true) {
                $keyAssoc[] = $assoc;
            } else {
                $colNames[] = $assoc;
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
        $options = array('*', $entity);
        foreach ($options as $option) {
            if (array_key_exists($option, $tableFilters) !== true) {
                continue;
            }
            if (isset($tableFilters[$option]['hidden'])) {
                $selects = explode(',', $tableFilters[$option]['hidden']);
                foreach ($selects as $sel) {
                    if (array_search('s.'.trim($sel), $allFields) !== false) {
                        unset($allFields[array_search('s.'.trim($sel), $allFields)]);
                    }
                }
                $allFields = array_values($allFields);
            }
        }

        $select  = implode(' , ', $allFields);
        $builder = $eManager->getRepository($entity)->createQueryBuilder('s')->select($select);
        $builder = SGNTwigCrudTools::getWhereFromParams($params, $builder);
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
            $$builder = SGNTwigCrudTools::getWhereFromParams($params, $builder);
            $query    = $builder->getQuery();
            // $sql      = $query->getSql();
            $count = $query->getSingleScalarResult();

            if ($count < $limit) {
                $limit = $count;
            }

            $collectionNames = $this->getCollectionNames($colNames, $bundle, $table);
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
                'columnModel'     => $columnModel,
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

        if ($obj === false || $obj === null) {
            throw $this->createNotFoundException('Aucun enr trouvé pour cet id : '.$id);
        }

        foreach ($metaData->fieldNames as $value) {
            $theValue = $obj->{'get'.ucfirst($value)}();
            if ($theValue === null) {
                $fields[$value] = '';
                continue;
            }
            $fields[$value] = $theValue;

            if ($metaData->fieldMappings[$value]['type'] === 'date' && is_object($theValue) === true) {
                $fields[$value]  = $theValue->format('Y-m-d');
                continue;
            }
            if ($metaData->fieldMappings[$value]['type'] === 'datetime' && is_object($theValue) === true) {
                $fields[$value]  = $theValue->format('Y-m-d H:i:s');
                continue;
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
                'table'           => $table,
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

                        $columnModel = SGNTwigCrudTools::getColumnModel($result[0]);
                    }
                } else {
                    $class           = $eManager->getClassMetadata($bundle.':'.$table)->getAssociationTargetClass($collection);
                    $columnModel     = SGNTwigCrudTools::getColumnModel(array(), $eManager, $class, $this->container->getParameter('sgn_forms.entities_filters'));
                }
            }
        }

        return array(
                'project'         => $bundle,
                'columnModel'     => $columnModel,
                'table'           => $table,
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
        foreach ($data as $champ) {
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

        $tableFilters = $this->container->getParameter('sgn_forms.entities_filters');
        $options = array('*', $bundleName.':'.$entity);
        $boolAudit = true;
        foreach ($options as $option) {
            if (isset($tableFilters[$option]) === true and isset($tableFilters[$option]['audit']) === true) {
                $boolAudit = $tableFilters[$option]['audit'];
            }
        }
        if ($auditManager->getMetadataFactory()->isAudited($tableAudit) === true and $boolAudit === true) {
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
}
