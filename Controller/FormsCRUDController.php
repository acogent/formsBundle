<?php

namespace SGN\FormsBundle\Controller;

use SGN\FormsBundle\Utils\SGNTwigCrudTools;
use SGN\FormsBundle\Utils\Serializor;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Template;
use Sensio\Bundle\GeneratorBundle\Command\Validators;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Form\Form;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Doctrine\ORM\EntityManager;

/**
 * @SuppressWarnings(PHPMD.StaticAccess)
 */
class FormsCRUDController extends Controller
{


    /**
     * @Route("/{table}/{format}/show/{params}", requirements= { "params"=".+"})
     * @Route("/{table}/{format}/show/" )
     * @Route("/{table}/" )
     * @Route("/" )
     *
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function showAction(Request $request, $table = 'x', $format = 'html', $params = 'limit/10')
    {
        if ($table === 'x') {
            $tables = $this->container->getParameter('sgn_forms.bestof_entity');
            foreach ($tables as $ta) {
                $table = $ta;
                break;
            }
        }

        $configTable = $this->getConfigFromtable($table);
        $eManager    = $this->getDoctrine()->getManager($this->container->getParameter('sgn_forms.orm'));

        // c'est pour retourner des données en json
        if ($format === 'json') {
            $filters = $request->query->all();

            return SGNTwigCrudTools::getFormatJson($eManager, $configTable, $filters, $params);
        }

        // c'est juste pour construire l'interface et donner des param à la grille
        if ($format === 'html') {
            $tabResult = $this->getFormatHtml($request, $eManager, $configTable, $params);

            return $this->render('SGNFormsBundle:FormsCRUD:show.html.twig', $tabResult);
        }

        throw $this->render('SGNFormsBundle:FormsCRUD:show.html.twig', $this->createNotFoundException('Le produit n’existe pas'));
    }

    /**
     * @Route("/{table}/new/")
     * @Route("/{table}/new/{ajax}/")
     *
     * @Template()
     */
    public function newAction($table, Request $request, $ajax = '')
    {
        $configTable = $this->getConfigFromtable($table);
        $class       = $configTable['meta']->name;
        $type        = $configTable['type'];
        $bundle      = $configTable['bundle'];

        if ($this->container->hasParameter('sgn_forms.forms.'.$bundle) === true) {
            $formBundle = $this->container->getParameter('sgn_forms.forms.'.$bundle);
            if ($formBundle !== '@service') {
                $formBundleName  = Validators::validateBundleName($formBundle);
                $formBundleValid = $this->get('Kernel')->getBundle($formBundleName);
                $formDir         = $formBundleValid->getNamespace();
                $type            = $formDir.'\Form\\'.$table.'Type';
            }
        }

        $obj = new $class();

        $form = $this->createForm(
            $type !== '' ? new $type() : strtolower($table).'_type',
            $obj,
            array(
             'action' => $this->generateUrl(
                 'sgn_forms_formscrud_new',
                 array('table' => $table)
             ),
            )
        );

        return $this->formRequestNew($form, $request, $ajax);
    }


