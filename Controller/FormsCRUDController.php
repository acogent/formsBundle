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


class FormsCRUDController extends Controller
{

    /**
     *
     * @Route("/{bundle}/{table}/{format}/show/{params}/", requirements= { "params"=".+"})
     * @Route("/{bundle}/{table}/{format}/show/" )
     * @Route("/{bundle}/{table}" )
     * @Route("/{bundle}/" )
     * @Route("/" )
     *
     * @Template()
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function showAction($bundle = "x", $table = 'x' , $format='html', $params="limit/10"  )
    {
        if ($bundle == "x")
        {
            $bundles = $this->container->getParameter('sgn_forms.bundles');
            $bundle = $bundles [0];
        }
        if (!strpos($bundle, "Bundle"))$bundle .= "Bundle";
        if ($table == "x")
        {
            $tables = $this->container->getParameter('sgn_forms.bestof_entity');
            foreach ($tables as $ta)
            {
                if (substr($ta, 0, strpos($ta, '.') )  == $bundle)
                {
                    $table = substr($ta,  strpos($ta, '.')+1);
                    break;
                }
            }
        }
        $em = $this->getDoctrine()->getManager($this->container->getParameter('sgn_forms.orm'));
        // c'est pour retourner des données
        if ($format == 'json')
        {
            $request     = $this->getRequest();
            $filters     = $request->query->all();

            return $this->getFormatJson($em, $bundle, $table, $filters, $params);
        }
        // c'est juste pour construire l'interface et donner des param à la grille
        else if ($format == 'html')
        {
            return $this->getFormatHtml($em, $bundle, $table, $params);
        }
        else{
            throw $this->createNotFoundException('Le produit n\'existe pas');
        }

    }

    /**
     * Renvoie les éléments utiles à la construction HTML de la page
     * @param  Entity Manager $em
     * @param  string $bundle Le nom du bundle
     * @param  string $table
     * @param  string $params les paramtres contenus dans l'URL
     * @return array         Les éléments utiles à la page
     */
    private function getFormatHtml($em, $bundle, $table, $params)
    {
        $request = $this->getRequest();
        $request->getSession()->getFlashBag()->add('info', "Les joker possibles, pour les champs texte, sont % et _. Pour les champs numériques, vous pouvez utiliser > et <. Pour une recherche plus compliquée, utilisez l'outil de recherche dédié");
        $data   = null;
        $entity = $bundle.":".$table;

        $criteria = $this->getCriteriaFromParams($params);
        $limits   = $this->getLimitsFromParams($params);
        $limit    = $limits[0];
        $rowsList = $limits[1];
        // pour les liens de droite
        $tables = $this->container->getParameter('sgn_forms.bestof_entity');
        foreach ($tables as $ta)
        {
            if (substr($ta, 0, strpos($ta, '.') )  == $bundle)
            {
                $bestof_entity[] = substr($ta,  strpos($ta, '.')+1);
            }
        }
        $tab_entities = SGNTwigCrudTools::getMenuTabEntities($this,$bundle );
        
        // Pour jQgrid
        $metadata            = $em->getClassMetadata($entity);
      //  var_dump($metadata ); exit();
        $DBtable             = $metadata->table['name'];
        $fields              = array_keys($metadata->fieldMappings);
        $associationMappings =  $metadata->associationMappings ;
        $keyAssoc = array();
        foreach ($associationMappings as $key=>$assoc)
        {
            if (isset ($assoc['joinColumns']))
            {
                $keyAssoc[] = $key;
            }
        }
        $fields = array_unique(array_merge($fields, $keyAssoc));
        foreach ($fields as $field)
        {
            if (in_array($field, $keyAssoc))
            {

                $s_fields[] = 'IDENTITY(s.'.$field.') as '.$field ;
            }
            else
            {
                $s_fields[] = 's.'.trim($field);
            }
        }
        // pour personnaliser les tables jQGrid
        $table_fields = $this->container->getParameter('sgn_forms.entities_fields');
        if (array_key_exists($entity,$table_fields))
        {
            $selects  = explode(',', $table_fields[$entity]);
            foreach($selects as $sel )
            {
                $sels[] = 's.'.trim($sel);
            }

            $AllFields = array_unique(array_merge($sels, $s_fields));
        }
        else
        {
            $AllFields =  $s_fields;
        }
        $select = implode (' , ',$AllFields );
        $builder = $em->getRepository($entity) ->createQueryBuilder('s') ->select($select);
        $builder = $this->getWhereFromParams($params, $builder);
        $query   = $builder ->getQuery();
        $query->setMaxResults( $limit );
        

        $request = $this->getRequest();
        $request->getSession()->getFlashBag()->add('notice', $query->getSQL());
       
       // $sql     = $query->getSql(); var_dump($sql);
        $result  = $query->getResult();

        if ($result)
        {
            $builder = $em->getRepository($entity) ->createQueryBuilder('a')->select('count(a)');

            $$builder = $this->getWhereFromParams($params, $builder);
            $query    = $builder ->getQuery();
            $sql      = $query->getSql();
            $count    = $query->getSingleScalarResult();

            if ($count  < $limit) $limit = $count;
            
            $columnNames     = $this->getColumnNames($result[0]);
            $collectionNames = $this->getCollectionNames($result[0],$bundle,$table);
            $columnModel     = $this->getColumnModel($result[0], $em, $entity);

            return array(
                'project'         => $bundle,
                'columnModel'     => $columnModel,
                'columnNames'     => $columnNames,
                'collectionNames' => $collectionNames,
                'entity'          => $table,
                'count'           => $count,
                'bestof_entity'   => $bestof_entity,
                'entities'        => $tab_entities,
                'limit'           => $limit,
                'rowsList'        => $rowsList,
                'url_new'         => $this->generateUrl('sgn_forms_formscrud_new', array('bundle'=>$bundle, 'table'=>$table), true),
                'url_showone'     => $this->generateUrl('sgn_forms_formscrud_showone', array('bundle'=>$bundle, 'table'=>$table, 'id'=>0), true),
                'url_edit'        => $this->generateUrl('sgn_forms_formscrud_edit', array('bundle'=>$bundle, 'table'=>$table, 'id'=>0), true),
                'url_delete'      => $this->generateUrl('sgn_forms_formscrud_delete', array('bundle'=>$bundle, 'table'=>$table, 'id'=>0)),
                'params'          => $params
                );

        }else{
             return array(
                'project'         => $bundle,
                'columnModel'     => "[]",
                'columnNames'     => "",
                'collectionNames' => null,
                'entity'          => $table,
                'count'           => 0,
                'bestof_entity'   => $bestof_entity,
                'entities'        => $tab_entities,
                'limit'           => $limit,
                'rowsList'        => $rowsList,
                'url_new'         => null,
                'url_showone'     => null,
                'url_edit'        => null,
                'params'          => $params
                );
        }
    }

