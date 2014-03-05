<?php

namespace SGN\FormsBundle\Form\Type;

use SGN\FormsBundle\Form\DataTransformer\ClassvalueToPropertyTransformer;
use SGN\FormsBundle\Form\DataTransformer\EntityToPropertyTransformer;
use SGN\FormsBundle\Form\DataTransformer\EntityToQuerypropertyTransformer;
use SGN\FormsBundle\Form\DataTransformer\EntityToTostringTransformer;
use SGN\FormsBundle\Form\DataTransformer\QueryvalueToPropertyTransformer;
use Symfony\Bridge\Doctrine\RegistryInterface;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Exception\FormException;
use Symfony\Component\Form\Exception\LogicException;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormView;
use Symfony\Component\OptionsResolver\OptionsResolverInterface;

class AjaxAutocompleteType extends AbstractType
{
    private $container;

    public function __construct($container)
    {
        $this->container = $container;
    }

    public function setDefaultOptions(OptionsResolverInterface $resolver)
    {
        $resolver->setDefaults(array(
            'entity_alias' => null,
            'class'        => null,
            'property'     => null,
            'value'        => null,
            'compound'     => false
        ));
    }

    public function getName()
    {
        return 'sgn_ajax_autocomplete';
    }

    public function getParent()
    {
        return 'text';
    }

    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $entities = $this->container->getParameter('sgn_forms.autocomplete_entities');

        if (null === $options['entity_alias']) {
            throw new LogicException('You must provide a entity alias "entity_alias" and tune it in config file');
        }

        if (!isset ($entities[$options['entity_alias']])){
            throw new LogicException('There are no entity alias "' . $options['entity_alias'] . '" in your config file');
        }

        $options['class']    = $entities[$options['entity_alias']]['class'];
        $options['property'] = $entities[$options['entity_alias']]['property'];
        $options['value']    = $entities[$options['entity_alias']]['value'];
        $options['entity']   = $entities[$options['entity_alias']]['entity'];
        $options['query']    = $entities[$options['entity_alias']]['query'];

        if ( $options['class'] != "query" 
          && $options['query'] == "class"
          && $options['value'] == 'id'
          && $options['entity'] 
          && $options['property'] != "__toString" )
        {
            $builder->addViewTransformer(new EntityToPropertyTransformer(
                $this->container->get('doctrine')->getManager(),
                $options['class'],
                $options['property'],
                $options['value']
            ), true);
        }

        if ( $options['class'] != "query" 
          && $options['property'] == "__toString" )
        {
            $builder->addViewTransformer(new EntityToTostringTransformer(
                $this->container->get('doctrine')->getManager(),
                $options['class'],
                $options['value']
            ), true);
        }

        if ( $options['class'] != "query" 
          && $options['value'] != 'id'
         && !$options['entity'] )
        {
            $builder->addViewTransformer(new ClassvalueToPropertyTransformer(
                $this->container->get('doctrine')->getManager(),
                $options['class'],
                $options['property'],
                $options['value']
            ), true);
        }

        if ( $options['query'] != "class" 
          && $options['class'] == "query" )
        {
            $builder->addViewTransformer(new QueryvalueToPropertyTransformer(
                $this->container->get('doctrine')->getManager(),
                $options['query'],
                $options['property'],
                $options['value']
            ), true);
        }

        if ( $options['query'] != "class" 
          && $options['class'] != "query" )
        {
            $builder->addViewTransformer(new EntityToQuerypropertyTransformer(
                $this->container->get('doctrine')->getManager(),
                $options['class'],
                $options['query'],
                $options['property'],
                $options['value']
            ), true);
        }

        $builder->setAttribute('entity_alias', $options['entity_alias']);
    }

    public function buildView(FormView $view, FormInterface $form, array $options)
    {
        $view->vars['entity_alias'] = $form->getConfig()->getAttribute('entity_alias');
    }

}
