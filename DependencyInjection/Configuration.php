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
        $rootNode    = $treeBuilder->root('sgn_forms');
        $rootNode->children()->scalarNode('template')->defaultValue('SGNAdminInterfaceBundle::layout.html.twig')->end()->scalarNode('orm')->defaultValue('default')->end()->arrayNode('bestof_entity')->prototype('scalar')->end()->defaultValue(array('*'))->end()->arrayNode('smallFields')->prototype('scalar')->end()->defaultValue(array(''))->end()->arrayNode('select_entity')->prototype('scalar')->end()->defaultValue(array('*'))->end()->arrayNode('bundles')->prototype('scalar')->end()->cannotBeEmpty()->end()->arrayNode('forms')->useAttributeAsKey('name')->prototype('array')->beforeNormalization()->ifString()->then(function ($v) {
            return array('bundle' => $v);
        })->end()->children()->scalarNode('bundle')->end()->end()->end()->end()->arrayNode('entities_filters')->beforeNormalization()->ifString()->then(function ($value) {
                                return array($value);
        })->end()->useAttributeAsKey('name')->prototype('array')->children()->scalarNode('order')->end()->scalarNode('hidden')->end()->booleanNode('audit')->end()->booleanNode('extended')->end()->scalarNode('rel_hidden')->end()->end()->end()->end()->scalarNode('twig_style')->defaultValue("{{ asset('bundles/sgnforms/css/style.css') }}")->end()->arrayNode('autocomplete_entities')->useAttributeAsKey('id')->prototype('array')->children()->scalarNode('class')->defaultValue('query')->cannotBeEmpty()->end()->scalarNode('property')->cannotBeEmpty()->end()->scalarNode('method')->defaultValue(null)->cannotBeEmpty()->end()->scalarNode('value')->defaultValue('id')->cannotBeEmpty()->end()->scalarNode('role')->defaultValue('IS_AUTHENTICATED_ANONYMOUSLY')->cannotBeEmpty()->end()->scalarNode('search')->defaultValue('begins_with')->cannotBeEmpty()->end()->scalarNode('target')->defaultValue('property')->cannotBeEmpty()->end()->scalarNode('show')->defaultValue('property')->cannotBeEmpty()->end()->scalarNode('query')->defaultValue('class')->cannotBeEmpty()->end()->scalarNode('minLength')->defaultValue(3)->cannotBeEmpty()->end()->booleanNode('case_insensitive')->defaultTrue()->end()->booleanNode('entity')->defaultTrue()->end()->end()->end()->end();
        return $treeBuilder;
    }
}