    /**
     * Renvoie les données au format json
     * @param  Entity Manager $em
     * @param  string $bundle Le nom du bundle
     * @param  string $table
     * @param  array $filters le tableau issu de l'ajax
     * @param  string $params les paramtres contenus dans l'URL
     * @return json         Les données filtrées au format json
     */
    private function getFormatJson($em, $bundle, $table, $filters, $params)
    {
        $entity   = $bundle.":".$table;
        $criteria = $this->getCriteriaFromParams($params);
        $limits   = $this->getLimitsFromParams($params);
        $result   = array();
        $orderBy  = array();

        $limit       = isset($filters['rows']) ? $filters['rows'] : 10;
        $page        = isset($filters['page']) ? $filters['page'] : 0;
        $sord        = isset($filters['sord']) ? $filters['sord'] : 'ASC';
        $sidx        = isset($filters['sidx']) ? $filters['sidx'] : 'id';
        $source      = isset($filters['source']) ? $filters['source'] : null;
        $sourceid    = isset($filters['sourceId']) ? $filters['sourceId'] : null;
        $search      = isset($filters['_search']) ? $filters['_search'] : 'false';
        $searchField = isset($filters['searchField']) ? $filters['searchField'] : 'false';
       

        if(!$sidx) $sidx = 1;

        $orderBy[$sidx] = $sord;
        // on a lancé une recherche par la barre de recherche
        if ($search == 'true' && $searchField == 'false')
        {
            $builder = $em->getRepository($entity)
                            ->createQueryBuilder('a')
                            ->select('count(a)');
            $builder    = $this->getWhereFromFilters($filters, $builder);
            
            $query = $builder->getQuery();

            $count = $builder->getQuery() ->getSingleScalarResult();

            if( $count > 0 && $limit > 0) {
                $total_pages = ceil($count/$limit);
            } else {
                $total_pages = 0;
            }
            if ($page > $total_pages) $page=$total_pages;

            $start = $limit*$page - $limit;
            if($start <0) $start = 0;

            $builder = $em->getRepository($entity)
                            ->createQueryBuilder('a')
                            ->select('a');
            
            $builder= $this->getWhereFromFilters($filters, $builder);
            
            $query = $builder->getQuery();
            $query->setFirstResult( $start );
            $query->setMaxResults( $limit );
            
            $data =  $query->getResult();

            $result = array();
            $result['debug']   = print_r(array('sql' => $query->getSQL(),'parameters' => $criteria,), true);
            $result['page']    = $page;
            $result['records'] = $count;
            $result['total']   = $total_pages;
            $result['rows']    =  Serializor::toArray($data);
        }
        // On a lancé une recherche par la boite de dialogue
        else if ($search == 'true' && $searchField != 'false')
        {
            $searchField  = $filters['searchField'];
            $searchString = $filters['searchString'];
            $searchOper   = $filters['searchOper'];

            $repository = $this->getDoctrine()
                ->getRepository($entity);

            $builder = $repository
                ->createQueryBuilder('u')
                ->where('1  = 1')
                ;

            $builder = $this->addWhere($em, $entity, $builder, $searchField , $searchString, $searchOper);
            if (get_class($builder) == "Doctrine\ORM\QueryBuilder" )
            {
                $query = $builder->getQuery();

                // pour le debugage
                $result['debug']   = print_r(array('sql' => $query->getSQL(),'parameters' => $query->getParameters(),), true);

                $result['page']    = $page;
                $result['records'] = '';
                $result['total']   = '';
                $data              = $query->getResult();

                $result['rows']    = Serializor::toArray($data);
            }
            if (get_class($builder) == "Doctrine\ORM\NativeQuery")
            {
                $result['debug']   = print_r(array('sql' => $builder->getSQL(),'parameters' => $builder->getParameters(),), true);
                $data              = $builder->getResult();
                $result['rows']    = Serializor::toArray($data);
            }
        }
        // on n'a pas lancé de recherche
        else{

            $builder = $em->getRepository($entity)
                            ->createQueryBuilder('a')
                            ->select('count(a)');

            $$builder    = $this->getWhereFromParams($params, $builder);
            $count = $builder
                ->getQuery()
                ->getSingleScalarResult();

            if( $count > 0 && $limit > 0) {
                $total_pages = ceil($count/$limit);
            } else {
                $total_pages = 0;
            }
            //if ($page > $total_pages) $page=$total_pages;

            $start = $limit*$page - $limit;
            if($start < 0) $start = 0;

            $data = $em->getRepository($entity)
            ->findBy($criteria, $orderBy , $limit , $start );
            $result = array();
            $result['page']    = $page;
            $result['records'] = $count;
            $result['total']   = $total_pages;
            $result['rows']    = Serializor::toArray($data);
        }

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
        $tParams = explode('/', $params);
        array_pop($tParams);
        while(count($tParams) > 1 && $tParams[0] <> "")
        {
            if($tParams[0] == 'orderby')
            {
                if((strtolower($tParams[2])== 'asc' or strtolower($tParams[2])== 'desc' ))
                {
                    $tParams = array_slice($tParams,3);
                }
                else{
                    $tParams = array_slice($tParams,2);
                }
            }
            elseif($tParams[0] == 'limit')
            {
                $tParams  = array_slice($tParams,2);
            }
            elseif( $tParams[0] == 'all')
            {
                $tParams = array_slice($tParams,1);
            }
            else
            {
                if (isset($tParams[1]) && isset($tParams[0]))
                {
                    $criteria[$tParams[0]] = $tParams[1];
                    $tParams = array_slice($tParams,2);
                }
            }
        }
        return  $criteria;
    }

