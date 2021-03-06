<?php

namespace SGN\FormsBundle\Form\DataTransformer;

use Symfony\Component\Form\DataTransformerInterface;
use Doctrine\ORM\EntityManager;
use Symfony\Component\PropertyAccess\PropertyAccess;

class ClassvalueToPropertyTransformer implements DataTransformerInterface
{

    protected $em;

    protected $class;

    protected $property;

    protected $value;


    public function __construct(EntityManager $em, $class, $property, $value)
    {
        $this->em       = $em;
        $this->class    = $class;
        $this->property = $property;
        $this->value    = $value;
    }

    public function transform($val_value)
    {
        if (!$val_value) {
            return null;
        }

        $entity = $this->em->getRepository($this->class)->findOneBy(array($this->value => $val_value));

        if (null === $entity) {
            return null;
        }

        $propertyAccessor = PropertyAccess::getPropertyAccessor();
        return $propertyAccessor->getValue($entity, $this->property);

    }

    public function reverseTransform($prop_value)
    {
        // $prop_value est la valeur de “property” si le champ reste inchangé, la valeur de ”value” si le champ a changé.

        if (!$prop_value) {
            return null;
        }

        $entity = $this->em->getRepository($this->class)->findOneBy(array($this->property => $prop_value));

        if (null === $entity) {
            return $prop_value;
        }

        $propertyAccessor = PropertyAccess::getPropertyAccessor();
        return $propertyAccessor->getValue($entity, $this->value);
    }
}
