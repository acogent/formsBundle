<?php

namespace SGN\FormsBundle\DependencyInjection;

use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\HttpKernel\DependencyInjection\Extension;
use Symfony\Component\DependencyInjection\Loader;

/**
 * This is the class that loads and manages your bundle configuration
 *
 * To learn more see {@link http://symfony.com/doc/current/cookbook/bundles/extension.html}
 */
class SGNFormsExtension extends Extension
{
    /**
     * {@inheritDoc}
     */
    public function load(array $configs, ContainerBuilder $container)
    {
        $configuration = new Configuration();
        $config = $this->processConfiguration($configuration, $configs);

        $container->setParameter('sgn_forms.autocomplete_entities', $config['autocomplete_entities']);

        $container->setParameter('sgn_forms.twig_style',      $config['twig_style']);
        $container->setParameter('sgn_forms.bestof_entity',   $config['bestof_entity']);
        $container->setParameter('sgn_forms.select_entity',   $config['select_entity']);
        $container->setParameter('sgn_forms.orm',             $config['orm']);
        $container->setParameter('sgn_forms.bundles',         $config['bundles']);
        foreach($config['forms'] as $value => $name)
        {
            $container->setParameter(sprintf('sgn_forms.forms.%s', $value), $name["bundle"]);
        }
        $container->setParameter('sgn_forms.entities_fields', $config['entities_fields']);
        $container->setParameter('sgn_forms.entities_fields_hidden', $config['entities_fields_hidden']);
        $loader = new Loader\XmlFileLoader($container, new FileLocator(__DIR__.'/../Resources/config'));
        $loader->load('services.xml');

    }
}