    /**
     * Renvoie une clause WHERE à partir des critères de sélection d'un ajax
     * @param  string $params la chaine de caractère contenant les parametres
     * @return builder
     */
    private function getWhereFromFilters($filters, $builder)
    {
        $array_exclude = array('rows','page','nd', 'sord','sidx','source','sourceId','_search','searchField');
        $builder->where('1=1');

        foreach($filters as $champ=>$val)
        {
            if (!in_array($champ, $array_exclude))
            {   
                if (strpos("&".$val, "%") || strpos($val, "?"))
                {
                    $builder->andWhere("a.$champ like '$val'");
                }
                else if (strpos("&".$val, ">") == 1)
                {
                    $val = str_replace(">", "", $val);
                    $builder->andWhere("a.$champ > '$val'");
                }
                else if (strpos("&".$val, "=") == 1)
                {
                    $val = str_replace("=", "", $val);
                    $builder->andWhere("a.$champ = '$val'");
                }
                else if (strpos("&".$val, "<") == 1)
                {
                    $val = str_replace("<", "", $val);
                    $builder->andWhere("a.$champ < '$val'");
                }
                else{
                    $builder->andWhere("a.$champ = '$val'");
                }  
            }
        }

        return  $builder;
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
        while(count($tParams) > 1 && $tParams[0] <> "")
        {
            if($tParams[0] == 'orderby')
            {
                if((strtolower($tParams[2])== 'asc' or strtolower($tParams[2])== 'desc' ))
                {
                    $tParams = array_slice($tParams,3);
                }
                else{
                    $tParams = array_slice($tParams,2);
                }
            }
            elseif($tParams[0] == 'limit')
            {
                $tParams  = array_slice($tParams,2);
            }
            elseif( $tParams[0] == 'all')
            {
                $tParams = array_slice($tParams,1);
            }
            else
            {
                if (isset($tParams[1]) && isset($tParams[0]))
                {
                    $builder->andWhere("a.$tParams[0] = '$tParams[1]'");
                    $tParams = array_slice($tParams,2);
                }
            }
        }
        return  $builder;
    }

