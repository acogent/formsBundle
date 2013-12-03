<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace SGN\FormsBundle\Command;

use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Output\Output;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\HttpKernel\Bundle\BundleInterface;
use Sensio\Bundle\GeneratorBundle\Command\Helper\DialogHelper;
use Sensio\Bundle\GeneratorBundle\Manipulator\RoutingManipulator;


use SGN\FormsBundle\Generator\SGNDoctrineFormGenerator;
/**
 * Generates a CRUD for a Doctrine entity.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
class generateFormCommand extends SGNGenerateDoctrineCommand
{
    // private $formGenerator;

    /**
     * @see Command
     */
    protected function configure()
    {
        $this
            ->setDefinition(array(
                new InputOption('entity', '', InputOption::VALUE_REQUIRED, "Le nom de l'entité"),
             ))
            ->setDescription("Genere le formulaire d'une entité")
            ->setName('SGN:generate:form')
        ;
    }

    /**
     * @see Command
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $dialog = $this->getDialogHelper();

        $entity                = Validators::validateEntityName($input->getOption('entity'));
        list($bundle, $entity) = $this->parseShortcutNotation($entity);
        $format                = Validators::validateFormat($input->getOption('format'));
        $prefix                = $this->getRoutePrefix($input, $entity);
       
        $entityClass   = $this->getContainer()->get('doctrine')->getAliasNamespace($bundle).'\\'.$entity;
        $metadata      = $this->getEntityMetadata($entityClass);
        $bundle        = $this->getContainer()->get('kernel')->getBundle($bundle);
        $entityManager = $this->getContainer()->get('doctrine')->getManager();

        $this->generateForm($bundle, $entity, $metadata);
        $output->writeln('Generating the Form code: <info>OK</info>');

        $dialog->writeGeneratorSummary($output, $errors);
    }



    protected function getRoutePrefix(InputInterface $input, $entity)
    {
        $prefix = $input->getOption('route-prefix') ?: strtolower(str_replace(array('\\', '/'), '_', $entity));

        if ($prefix && '/' === $prefix[0]) {
            $prefix = substr($prefix, 1);
        }

        return $prefix;
    }

    // protected function createGenerator($bundle = null)
    // {
    //     return new SGNDoctrineCrudGenerator($this->getContainer()->get('filesystem'));
    // }

    /**
     * Tries to generate forms if they don't exist yet and if we need write operations on entities.
     */
    protected function generateForm($bundle, $entity, $metadata)
    {
        try {
           $this->getFormGenerator($bundle)->generate(
            $bundle,
            $entity,
            $metadata[0]);
        }

        catch (\RuntimeException $e ) {
            // form already exists
        }
    }

    protected function getFormGenerator($bundle = null)
    {

        if (null === $this->formGenerator) {
            $this->formGenerator = new SGNDoctrineFormGenerator($this->getContainer()->get('filesystem'));
            $this->formGenerator->setSkeletonDirs($this->getSkeletonDirs($bundle));
        }

        return $this->formGenerator;
    }

    // public function setFormGenerator(SGNDoctrineCrudGenerator $formGenerator)
    // {
    //     $this->formGenerator = $formGenerator;
    // }
}
