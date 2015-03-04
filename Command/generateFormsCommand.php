<?php

namespace SGN\FormsBundle\Command;

use Doctrine\Bundle\DoctrineBundle\Mapping\DisconnectedMetadataFactory;
use SGN\FormsBundle\Generator\SGNDoctrineFormGenerator;
use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Generates the CRUD for all entities of DatabaseBundle
 */
class generateFormsCommand extends ContainerAwareCommand
{


    /**
     * Configuration
     */
    protected function configure()
    {
        $this->setName('sgn:generate:forms')->setDescription("Generer les formulaires des entites d'un bundle")->addArgument('bundle', InputArgument::REQUIRED, 'Pour quel bundle voulez-vous generer des formulaires ?');
    }


    /**
     * Executes a GenerateCrudCommand for all the entities of DatabaseBundle
     * @param  InputInterface  $input
     * @param  OutputInterface $output
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $command      = $this->getApplication()->find('sgn:generate:forms');
        $bundleName   = $input->getArgument('bundle');
        $databaseDir  = $this->getContainer()->get('kernel')->getBundle($bundleName)->getPath();
        $entities     = array();
        $pathEntities = $databaseDir.'/Entity';

        if (is_dir($pathEntities) === true) {
            if ($dh = opendir($pathEntities)) {
                while (($file = readdir($dh)) !== false) {
                    if (is_file($pathEntities.'/'.$file) === true
                        && strpos($file, 'Repository') === false
                        && strpos($file, 'Listener') === false
                        && strpos($file, '~') === false
                    ) {
                        $entities[] = basename($file, '.php');
                    }
                }

                closedir($dh);
            }
        }

        $bundle      = $this->getContainer()->get('kernel')->getBundle($bundleName);
        $databaseDir = $bundle->getPath();
        foreach ($entities as $entity) {
            $path = $databaseDir.'/Form/'.$entity.'Type.php';
            if (file_exists($path) === false) {
                $entityClass = $this->getContainer()->get('doctrine')->getAliasNamespace($bundleName).'\\'.$entity;
                $metadata    = $this->getEntityMetadata($entityClass);
                $this->generateForm($bundle, $entity, $metadata);
                $output->writeln('Generating the Form code: <info>OK</info>');
            } else {
                $output->writeln('File exists : <error>'.$path.'</error>');
            }
        }

        $output->writeln('Toutes les entités ont été traitées.');
    }


    protected function getEntityMetadata($entity)
    {
        $factory = new DisconnectedMetadataFactory($this->getContainer()->get('doctrine'));
        $meta    = $factory->getClassMetadata($entity)->getMetadata();

        return $meta;
    }


    /**
     * Tries to generate forms if they don't exist yet and if we need write operations on entities.
     */
    protected function generateForm($bundle, $entity, $metadata)
    {
        $generator      = new SGNDoctrineFormGenerator($this->getContainer()->get('filesystem'));
        $skeletonDirs[] = __DIR__.'/../Resources/skeleton';
        $skeletonDirs[] = __DIR__.'/../Resources';

        $generator->setSkeletonDirs($skeletonDirs);
        $generator->generate($bundle, $entity, $metadata[0]);
    }


}