    /**
     * Renvoie un tableau contenant les paramètres "order by" contenn dans l'URL
     * @param  string $params la chaine de caractère contenant les parametres
     * @return array         Un tableau avec les paramètres de l'order by
     */
    private function getOrderByFromParams($params)
    {

        $Orderby = array();
        $tParams = explode('/', $params);
        array_pop($tParams);
        while(count($tParams) > 1 && $tParams[0] <> "")
        {
            if($tParams[0] == 'orderby')
            {
                if((strtolower($tParams[2])== 'asc' or strtolower($tParams[2])== 'desc' ))
                {
                    $Orderby[$tParams[1]] = $tParams[2];
                    $tParams = array_slice($tParams,3);
                }
                else{
                    $Orderby[$tParams[1]] = 'asc';
                    $tParams = array_slice($tParams,2);
                }
                return $Orderby;
            }
            elseif($tParams[0] == 'limit')
            {
                $tParams  = array_slice($tParams,2);
            }
            elseif( $tParams[0] == 'all')
            {
                $tParams = array_slice($tParams,1);
            }
            else
            {
                if (isset($tParams[1]) && isset($tParams[0]))
                {
                    $tParams = array_slice($tParams,2);
                }
            }
        }
        return  $Orderby;
    }

    /**
     * Renvoie un tableau contenant les parametres "limit" contenu dans l'URL
     * @param  string $params la chaine de caractère contenant les parametres
     * @return array         Un tableau avec la limite et la liste de filtre
     */
    private function getLimitsFromParams($params)
    {
        $Limit = array(10,"[10, 20, 30, 40]");
        $tParams = explode('/', $params);
        array_pop($tParams);
        while(count($tParams) > 1 && $tParams[0] <> "")
        {
            if($tParams[0] == 'orderby' && isset($tParams[1]) )
            {
                if( isset($tParams[2])  && (strtolower($tParams[2])== 'asc' or strtolower($tParams[2])== 'desc' ))
                {
                    $tParams = array_slice($tParams,3);
                }
                else{
                    $tParams = array_slice($tParams,2);
                }
            }
            elseif($tParams[0] == 'limit' && isset($tParams[1]))
            {
                $lim1     = $tParams[1];
                $lim2     = $lim1*2;
                $lim3     = $lim1*3;
                $lim4     = $lim1*4;
                $rowsList = "[$lim1, $lim2, $lim3, $lim4]";
                $Limit    = array( $lim1, $rowsList);
                return $Limit;
            }
            elseif( $tParams[0] == 'all')
            {
                $tParams = array_slice($tParams,1);
            }
            else
            {
                if (isset($tParams[1]) && isset($tParams[0]))
                {
                    $tParams = array_slice($tParams,2);
                }
            }
        }
        return  $Limit;
    }

    /**
     *
     * @Route("/{bundle}/{table}/new/")
     *
     * @Template()
     */

    public function newAction($bundle, $table , Request $request )
    {
        $bundlename  = Validators::validateBundleName($bundle);
        $BundleValid = $this->get('Kernel')->getBundle($bundlename);
        $dir         = $BundleValid->getNamespace();
        
        $class = $dir.'\Entity\\'.$table;
        $type  = $dir.'\Form\\'.$table.'Type';

        $obj   = new $class();
        $form  = $this->createForm(new $type(), $obj, array(
            'action' => $this->generateUrl(
                'sgn_forms_formscrud_new', 
                array('bundle' => $bundle, 'table' => $table)
            ),
        ));

        $form->handleRequest($request);

        if ($form->isValid()) {
            $em    = $this->getDoctrine()->getManager($this->container->getParameter('sgn_forms.orm'));
            $em->persist($obj);
            $em->flush();
            $request->getSession()->getFlashBag()->add('info', 'Enregistrement ajouté.');
            return $this->redirect($this->generateUrl('sgn_forms_formscrud_show',
                array('bundle' => $bundle, 'table' => $table)));
        }

        return  array(
                'form' => $form->createView(),
            );
    }

    /**
     *
     * @Route("/{bundle}/{table}/edit/{id}/")
     *
     * @Template()
     */

    public function editAction( $bundle, $table , $id,  Request $request )
    {
        $bundlename  = Validators::validateBundleName($bundle);
        $BundleValid = $this->get('Kernel')->getBundle($bundlename);
        $dir         = $BundleValid->getNamespace();
        $class = $dir.'\Entity\\'.$table;
        $type  = $dir.'\Form\\'.$table.'Type';
        $em    = $this->getDoctrine()->getManager($this->container->getParameter('sgn_forms.orm'));
        $obj   = $em->getRepository($bundle.':'.$table)
                ->findOneById($id );
        if (!$obj) {
            throw $this->createNotFoundException('Aucun enr trouvé pour cet id : '.$id);
        }

        $form  = $this->createForm(new $type(), $obj, array(
            'action' => $this->generateUrl(
                'sgn_forms_formscrud_edit', 
                array('bundle' => $bundle, 'table' => $table, 'id' => $id)
            ),
        ));

        $form->handleRequest($request);
        
        if ($form->isValid()) {
            $request->getSession()->getFlashBag()->add('info', 'Enregistrement modifé.');
            $em->flush();
            return $this->redirect($this->generateUrl('sgn_forms_formscrud_show',
                array('bundle' => $bundle, 'table' => $table)));
        }
        return  array(
            'form' => $form->createView(),
        );
    }

