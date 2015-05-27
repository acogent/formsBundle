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

use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Helper\DialogHelper;

/**
 * Base class for generator commands.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
abstract class SGNGeneratorCommand extends ContainerAwareCommand
{

    private $generator;

    // only useful for unit tests


    public function setGenerator(Generator $generator)
    {
        $this->generator = $generator;

    }


    abstract protected function createGenerator();


    protected function getGenerator(BundleInterface $bundle = null)
    {
        if (null === $this->generator) {
            $this->generator = $this->createGenerator();
            $this->generator->setSkeletonDirs($this->getSkeletonDirs($bundle));
        }

        return $this->generator;
    }


    protected function getSkeletonDirs(BundleInterface $bundle = null)
    {
        $skeletonDirs = array();

        if (isset($bundle) === true && is_dir($bundle->getPath().'/Resources/SensioGeneratorBundle/skeleton') === true) {
            $skeletonDirs[] = $bundle->getPath().'/Resources/SensioGeneratorBundle/skeleton';
        }

        if (is_dir($this->getContainer()->get('kernel')->getRootdir().'/Resources/SensioGeneratorBundle/skeleton') === true) {
            $skeletonDirs[] = $this->getContainer()->get('kernel')->getRootdir().'/Resources/SensioGeneratorBundle/skeleton';
        }

        $skeletonDirs[] = __DIR__.'/../Resources/skeleton';
        $skeletonDirs[] = __DIR__.'/../Resources';


        return $skeletonDirs;
    }


    protected function getDialogHelper()
    {
        $dialog = $this->getHelperSet()->get('dialog');
        if ($dialog === false || get_class($dialog) !== 'Symfony\Component\Console\Helper\DialogHelper') {
            $dialog = new DialogHelper();
            $this->getHelperSet()->set($dialog);
        }

        return $dialog;
    }


}
