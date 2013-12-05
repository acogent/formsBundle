<?php

namespace SGN\FormsBundle\DependencyInjection;

use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

/**
 * This is the class that validates and merges configuration from your app/config files
 *
 * To learn more see {@link http://symfony.com/doc/current/cookbook/bundles/extension.html#cookbook-bundles-extension-config-class}
 */
class Configuration implements ConfigurationInterface
{
    /**
     * {@inheritDoc}
     */
    public function getConfigTreeBuilder()
    {
        $treeBuilder = new TreeBuilder();
        $rootNode = $treeBuilder->root('sgn_forms');

        $rootNode
            ->children()
                ->arrayNode('twig_bestof')
                    ->prototype('scalar')->end()
                    ->defaultValue(array('*'))
                ->end()
                ->scalarNode('twig_template')->defaultValue('SGNTemplateBundle:DevBlog:base.html.twig')->end()
                ->scalarNode('twig_style')->defaultValue("{{ asset('bundles/sgnforms/css/style.css') }}")->end()
           

                ->arrayNode('autocomplete_entities')
                    ->useAttributeAsKey('id')
                    ->prototype('array')
                        ->children()
                            ->scalarNode('class')
                                ->cannotBeEmpty()
                            ->end()
                            ->scalarNode('property')
                                ->cannotBeEmpty()
                            ->end()
                            ->scalarNode('value')
                                ->defaultValue('id')
                                ->cannotBeEmpty()
                            ->end()
                            ->scalarNode('role')
                                ->defaultValue('IS_AUTHENTICATED_ANONYMOUSLY')
                                ->cannotBeEmpty()
                            ->end()
                            ->scalarNode('search')
                                ->defaultValue('begins_with')
                                ->cannotBeEmpty()
                            ->end()
                ->booleanNode('case_insensitive')
                                 ->defaultTrue()
                            ->end()
                        ->end()
                    ->end()
                ->end()
                ;

        return $treeBuilder;
    }
}