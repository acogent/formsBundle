<?php

namespace SGN\FormsBundle\Command;

use Doctrine\Bundle\DoctrineBundle\Mapping\DisconnectedMetadataFactory;
use SGN\FormsBundle\Generator\SGNDoctrineFormGenerator;
use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Finder\Finder;
use Symfony\Component\HttpKernel\KernelInterface;

/**
 * Generates the CRUD for all entities of DatabaseBundle
 */
class generateFormsCommand extends ContainerAwareCommand
{

    protected $kernel;


    public function __construct(KernelInterface $kernel)
    {
        $this->kernel = $kernel;
    }

    /**
     * Configuration
     */
    protected function configure()
    {
        $this->setName('sgn:generate:forms')->setDescription("Generer les formulaires des entites d'un bundle")->addArgument('bundle', InputArgument::REQUIRED, 'Pour quel bundle voulez-vous generer des formulaires ?')->addArgument('dir', InputArgument::OPTIONAL, 'Un sous-dossier contenant les entités ?');
    }


    /**
     * Executes a GenerateCrudCommand for all the entities of DatabaseBundle
     * @param  InputInterface  $input
     * @param  OutputInterface $output
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $bundleName   = $input->getArgument('bundle');
        $databaseDir  = $this->getContainer()->get('kernel')->getBundle($bundleName)->getPath();
        $dir          = $input->getArgument('dir');
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
        $pathForm    = $databaseDir.'/Form/';

        foreach ($entities as $entity) {
            $finder = new Finder();
            $finder->files()->in($pathForm)->name($entity.'Type.php');

            if (iterator_count($finder) > 0) {
                $output->writeln('File exists : <comment>'.$entity.'Type.php</comment>');
                continue;
            }

            $path = $databaseDir.'/Form/'.$entity.'Type.php';
            if ($dir !== null) {
                 $path = $databaseDir.'/Form/'.$dir.'/'.$entity.'Type.php';
            }

            if (file_exists($path) === false) {
                $entityClass = $this->getContainer()->get('doctrine')->getAliasNamespace($bundleName).'\\'.$entity;
                if ($dir !== null) {
                    $entityClass = $this->getContainer()->get('doctrine')->getAliasNamespace($bundleName).'\\'.$dir.'\\'.$entity;
                }

                $metadata = $this->getEntityMetadata($entityClass);
                $this->generateForm($bundle, $entity, $metadata);
                $output->writeln('Generating the Form code: /Form/'.$entity.'Type.php<info>OK</info>');
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
        $skeletonDirs[] = $this->kernel->locateResource('@SGNFormsBundle/Resources/skeleton/');
        $skeletonDirs[] = $this->kernel->locateResource('@SGNFormsBundle/Resources/');


        $generator->setSkeletonDirs($skeletonDirs);
        $generator->generate($bundle, $entity, $metadata[0]);
    }
}
