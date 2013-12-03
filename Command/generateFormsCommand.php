<?php

namespace SGN\FormsBundle\Command;


use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Input\ArrayInput;

use SGN\FormsBundle\Command\generateFormCommand;

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
        $this->setName('SGN:generate:forms')
             ->setDescription("Generer les formulaires des entites d'un bundle")
             ->addArgument('bundle', InputArgument::REQUIRED, 'Pour quel bundle voulez-vous generer des formulaires ?')
    }

    /**
     * Executes a GenerateCrudCommand for all the entities of DatabaseBundle
     * @param  InputInterface  $input
     * @param  OutputInterface $output
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $command      = $this->getApplication()->find('SGN:generate:forms');
        $bundleName   = $input->getArgument('bundle');
        $database_dir = $this->getContainer()->get('kernel')->getBundle($bundleName)->getPath();
        $entities     = array();
        $pathEntities = $database_dir.'/Entity';

        if (is_dir($pathEntities)) {
            if ($dh = opendir($pathEntities)) {
                while (($file = readdir($dh)) !== false) {
                    if (is_file($pathEntities.'/'.$file)
                        && strpos($file, 'Repository') === false
                        && strpos($file, "~") === false) {
                        $entities[] = basename($file, '.php');
                    }
                }
                closedir($dh);
            }
        }

        $arguments = array(
            'command'      => 'SGN:g:f',
        );
        foreach ($entities as $entity) {
            $output->writeln('Traitement de l\'entite '.$entity.'.');
            $arguments['--entity'] = $bundleName.':'.$entity;
            $arguments['--route-prefix'] = '/crud/'.strtolower($entity);

            $path = $database_dir."/Form/".$entity."Type.php";
            if (file_exists($path))
            {
               $output->writeln("Le fichier $path existe, on passe." );
               continue;
            }
            $input = new ArrayInput($arguments);
            $input->setInteractive(false);
            $returnCode = $command->run($input, $output);
        }
        $output->writeln('Toutes les entités ont été traitées.');
    }

}
