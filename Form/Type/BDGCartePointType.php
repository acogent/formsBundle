<?php

namespace SGN\FormsBundle\Form\Type;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormView;
use Symfony\Component\OptionsResolver\OptionsResolverInterface;
use Symfony\Component\Process\Exception\RuntimeException;
use Symfony\Component\Process\Process;
use Symfony\Component\PropertyAccess\PropertyAccess;

class BDGCartePointType extends AbstractType
{


    public function setDefaultOptions(OptionsResolverInterface $resolver)
    {
        $resolver->setDefaults(
            array(
             'data_class' => 'CrEOF\Spatial\PHP\Types\Geometry\Point',
             'domaine'    => null,
            )
        );
    }


    public function getName()
    {
        return 'bdg_carte_point';
    }


    public function getParent()
    {
        return 'text';
    }


    /**
     * Passe l'URL de l'image à la vue.
     *
     * @param \Symfony\Component\Form\FormView $view
     * @param \Symfony\Component\Form\FormInterface $form
     * @param array $options
     */
    public function buildView(FormView $view, FormInterface $form, array $options)
    {
        $data       = $form->getData();
        $parentData = $form->getParent()->getData();
        $lon        = 2;
        $lat        = 40;
        if (null !== $parentData && null !== $data) {
            $accessor = PropertyAccess::createPropertyAccessor();
            $geom     = $accessor->getValue($parentData, 'point');
            $lon      = $geom->getX();
            $lat      = $geom->getY();
        }

        $view->vars['lon']  = $lon;
        $view->vars['lat']  = $lat;
        $view->vars['zoom'] = 14;
    }


}
