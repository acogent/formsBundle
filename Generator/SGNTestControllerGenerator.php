<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace SGN\FormsBundle\Generator;

use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpKernel\Bundle\BundleInterface;
use Doctrine\ORM\Mapping\ClassMetadataInfo;

/**
 * Generates a Test class based on a Doctrine entity.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 * @author Hugo Hamon <hugo.hamon@sensio.com>
 */
class SGNTestControllerGenerator extends SGNGenerator
{

    private $filesystem;

    private $className;

    private $classPath;


    /**
     * Constructor.
     *
     * @param Filesystem $filesystem A Filesystem instance
     */
    public function __construct(Filesystem $filesystem)
    {
        $this->filesystem = $filesystem;
    }


    public function getClassName()
    {
        return $this->className;
    }


    public function getClassPath()
    {
        return $this->classPath;
    }


    /**
     * Generates the entity form class if it does not exist.
     *
     * @param BundleInterface   $bundle   The bundle in which to create the class
     * @param string            $entity   The entity relative class name
     * @param ClassMetadataInfo $metadata The entity metadata class
     */
    public function generate(BundleInterface $bundle, $entity, ClassMetadataInfo $metadata)
    {
        $parts       = explode('\\', $entity);
        $entityClass = array_pop($parts);

        $this->className = $entityClass.'ControllerTest';
        $dirPath         = $bundle->getPath().'/Tests/Controller';
        $this->classPath = $dirPath.'/'.str_replace('\\', '/', $entity).'ControllerTest.php';

        if (file_exists($this->classPath)) {
            throw new \RuntimeException(sprintf('Unable to generate the %s test class as it already exists under the %s file', $this->className, $this->classPath));
        }


        $parts = explode('\\', $entity);
        array_pop($parts);
        $namespace     = $bundle->getNamespace();
        $frm_namespace = strtolower(str_replace('\\', '_', $namespace));
        $this->renderFile(
            'Tests/TestController.php.twig',
            $this->classPath,
            array(
             'fields'           => $this->getFieldsFromMetadata($metadata),
             'fieldsManyToOne'  => $this->getFieldsManyToOneFromMetadata($metadata),
             'fieldsOneToMany'  => $this->getFieldsOneToManyFromMetadata($metadata),
             'namespace'        => $bundle->getNamespace(),
             'frm_namespace'    => $frm_namespace,
             'entity_namespace' => implode('\\', $parts),
             'entity_class'     => $entityClass,
             'bundle'           => $bundle->getName(),
             'test_class'       => $this->className,
            // 'form_type_name'   => strtolower(str_replace('\\', '_', $bundle->getNamespace()).($parts ? '_' : '').implode('_', $parts).'_'.substr($this->className, 0, -4)),
            )
        );
    }


    /**
     * Returns an array of fields. Fields can be both column fields and
     * association fields.
     *
     * @param  ClassMetadataInfo $metadata
     * @return array             $fields
     */
    private function getFieldsFromMetadata(ClassMetadataInfo $metadata)
    {
        $fields = (array) $metadata->fieldNames;

        // Remove the primary key field if it's not managed manually
        if (!$metadata->isIdentifierNatural()) {
            $fields = array_diff($fields, $metadata->identifier);
        }

        return $fields;
    }


   /**
     * Returns an array of fields. Fields can be both column fields and
     * association fields.
     *
     * @param  ClassMetadataInfo $metadata
     * @return array             $fields
     */
    private function getFieldsOneToManyFromMetadata(ClassMetadataInfo $metadata)
    {
        $fields = array();

        foreach ($metadata->associationMappings as $fieldName => $relation) {
            if ($relation['type'] == ClassMetadataInfo::ONE_TO_MANY) {
// $fields[] = substr($fieldName,0,strlen($fieldName)-1);
                $fields[] = $fieldName;
            }
        }

        return $fields;
    }


   /**
     * Returns an array of fields. Fields can be both column fields and
     * association fields.
     *
     * @param  ClassMetadataInfo $metadata
     * @return array             $fields
     */
    private function getFieldsManyToOneFromMetadata(ClassMetadataInfo $metadata)
    {
        $fields = array();

        foreach ($metadata->associationMappings as $fieldName => $relation) {
            if ($relation['type'] == ClassMetadataInfo::MANY_TO_ONE) {
                $fields[] = $fieldName;
            }
        }

        return $fields;
    }


}
