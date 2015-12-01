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

use Doctrine\ORM\Mapping\ClassMetadataInfo;

/**
 * Generator is the base class for all generators.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
class SGNGenerator
{

    private $skeletonDirs;


    /**
     * Sets an array of directories to look for templates.
     *
     * The directories must be sorted from the most specific to the most
     * directory.
     *
     * @param array $skeletonDirs An array of skeleton dirs
     */
    public function setSkeletonDirs($skeletonDirs)
    {
        $this->skeletonDirs = is_array($skeletonDirs) ? $skeletonDirs : array($skeletonDirs);
    }


    protected function render($template, $parameters)
    {
        $loader = new \Twig_Loader_Filesystem($this->skeletonDirs);
        $twig   = new \Twig_Environment(
            $loader,
            array(
             'debug'            => true,
             'cache'            => false,
             'strict_variables' => true,
             'autoescape'       => false,
            )
        );

            return $twig->render($template, $parameters);
    }


    protected function renderFile($template, $target, $parameters)
    {
        if (is_dir(dirname($target) === false)) {
            mkdir(dirname($target), 0777, true);
        }

        return file_put_contents($target, $this->render($template, $parameters));
    }


    /**
     * Returns an array of fields. Fields can be both column fields and
     * association fields.
     *
     * @param  ClassMetadataInfo $metadata
     * @return array             $fields
     */
    protected function getFieldsFromMetadata(ClassMetadataInfo $metadata)
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
    protected function getFieldsOneToManyFromMetadata(ClassMetadataInfo $metadata)
    {
        $fields = array();

        foreach ($metadata->associationMappings as $fieldName => $relation) {
            if ($relation['type'] === ClassMetadataInfo::ONE_TO_MANY) {
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
    protected function getFieldsManyToOneFromMetadata(ClassMetadataInfo $metadata)
    {
        $fields = array();

        foreach ($metadata->associationMappings as $fieldName => $relation) {
            if ($relation['type'] === ClassMetadataInfo::MANY_TO_ONE) {
                $fields[] = $fieldName;
            }
        }

        return $fields;
    }
}