    /**
     *
     * @Route("/{bundle}/{table}/showone/{id}/")
     *
     * @Template()
     */

    public function showoneAction( $bundle, $table , $id,  Request $request )
    {
        $bundlename  = Validators::validateBundleName($bundle);
        $BundleValid = $this->get('Kernel')->getBundle($bundlename);
        $dir         = $BundleValid->getNamespace();
        $class       = $dir.'\Entity\\'.$table;
        $type        = $dir.'\Form\\'.$table.'Type';
        $em          = $this->getDoctrine()->getManager($this->container->getParameter('sgn_forms.orm'));
        $obj         = $em->getRepository($bundle.':'.$table)  ->findOneById($id );
        $MetaData = $em->getClassMetadata($bundle.':'.$table);
        if (!$obj) {
            throw $this->createNotFoundException('Aucun enr trouvé pour cet id : '.$id);
        }
        foreach ($MetaData->fieldNames as $value) {
            $fields[$value] = $obj->{'get'.ucfirst($value)}();
        }

        return  array(
            'obj' => $fields
        );
    }

     /**
     *
     * @Route("/{bundle}/{table}/delete/{id}/")
     * 
     * @Template()
     */
    public function deleteAction($bundle, $table , $id ,  Request $request)
    {
        $bundlename  = Validators::validateBundleName($bundle);
        $BundleValid = $this->get('Kernel')->getBundle($bundlename);
        $dir         = $BundleValid->getNamespace();

        $class = $dir.'\Entity\\'.$table;
        $type  = $dir.'\Form\\'.$table.'Type';
        $em    = $this->getDoctrine()->getManager($this->container->getParameter('sgn_forms.orm'));
        $obj   = $em->getRepository($bundle.':'.$table)
                ->findOneById($id );

        $form  = $this->createForm(new $type(), $obj, array(
            'action' => $this->generateUrl(
                'sgn_forms_formscrud_delete', 
                array('bundle' => $bundle, 'table' => $table, 'id' => $id)
            ),
        ));

        $form->handleRequest($request);

        if ($form->isValid()) {
            $em = $this->getDoctrine()->getManager($this->container->getParameter('sgn_forms.orm'));
            $entity = $em->getRepository('IFIDatabaseBundle:Alti')->find($id);

            if (!$entity) {
                throw $this->createNotFoundException('Unable to find entity.');
            }

            $em->remove($entity);
            $em->flush();

            return $this->redirect($this->generateUrl('sgn_forms_formscrud_show',
                array('bundle' => $bundle, 'table' => $table)));
        }

        return  array(
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
    public function selectJqGridAction($bundle, $table , $format,   $collection )
    {
        $request = $this->getRequest();
        $filters = $request->query->all();
        $limit   = isset($filters['rows']) ? $filters['rows'] : 10;
        $page    = isset($filters['page']) ? $filters['page'] : 0;

        $columnModel     = "[]";
        $columnNames     = "";
        $collectionNames = null;
        $result          = array();
        $datas = array();

        $id = isset($filters['sourceId']) ? $filters['sourceId'] : null;
        $em = $this->getDoctrine()->getManager($this->container->getParameter('sgn_forms.orm'));
        if (isset($id) && $id != 'undefined')
        {
            if ($collection == 'Audit')
            {
                $datas = $this->getAudit($bundle, $table , $id );

                if(count($datas) > 0 )
                {
                    for ($i = 0; $i <  $limit; $i++)
                    {
                        $index  = $i + ($page*$limit) - $limit;
                        if ($index ==  count($datas) ) break;
                        $result[] = $datas[$index];
                    }
                    $columnNames     = $this->getColumnNames($result[0]);
                    $collectionNames = $this->getCollectionNames($result[0], $bundle,$table);
                    $columnModel     = $this->getColumnModel($result[0]);
                }
            }
            else{
                $enr = $em->getRepository($bundle.":".$table)
                ->findOneById($id );
                $method_name = 'get' . $collection ;
                if (method_exists($enr, $method_name)) $datas = $enr->$method_name();
                if(count($datas) > 0 )
                {
                    for ($i = 0; $i <  $limit; $i++)
                    {
                        $index  = $i + ($page*$limit) - $limit;
                        if ($index == count($datas) ) break;
                        $result[] = Serializor::toArray( $datas[$index]);
                    }
                    $columnNames     = $this->getColumnNames($result[0]);
                    $collectionNames = $this->getCollectionNames($result[0], $bundle,$table);
                    $columnModel     = $this->getColumnModel($result[0]);
                }
            }
        }

        if ($format == 'json')
        {
            $count = count($datas);
            if( $count > 0 && $limit > 0) {
                $total_pages = ceil($count/$limit) ;
            } else {
                $total_pages = 0;
            }
            $start = $limit*$page - $limit;
            if($start < 0) $start = 0;

            $res = array();
            $res['page']    = $page;
            $res['records'] = $count;
            $res['total']   = $total_pages;
            $res['rows']    = $result;

            $json     = json_encode($res);
            $response = new Response($json);
            $response->headers->set('Content-Type', 'application/json');

            return $response;
        }

        return array(
                'project'         => $bundle,
                'columnModel'     => $columnModel,
                'columnNames'     => $columnNames,
                'table'           => $table,
                'collectionNames' => $collectionNames,
                'entity'          => $collection,
                'limit'           => 10,
                'rowsList'        => 1
                );

    }

    private function getAudit($bundle, $table, $id)
    {

        $auditManager = $this->container->get("simplethings_entityaudit.manager");
        
        $bundlename  = Validators::validateBundleName($bundle);
        $BundleValid = $this->get('Kernel')->getBundle($bundlename);
        $dir         = $BundleValid->getNamespace();
        $tableAudit = $dir.'\Entity\\'.$table;

        if (! $auditManager->getMetadataFactory()->isAudited($tableAudit) )
        {
            $result = array();
        }
        else{
            $em = $this->getDoctrine()->getManager($this->container->getParameter('sgn_forms.orm'));

            $entity = $bundle.':'.$table;
            $class = $em->getClassMetadata($entity);
            $tableName =  $class->table['name'] . '_audit';

            $query = "SELECT * FROM " . $tableName . " e WHERE e.id = " . $id . " ORDER BY e.rev DESC";
            $result = $em->getConnection()->fetchAll($query);
            //var_dump($result);
        }
        return $result;
    }
    /**
     * Permet de créer la grille d'une collection quand on sélectionne un objet dans une grille
     * @Route("/{bundle}/{table}/{format}/select/JQG/{collection}/{id}/" )
     *
     * @Template("SGNFormsBundle:FormsCRUD:selectJqGrid.html.twig")
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function createJqGridAction($bundle, $table , $format,   $collection , $id = 0)
    {
        $request    = $this->getRequest();
        $columnModel     = "[]";
        $columnNames     = "";
        $collectionNames = null;
        $datas = array();
        if($request->isXmlHttpRequest())
        {
            $em = $this->getDoctrine()->getManager($this->container->getParameter('sgn_forms.orm'));
            if (isset($id) && $id != 'undefined'  && $id > 0)
            {
                if ($collection == 'Audit')
                {
                    $datas = $this->getAudit($bundle, $table , $id );
                    if(count($datas) > 0 )
                    {
                        foreach ($datas as $data)
                        {
                            $result[] = $data;
                        }
                        $columnNames     = $this->getColumnNames($result[0]);
                        $collectionNames = $this->getCollectionNames($result[0], $bundle,$table);
                        $columnModel     = $this->getColumnModel($result[0]);
                    }
                }
                else{
                     $enr = $em->getRepository($bundle.':'.$table)
                    ->findOneById($id );

                    $method_name = 'get' . $collection ;
                    if (method_exists($enr, $method_name)) $datas = $enr->$method_name();
                    if(count($datas) > 0 )
                    {
                        foreach ($datas as $data)
                        {
                            $result[] = Serializor::toArray($data);
                        }
                        $columnNames     = $this->getColumnNames($result[0]);
                        $collectionNames = $this->getCollectionNames($result[0], $bundle,$table);
                        $columnModel     = $this->getColumnModel($result[0]);
                    }
                }
            }
        }
        return array(
                'project'         => $bundle,
                'columnModel'     => $columnModel,
                'columnNames'     => $columnNames,
                'table'           => $table,
                'collectionNames' => $collectionNames,
                'entity'          => $collection,
                'limit'           => 10,
                'rowsList'        => 1
                );

    }

    /**
     * Génère les paramètres à partir des filtres d'un ajax
     * @param  array $filters Les filtres de la grille envoyés par ajax
     * @return array          les paramètres
     */
    private function getParamsFromJQG($filters)
    {
        $params = array();
        $exclude = array('_search', 'nd','page', 'rows', 'sldx', 'sord', 'sidx',
            'searchField', 'searchOper', 'searchString' , 'filters');
        foreach($filters as $champ => $val)
        {
            if ( !in_array($champ , $exclude )) $params[$champ] = $val;
        }
        return $params;
    }

    /**
     * Fabrique l'URL pour le formulaire NEW
     * @param  string $project le nom du projet
     * @param  string $entity  le nom de l'entité
     * @return url          l'url générée
     */
/*    private function getURLNew($project,$entity)
    {
        $url = $this->get('router')->generate(
            'sgn_forms_formscrud_new',
            array(
                'bundle' => $project ,
                'table'  => $entity  ,
                ),
            true
        );
        return $url;
    }
*/
    /**
     * Fabrique l'URL pour le formulaire EDIT
     * @param  string $project le nom du projet
     * @param  string $entity  le nom de l'entité
     * @return url          l'url générée
     */
 /*   private function getURLEdit($project,$entity )
    {
        $url = $this->get('router')->generate(
            'sgn_forms_formscrud_edit',
            array(
                'bundle' => $project ,
                'table'  => $entity  ,
                'id'     => 0,
                ),
            true
        );
        return $url;
    }
*/
    /**
     * Renvoie un tableau contenant les URL utiles à la fabrication des sous-formulaires
     * @param  array $data    tableau issu d'une sérialisation du résultat d'une requete
     * @param  string $project le nom du projet
     * @param  string $entity  le nom de l'entité
     * @return array          le tableau avec les URL
     */
    private function getCollectionNames($data, $project,$entity)
    {
        $collectionNames = array();
        $auditManager    = $this->container->get("simplethings_entityaudit.manager");

        $bundlename  = Validators::validateBundleName($project);
        $BundleValid = $this->get('Kernel')->getBundle($bundlename);
        $dir         = $BundleValid->getNamespace();
        $tableAudit  = $dir.'\Entity\\'.$entity;
        foreach($data as $champ=>$val)
        {
            if(is_array($val ) )
            {
                $url ="";
                $url = $this->get('router')->generate(
                    'sgn_forms_formscrud_createjqgrid',
                    array(
                        'bundle'     =>  $project ,
                        'format'     => 'html' ,
                        'table'      =>  $entity  ,
                        'collection' =>  $champ ),
                    true
                    );
                $collectionNames[$champ] = $url;
            }
        }
        if ( $auditManager->getMetadataFactory()->isAudited($tableAudit) )
        {
            $url = $this->get('router')->generate(
                'sgn_forms_formscrud_createjqgrid',
                array(
                    'bundle'     =>  $project ,
                    'format'     => 'html' ,
                    'table'      =>  $entity  ,
                    'collection' =>  "Audit" ),
                true
                );
            $collectionNames["Audit"] = $url;
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
        //$columnNames = array('Actions');
        $columnNames = array();
        foreach($data as $champ=>$val)
        {
            if(!is_array($val ) )  $columnNames[] = $champ;
        }
        return $columnNames;
    }
    /**
     * Fabrique le modèles des colonnes d'une grille
     * @param  array $data    tableau issu d'une sérialisation du résultat d'une requete
     * @param  entityManager $em
     * @param  string $entity  le nom de l'entité
     * @return string         le tableau au format json du modèle des colonnes
     */
    private function getColumnModel($data, $em = null , $entity = null)
    {
       // $columnModel = "{name:'act',index:'act', width:75,sortable:false},";
        $columnModel = "";
        if ($em && $entity)
        {
            $metadata = $em->getClassMetadata($entity);
            foreach($data as $champ=>$val)
            {
                if(!is_array($val ) )
                {
                    if ($metadata->getTypeOfField( $champ) === NULL)
                    {
                        $columnModel .="{ name: '".$champ."' , index: '".$champ."' , search: false },";
                    }
                    else{
                        $columnModel .="{ name: '".$champ."' , index: '".$champ."', search: true },";
                    }
                }
            }
        }
        else{
             foreach($data as $champ=>$val)
            {
                if(!is_array($val ) )
                {
                    $columnModel .="{ name: '".$champ."' , index: '".$champ."', search: false },";
                }
            }
        }
        return '['.substr($columnModel,0,-1).']';
    }

    /**
     * Fabrique le "WHERE" issus des filtres choisis
     * @param entityManager $em
     * @param string $entity       le nom de l'entité
     * @param QueryBuilder $builder
     * @param string $searchField  Le champ recherché
     * @param string $searchString la valeur
     * @param string $searchOper   l'opérateur
     * @return   le QueryBuilder avec un where de plus
     */
    private function addWhere($em, $entity, $builder, $searchField , $searchString, $searchOper)
    {
        $metadata = $em->getClassMetadata($entity);
        if (!$metadata->getTypeOfField($searchField))
        {
            return $this->addWhereAssoc($em, $entity, $builder, $searchField , $searchString, $searchOper);
        }
        $numeric  = array('integer', 'double' , 'boolean');
        $date     = array('date', 'datetime' );

        $operator = array('eq' => ' = ', 'ne' => ' <> ',
                          'lt' => ' < ', 'le' => ' <= ',
                          'gt' => ' > ', 'ge' => ' >= ',

                          'bw' => ' LIKE ', 'bn' => ' NOT LIKE ',
                          'in' => ' IN ', 'ni' => ' NOT IN ',
                          'ew' => ' LIKE ', 'en' => ' NOT LIKE ',
                          'cn' => ' LIKE ', 'nc' => ' NOT LIKE ',
                          'nu' => ' IS NULL', 'nn' => ' IS NOT NULL');
        switch ($searchOper) {
            case 'bw': //'begins with'

                if (in_array($metadata->getTypeOfField($searchField) , $numeric  ))
                {
                    return $this->getNativeQuery($em, $entity , $builder, $searchField. "::text" , $searchString.'%', " LIKE ");
                }
                if (in_array($metadata->getTypeOfField($searchField) , $date  ))
                {
                    return $this->getNativeQuery($em, $entity , $builder, $searchField. "::text" , $searchString.'%', " LIKE ");
                }
                $searchString = $searchString.'%';
                $where = 'u.'.$searchField." LIKE :$searchField" ;
                $builder->andWhere($where)->setParameter($searchField, $searchString);
                return $builder;
            case 'bn': //'does not begin with'
                if (in_array($metadata->getTypeOfField($searchField) , $numeric  ))
                {
                    return $this->getNativeQuery($em, $entity , $builder, $searchField. "::text" , $searchString.'%', " NOT LIKE ");
                }
                $searchString = $searchString.'%';
                $where = 'u.'.$searchField." NOT LIKE :$searchField" ;
                $builder->andWhere($where)->setParameter($searchField, $searchString);
                return $builder;

            case 'in': //'is in'
                $searchString = explode(',', $searchString );
                $where = 'u.'.$searchField. " IN (:$searchField)" ;
                $builder->andWhere($where)->setParameter($searchField, $searchString);
                return $builder;
            case 'ni': //'is not in'
                $searchString = explode(',', $searchString );
                $where = 'u.'.$searchField. " NOT IN ( : $searchField )" ;
                $builder->whereNotIn($where)->setParameter($searchField, $searchString);
                return $builder;

            case 'ew': //'ends with'
                if (in_array($metadata->getTypeOfField($searchField) , $numeric  ))
                {
                    return $this->getNativeQuery($em, $entity , $builder, $searchField. "::text" , '%'.$searchString , " LIKE ");
                }
                $searchString = '%'.$searchString;
                $where = 'u.'.$searchField." LIKE :$searchField" ;
                $builder->andWhere($where)->setParameter($searchField, $searchString);
                return $builder;
            case 'en': //'does not end with'
                if (in_array($metadata->getTypeOfField($searchField) , $numeric  ))
                {
                    return $this->getNativeQuery($em, $entity , $builder, $searchField. "::text" , '%'.$searchString , " NOT LIKE ");
                }
                $searchString = '%'.$searchString;
                $where = 'u.'.$searchField."  NOT LIKE :$searchField" ;
                $builder->andWhere($where)->setParameter($searchField, $searchString);
                return $builder;

            case 'cn': //'contains'
                if (in_array($metadata->getTypeOfField($searchField) , $numeric  ))
                {
                    return $this->getNativeQuery($em, $entity , $builder, $searchField. "::text" , '%'.$searchString.'%' , " LIKE ");
                }
                $searchString = '%'.$searchString.'%';
                $where = 'u.'.$searchField." LIKE :$searchField" ;
                $builder->andWhere($where)->setParameter($searchField, $searchString);
                return $builder;
            case 'nc': //'does not contain'
                if (in_array($metadata->getTypeOfField($searchField) , $numeric  ))
                {
                    return $this->getNativeQuery($em, $entity , $builder, $searchField. "::text" , '%'.$searchString.'%' , " NOT LIKE ");
                }
                $searchString = '%'.$searchString.'%';
                $where = 'u.'.$searchField."  NOT LIKE :$searchField" ;
                $builder->andWhere($where)->setParameter($searchField, $searchString);
                return $builder;


            default:
                $where = 'u.'.$searchField. " " .$operator[$searchOper] .':'.$searchField;
                $builder->andWhere($where)->setParameter($searchField, $searchString);
                return $builder;
        }
    }

    /**
     * Fabrique le "WHERE" pour une association
     * @param entityManager $em
     * @param string $entity       le nom de l'entité
     * @param QueryBuilder $builder
     * @param string $searchField  Le champ recherché
     * @param string $searchString la valeur
     * @param string $searchOper   l'opérateur
     * @return   le QueryBuilder avec un where de plus
     */
    private function addWhereAssoc($em, $entity, $builder, $searchField , $searchString, $searchOper)
    {
        $query = null;
        $metadata = $em->getClassMetadata( $entity);
        $assoc  = $metadata->getAssociationMapping($searchField);
        $joinClass = $assoc["fieldName"];
        return $query;
    }

    /**
     * Fabrique une requete "native"
     * @param entityManager $em
     * @param string $entity       le nom de l'entité
     * @param QueryBuilder $builder
     * @param string $searchField  Le champ recherché
     * @param string $searchString la valeur
     * @param string $searchOper   l'opérateur
     * @return NativeQuery
     */
    private function getNativeQuery($em, $entity , $builder, $searchField , $searchString, $searchOper)
    {
        $metadata = $em->getClassMetadata( $entity);
        $table    = $metadata->getTableName();
        if (strpos( $searchField , '::') > 0 )
        {
            list($field, $type) = explode ('::', $searchField);
            $col = $metadata->getColumnName( $field)."::".$type;
        }
        else{
            $col = $metadata->getColumnName( $searchField);
        }
        $rsm = new ResultSetMappingBuilder($em);
        $rsm->addRootEntityFromClassMetadata($entity, 'u');
        // visiblement, on ne peut pas nommer les parametres
        $sql = 'SELECT *  FROM '.$table.' WHERE '.$col.' '. $searchOper.' ?';
        $query = $em->createNativeQuery($sql, $rsm);
        $query->setParameter(1, $searchString);
        return $query;
    }

}
