<?php

namespace SGN\FormsBundle\Command;

use Doctrine\Bundle\DoctrineBundle\Mapping\DisconnectedMetadataFactory;
use SGN\FormsBundle\Generator\SGNTestControllerGenerator;
use SGN\FormsBundle\Generator\SGNTestValidatorGenerator;
use SGN\FormsBundle\Utils\SGNTwigCrudTools;
use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Finder\Finder;

/**
 * Generates the CRUD for all entities of DatabaseBundle
 */
class generateTestsCommand extends ContainerAwareCommand
{


    /**
     * Configuration
     */
    protected function configure()
    {
        $this->setName('sgn:generate:tests')->setDescription("Generer les tests fonctionnels des entites d'un bundle")
        ->addArgument('bundle', InputArgument::REQUIRED, 'Pour quel bundle voulez-vous generer des tests fonctionnels ?')
        ->addArgument('dir', InputArgument::OPTIONAL, 'Un sous-dossier contenant les entités ?');
    }


    /**
     * Executes a GenerateCrudCommand for all the entities of DatabaseBundle
     * @param  InputInterface  $input
     * @param  OutputInterface $output
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $command      = $this->getApplication()->find('sgn:generate:tests');
        $bundleName   = $input->getArgument('bundle');
        $dir          = $input->getArgument('dir');
        $databaseDir  = $this->getContainer()->get('kernel')->getBundle($bundleName)->getPath();
        $entities     = array();
        $pathEntities = $databaseDir.'/Entity/'.$dir;

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
        $dirPath     = $bundle->getPath().'/Tests/Controller';
        copy(__DIR__.'/../Resources/skeleton/Tests/InterfaceControllerTest.php', $dirPath.'/InterfaceControllerTest.php.dist');
        copy(__DIR__.'/../Resources/skeleton/Tests/ModelControllerTest.php', $dirPath.'/ModelControllerTest.php.dist');

        $pathTest = $databaseDir.'/Tests/Controller/';

        foreach ($entities as $entity) {
            $finder   = new Finder();
            $finder->files()->in($pathTest)->name($entity.'ControllerTest.php');

            if (iterator_count($finder) > 0) {
                $output->writeln('File exists : <comment>'.$entity.'ControllerTest.php</comment>');
                continue;
            }

            $path = $databaseDir.'/Tests/Controller/'.$entity.'ControllerTest.php';
            if ($dir !== null) {
                 $path = $databaseDir.'/Tests/Controller/'.$dir.'/'.$entity.'ControllerTest.php';
            }

            $entityClass = $this->getContainer()->get('doctrine')->getAliasNamespace($bundleName).$entity;
            if ($dir !== null) {
                $entityClass = $this->getContainer()->get('doctrine')->getAliasNamespace($bundleName).'\\'.$dir.'\\'.$entity;
            }

            $metadata    = $this->getEntityMetadata($entityClass);
            $this->generateControllerTest($bundle, $entity, $metadata);
            $output->writeln('Generating the test code: <info>'.$path.'</info>');
        }
        $output->writeln('---');
        $output->writeln('Tous les Tests/Controller des entités ont été traitées.');
        $output->writeln('---');

        $pathTest = $databaseDir.'/Tests/Validator/';
        foreach ($entities as $entity) {
            $finder   = new Finder();
            $finder->files()->in($pathTest)->name($entity.'ValidatorTest.php');
            if (iterator_count($finder) > 0) {
                $output->writeln('File exists : <comment>'.$entity.'ValidatorTest.php</comment>');
                continue;
            }

            $path = $databaseDir.'/Tests/Validator/'.$entity.'ValidatorTest.php';

            $entityClass = $this->getContainer()->get('doctrine')->getAliasNamespace($bundleName).'\\'.$entity;
            if ($dir !== null) {
                $entityClass = $this->getContainer()->get('doctrine')->getAliasNamespace($bundleName).'\\'.$dir.'\\'.$entity;
            }

            $metadata    = $this->getEntityMetadata($entityClass);
            $this->generateValidatorTest($bundle, $entity, $metadata);
            $output->writeln('Generating the test code: <info>'.$path.'</info>');
        }
        $output->writeln('---');
        $output->writeln('Tous les /Tests/Validator des entités ont été traitées.');
        $output->writeln('---');
    }


    protected function getEntityMetadata($entity)
    {
        $factory = new DisconnectedMetadataFactory($this->getContainer()->get('doctrine'));
        $meta    = $factory->getClassMetadata($entity)->getMetadata();

        return $meta;
    }


    /**
     * Tries to generate tests if they don't exist yet and if we need write operations on entities.
     */
    protected function generateControllerTest($bundle, $entity, $metadata)
    {
        $generator      = new SGNTestControllerGenerator($this->getContainer()->get('filesystem'));
        $skeletonDirs[] = __DIR__.'/../Resources/skeleton';
        $skeletonDirs[] = __DIR__.'/../Resources';

        $generator->setSkeletonDirs($skeletonDirs);
        $generator->generate($bundle, $entity, $metadata[0]);
    }

     /**
     * Tries to generate tests if they don't exist yet and if we need write operations on entities.
     */
    protected function generateValidatorTest($bundle, $entity, $metadata)
    {
        $generator      = new SGNTestValidatorGenerator($this->getContainer()->get('filesystem'));
        $skeletonDirs[] = __DIR__.'/../Resources/skeleton';
        $skeletonDirs[] = __DIR__.'/../Resources';

        $generator->setSkeletonDirs($skeletonDirs);
        $generator->generate($bundle, $entity, $metadata[0]);
    }

}