    private function formRequestNew(Form $form, Request $request, $ajax)
    {
        $form->handleRequest($request);
        $eManager = $this->getDoctrine()->getManager($this->container->getParameter('sgn_forms.orm'));

        // Cas 'normal'
        if ($ajax === '' && $form->isValid() === true) {
            $eManager->persist($obj);
            $eManager->flush();
            $request->getSession()->getFlashBag()->add('info', 'Enregistrement ajouté.');

            return $this->redirect($this->generateUrl(
                'sgn_forms_formscrud_show',
                array('table' => $table)
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
                array('table' => $table)
            ));
        }

        // ajax pas valide
        if ($ajax === 'validate' && $form->isValid() === false) {
            return array('form' => $form->createView());
        }

        if ($ajax === 'dynamic' && $form->isValid() === false) {
            return array('form' => $form->createView());
        }

        if ($ajax === 'dynamicChoise' && $form->isValid() === false) {
            return array('form' => $form->createView($form->getParent()));
        }

        // Pb des formulaires avec file et ajax, on est obligé de bricoler !!!!
        // voir si on ne peux pas faire mieux !!
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

        return array('form' => $form->createView());
    }
    /**
     * @Route("/{table}/edit/{ident}/")
     * @Route("/{table}/edit/{ident}/{ajax}")
     *
     * @Template()
     */
    public function editAction($table, $ident, Request $request, $ajax = '')
    {
        $configTable = $this->getConfigFromtable($table);
        $type        = $configTable['type'];
        $bundle      = $configTable['bundle'];
        $alias       = $configTable['alias'];

        // je ne sais pas quand c'est utilisé !!!
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
        $obj      = $eManager->getRepository($alias.':'.$table)->findOneById($ident);
        if ($obj === false) {
            throw $this->createNotFoundException('Aucun enr trouvé pour cet id : '.$ident);
        }

        $form = $this->createForm(
            $type !== '' ? new $type() : strtolower($table).'_type',
            $obj,
            array(
             'action' => $this->generateUrl(
                 'sgn_forms_formscrud_edit',
                 array(
                  'table' => $table,
                  'ident' => $ident,
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
                array('table' => $table)
            ));
        }

        // $ajax === 'dynamic' => le formulaire est modifié dynamiquement.
        if ($ajax !== 'dynamic' && $form->isValid() === true) {
            $eManager->flush();
            $request->getSession()->getFlashBag()->add('info', 'Enregistrement modifé.');
            // Si on valide le formulaire par Ajax, la redirection se fait en JQuery.
            return new Response($this->generateUrl(
                'sgn_forms_formscrud_show',
                array('table' => $table)
            ));
        }

        return array( 'form' => $form->createView() );
    }

    /**
     * Renvoie les éléments utiles à la construction HTML de la page.
     *
     * @param Entity Manager $eManager
     * @param string         $bundle   Le nom du bundle
     * @param string         $table
     * @param string         $params   les paramtres contenus dans l'URL
     *
     * @return array Les éléments utiles à la page
     *
     * @SuppressWarnings(PHPMD.StaticAccess)
     */
    private function getFormatHtml(Request $request, EntityManager $eManager, $configTable, $params)
    {
        $limits   = SGNTwigCrudTools::getLimitsFromParams($params);
        $limit    = $limits[0];
        $rowsList = $limits[1];

        if (isset($configTable['alias']) === true) {
            $entity = $configTable['alias'].':'.$configTable['table'];
        }

        // pour les liens de droite
        $bestofEntity = $this->container->getParameter('sgn_forms.bestof_entity');

        $bundle           = $configTable['bundle'];
        $tabEntities      = SGNTwigCrudTools::getMenuTabEntities($this, $bundle, $this->container->getParameter('sgn_forms.select_entity'));
        $entity           = $configTable['alias'].':'.$configTable['table'];
        $table            = $configTable['table'];
        $arraySmallFields = $this->container->getParameter('sgn_forms.smallFields');
        $tableFilters     = $this->container->getParameter('sgn_forms.entities_filters');
        $columnModel      = SGNTwigCrudTools::getColumnModel(array(), $eManager, $entity, $tableFilters, $arraySmallFields);

        $collectionNames = $this->getCollectionNames($configTable);
        $urlShowOne      = $this->generateUrl('sgn_forms_formscrud_showone', array('table' => $table, 'ident' => '#'), true);
        $urlEdit         = $this->generateUrl('sgn_forms_formscrud_edit', array('table' => $table, 'ident' => '#'), true);
        $urlDelete       = $this->generateUrl('sgn_forms_formscrud_delete', array('table' => $table, 'ident' => '#'));
        $urlNew          = $this->generateUrl('sgn_forms_formscrud_new', array('table' => $table), true);

        return array(
                'columnModel'     => $columnModel,
                'collectionNames' => $collectionNames,
                'entity'          => $table,
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

    private function getConfigFromtable($table)
    {
        $config['table']     = '';
        $config['alias']     = '';
        $config['meta']      = '';
        $config['bundle']    = '';
        $config['bundleDir'] = '';
        $config['entity']    = '';
        $config['type']      = '';

        $namespaces      = $this->getDoctrine()->getManager($this->container->getParameter('sgn_forms.orm'))->getConfiguration()->getEntityNamespaces();
        $allMeta         = $this->getDoctrine()->getManager($this->container->getParameter('sgn_forms.orm'))->getMetadataFactory()->getAllMetadata();
        $config['table'] = $table;

        if (strpos($table, '.') > 0) {
            $tab              = explode('.', $table);
            $table            = $tab[1];
            $config['alias']  = $tab[0];
            $config['bundle'] = $tab[0];
            $config['table']  = $tab[1];
        }

        foreach ($allMeta as $meta) {
            $shortMetaName = SGNTwigCrudTools::getName($meta->name);
            if ($shortMetaName === $table) {
                $tab    = explode('\Entity', $meta->namespace);
                $bundle = str_replace('\\', '', $tab[0]);

                $classBundleName  = Validators::validateBundleName($bundle);
                $classBundleValid = $this->get('Kernel')->getBundle($classBundleName);

                $config['alias']     = array_search($meta->namespace, $namespaces);
                $config['meta']      = $meta;
                $config['bundle']    = $classBundleName;
                $config['bundleDir'] = $tab[0];
                $config['entity']    = $config['alias'].':'.$table;
                $config['type']      = $this->getFormFromTable($classBundleValid, $table);

                return $config;
            }
        }

        $message = 'La table '.$table.' n’existe pas !';

        throw new \Exception($message);
    }

    private function getFormFromTable($classBundleValid, $table)
    {
        $databaseDir = $classBundleValid->getPath();
        $classDir    = $classBundleValid->getNamespace();
        $finder      = new Finder();
        $type        = '';
        $finder->files()->in($databaseDir)->name($table.'Type.php');
        foreach ($finder as $file) {
            $relativPath = $file->getRelativePath();
            $relativPath = str_replace('/', '\\', $relativPath);
            $type        = $classDir.'\\'.$relativPath.'\\'.$table.'Type';
        }

        return $type;
    }

    /**
     * @Route("/{table}/showone/{ident}/")
     *
     * @Template()
     */
    public function showoneAction($table, $ident, Request $request)
    {
        $configTable = $this->getConfigFromtable($table);
        $eManager    = $this->getDoctrine()->getManager($this->container->getParameter('sgn_forms.orm'));
        $obj         = $eManager->getRepository($configTable['alias'].':'.$table)->findOneById($ident);
        $metaData    = $eManager->getClassMetadata($configTable['alias'].':'.$table);

        if ($obj === false || $obj === null) {
            throw $this->createNotFoundException('Aucun enr trouvé pour cet id : '.$ident);
        }

        foreach ($metaData->fieldNames as $value) {
            $theValue = $obj->{'get'.ucfirst($value)}();
            if ($theValue === null) {
                $fields[$value] = '';
                continue;
            }

            $fields[$value] = $theValue;

            if ($metaData->fieldMappings[$value]['type'] === 'date' && is_object($theValue) === true) {
                $fields[$value] = $theValue->format('Y-m-d');
                continue;
            }

            if ($metaData->fieldMappings[$value]['type'] === 'datetime' && is_object($theValue) === true) {
                $fields[$value] = $theValue->format('Y-m-d H:i:s');
                continue;
            }
        }

        return array('obj' => $fields);
    }

    /**
     * @Route("/{table}/delete/{ident}/")
     *
     * @Template()
     */
    public function deleteAction($table, $ident, Request $request)
    {
        $configTable = $this->getConfigFromtable($table);
        $bundle      = $configTable['bundle'];
        if ($this->container->hasParameter('sgn_forms.forms.'.$bundle) === true) {
            $formBundle = $this->container->getParameter('sgn_forms.forms.'.$bundle);
            if ($formBundle !== '@service') {
                $formBundleName  = Validators::validateBundleName($formBundle);
            }
        }

        $eManager = $this->getDoctrine()->getManager($this->container->getParameter('sgn_forms.orm'));
        $obj      = $eManager->getRepository($configTable['alias'].':'.$table)->findOneById($ident);
        $form = $this->createFormBuilder($obj)->setAction($this->generateUrl('sgn_forms_formscrud_delete', array('bundle' => $bundle, 'table' => $table, 'ident' => $ident)))->getForm();
        $form->handleRequest($request);

        // Valider un objet qui va etre détruit ;-) On maintient à cause des relations OneToMany & co
        // On considere donc que les données à supprimer sont bonnes
        if ($form->isValid() === true) {
            $entity = $eManager->getRepository($configTable['alias'].':'.$table)->findOneById($ident);

            if ($entity === false || $entity === null) {
                throw $this->createNotFoundException('Unable to find entity '.$configTable['alias'].':'.$table.' id = '.$ident);
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

        return array( 'form' => $form->createView());
    }

    /**
     * Permet de créer la grille HTML d'une collection quand on sélectionne un objet dans une grille.
     *
     * @Route("/{table}/create/JQG/{collection}/{ident}/" )
     *
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function createJqGridAction(Request $request, $table, $collection, $ident = 1)
    {
        $arraySmallFields = $this->container->getParameter('sgn_forms.smallFields');

        $filters   = $request->query->all();
        $datas     = array();
        $tabResult = array(
                      'columnModel'    => '[]',
                      'table'          => $table,
                      'collection'     => $collection,
                      'limit'          => 10,
                      'rowsList'       => 1,
                      'parent'         => null,
                      'collectionUrls' => array(),
                      'url'            => '',
                      'ident'          => $ident,
                     );

        if (isset($filters['parent']) === true) {
            $tabResult['parent'] = $filters['parent'];
        }

        // si une ligne est selectionnée
        if ($request->isXmlHttpRequest() === true) {
            if (isset($ident) === true && $ident > 0) {
                if ($collection === 'Audit') {
                    $datas = $this->getAudit($table, 0);
                    if (count($datas) > 0) {
                        foreach ($datas as $data) {
                            $result[] = $data;
                            break;
                        }

                        $tabResult['columnModel'] = SGNTwigCrudTools::getColumnModel($result[0]);
                    }
                } else {
                    $eManager    = $this->getDoctrine()->getManager($this->container->getParameter('sgn_forms.orm'));
                    $configTable = $this->getConfigFromtable($table);
                    $metadata    = $configTable['meta'];
                    $assocClass  = $metadata->getAssociationTargetClass($collection);

                    $tabResult['columnModel'] = SGNTwigCrudTools::getColumnModel(array(), $eManager, $assocClass, $this->container->getParameter('sgn_forms.entities_filters'), $arraySmallFields);

                    if ($tabResult['parent'] === null) {
                        $assocTable      = SGNTwigCrudTools::getName($assocClass);
                        $configTablAssoc = $this->getConfigFromtable($assocTable);
                        $collectionUrls  = $this->getCollectionNames($configTablAssoc, $collection);
                        if (isset($collectionUrls[$table]) === true) {
                            unset($collectionUrls[$table]);
                        }

                        $tabResult['collectionUrls'] = $collectionUrls;
                    }
                }

                if ($collection !== 'Audit') {
                    $assoc            = $configTable['meta']->getAssociationMapping($collection);
                    $name             = $assoc['targetEntity'];
                    $entityColl       = SGNTwigCrudTools::getName($name);
                    $tabResult['url'] = $this->get('router')->generate(
                        'sgn_forms_formscrud_show',
                        array(
                         'table'  => $entityColl,
                         'format' => 'html',
                         'id'     => '#',
                        ),
                        true
                    );
                }
            }
        }

        return $this->render('SGNFormsBundle:FormsCRUD:selectJqGrid.html.twig', $tabResult);
    }

    /**
     * Permet de récupérer une collection d'un objet quand on le sélectionne dans une grille
     * Format json.
     *
     * @Route("/{table}/select/JQG/{collection}/" )
     * @Route("/{table}/select/JQG/{collection}/{ident}/" )
     *
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function selectJqGridAction(Request $request, $table, $collection, $ident = 0)
    {
        $filters    = $request->query->all();

        $datas      = array();
        $limit      = 10;
        $page       = 0;
        $sourceId   = null;
        $count      = 0;
        $totalPages = 0;

        if (isset($filters['rows']) === true) {
            $limit = $filters['rows'];
        }

        if (isset($filters['page']) === true) {
            $page = $filters['page'];
        }

        if (isset($filters['sourceId']) === true) {
            $sourceId = $filters['sourceId'];
        }

        if (isset($sourceId) === true && $sourceId !== 'undefined') {
            $result = $this->getResultJqGrid($table, $collection, $sourceId, $count);
        }

        if ($count > 0 && $limit > 0) {
            $totalPages = ceil($count / $limit);
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

    private function getResultJqGrid($table, $collection, $sourceId, &$count)
    {
        $result     = array();
        if ($collection === 'Audit') {
            $datas = $this->getAudit($table, $sourceId);
        } else {
            $datas = $this->getCollection($table, $collection, $sourceId);
        }
        $count = count($datas);
        if ($count > 0) {
            for ($i = 0; $i < $limit; ++$i) {
                $index = ($i + ($page * $limit) - $limit);
                if ($index === $count) {
                    break;
                }
                if ($collection === 'Audit') {
                    $result[] = $datas[$index];

                } else {
                    $result[] = Serializor::toArray($datas[$index]);
                }
            }
        }
        return $result;
    }

    private function getCollection($table, $collection, $sourceId)
    {
        $eManager    = $this->getDoctrine()->getManager($this->container->getParameter('sgn_forms.orm'));
        $configTable = $this->getConfigFromtable($table);
        $entity      = $configTable['entity'];
        $enr         = $eManager->getRepository($entity)->findOneById($sourceId);
        $methodName  = 'get'.ucfirst($collection);
        $datas       = null;
        if (method_exists($enr, $methodName) === true) {
            $metadata = $eManager->getClassMetadata($entity);
            $fetch    = $enr->$methodName();
            if ($metadata->isCollectionValuedAssociation($collection) === true) {
                $datas = $fetch;
            } elseif ($fetch !== null) {
                $datas[] = $fetch;
            }

            return $datas;
        }

        return $datas;
    }

    private function getAudit($table, $ident)
    {
        $auditManager = $this->container->get('simplethings_entityaudit.manager');
        $configTable  = $this->getConfigFromtable($table);

        $tableAudit = $configTable['alias'].'\\'.$configTable['table'];

        if ($auditManager->getMetadataFactory()->isAudited($tableAudit) === false) {
            return array();
        }

        $eManager  = $this->getDoctrine()->getManager($this->container->getParameter('sgn_forms.orm'));
        $entity    = $configTable['entity'];
        $class     = $eManager->getClassMetadata($entity);
        $tableName = $class->table['name'].'_audit';
        $table_    = $class->table;
        $prefix    = '';

        if (isset($table_['schema']) === true) {
            $prefix = $table_['schema'].'.';
        }

        $champIdBd = 'id';
        if (isset($class->fieldMappings['id']['columnName']) === true) {
            $champIdBd = $class->fieldMappings['id']['columnName'];
        }

        $query = 'SELECT * FROM '.$prefix.$tableName.' e WHERE e.'.$champIdBd.' = '.$ident.' ORDER BY e.rev DESC';

        if ($ident === 0) {
            $query = 'SELECT * FROM '.$prefix.$tableName.' limit 1';
        }

        $result = $eManager->getConnection()->fetchAll($query);

        return $result;
    }

    /**
     * Renvoie un tableau contenant les URL utiles à la fabrication des sous-formulaires.
     *
     * @param string $project le nom du projet
     * @param string $table   le nom de la table
     *
     * @return array le tableau avec les URL
     */
    private function getCollectionNames($configTable, $parent = null)
    {
        $collectionNames = array();
        $entity          = $configTable['entity'];
        $table           = $configTable['table'];
        $eManager        = $this->getDoctrine()->getManager($this->container->getParameter('sgn_forms.orm'));
        $metadata        = $eManager->getClassMetadata($entity);
        $associations    = $metadata->getAssociationNames();
        $tableFilters    = $this->container->getParameter('sgn_forms.entities_filters');
        $boolAudit       = true;
        $boolExtended    = false;

        $options = array(
                    '*',
                    $configTable['entity'],
                   );

        foreach ($options as $option) {
            if (isset($tableFilters[$option]) !== true) {
                continue;
            }

            if (isset($tableFilters[$option]['extended']) === true) {
                $boolExtended = $tableFilters[$option]['extended'];
            }

            if (isset($tableFilters[$option]['rel_hidden']) === true) {
                $selects = explode(',', $tableFilters[$option]['rel_hidden']);
                foreach ($selects as $sel) {
                    if (array_search(trim($sel), $associations) !== false) {
                        unset($associations[array_search(trim($sel), $associations)]);
                    }

                    if (strtolower(trim($sel)) === 'audit') {
                        $boolAudit = false;
                    }
                }
            }
        }

        foreach ($associations as $champ) {
            if ($boolExtended !== true && $metadata->isSingleValuedAssociation($champ) === true) {
                continue;
            }

            $assocClass = $metadata->getAssociationTargetClass($champ);
            $assocTable = SGNTwigCrudTools::getName($assocClass);
            $configT    = $this->getConfigFromtable($assocTable);

            $url = $this->get('router')->generate(
                'sgn_forms_formscrud_createjqgrid',
                array(
                 'table'      => $table,
                 'collection' => $champ,
                 'id'         => '#',
                 'parent'     => $parent,
                ),
                true
            );
            $collectionNames[$champ]['url'] = $url;

            $urlRelocation = $this->get('router')->generate(
                'sgn_forms_formscrud_show',
                array(
                 'table'  => $assocTable,
                 'format' => 'html',
                 'id'     => '#',

                ),
                true
            );

            $collectionNames[$champ]['urlRelocation'] = $urlRelocation;

            if ($boolExtended !== true || $parent !== null) {
                continue;
            }

            $collectionNames[$champ]['collections'] = $this->getCollectionNames($configT, $champ);

            if (isset($collectionNames[$champ]['collections'][$table]) === true) {
                unset($collectionNames[$champ]['collections'][$table]);
            }
        }

        if ($boolAudit !== true || $parent !== null) {
            return $collectionNames;
        }

        $auditManager = $this->container->get('simplethings_entityaudit.manager');
        $tableAudit   = $configTable['alias'].'\\'.$configTable['table'];

        if ($auditManager->getMetadataFactory()->isAudited($tableAudit) === true) {
            // $urlRelocation = $this->get('router')->generate(
            //     'sgn_forms_formscrud_show',
            //     array(
            //      'table'  => $assocTable,
            //      'format' => 'html',
            //      'id'     => '#',

            //     ),
            //     true
            // );
            // Pour l'instant on ne redirige pas les tables audit
            $urlRelocation = '';

            $url = $this->get('router')->generate(
                'sgn_forms_formscrud_createjqgrid',
                array(
                 'table'      => $table,
                 'collection' => 'Audit',
                 'url'        => $urlRelocation,
                 'id'         => '#',
                ),
                true
            );
            $collectionNames['Audit']['url'] = $url;
        }

        return $collectionNames;
    }
}
