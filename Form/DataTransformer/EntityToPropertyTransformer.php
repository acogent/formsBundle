<?php

namespace SGN\FormsBundle\Form\DataTransformer;


use Symfony\Component\Form\DataTransformerInterface;
use Doctrine\ORM\EntityManager;
use Symfony\Component\PropertyAccess\PropertyAccess;
use Symfony\Component\Form\Exception\UnexpectedTypeException;
use Symfony\Component\Form\Exception\TransformationFailedException;
use Symfony\Component\Form\Exception\FormException;

class EntityToPropertyTransformer implements DataTransformerInterface
{
    protected $em;
    protected $class;
    protected $property;
    protected $unitOfWork;
    protected $value;

    public function __construct(EntityManager $em, $class, $property, $value)
    {
        $this->em         = $em;
        $this->unitOfWork = $this->em->getUnitOfWork();
        $this->class      = $class;
        $this->property   = $property;
        $this->value      = $value;

    }

    public function transform($entity)
    {
        if ( NULL === $entity )
        {
            return NULL;
        }

        if (!$this->unitOfWork->isInIdentityMap($entity))
        {
            throw new FormException('Entities passed to the choice field must be managed');
        }
        if ($this->property) 
        {
            $propertyAccessor = PropertyAccess::getPropertyAccessor();
            return $propertyAccessor->getValue($entity, $this->property);
        }
        return current($this->unitOfWork->getEntityIdentifier($entity));
    }

    public function reverseTransform($prop_value)
    {
        // $prop_value est la valeur de “property” si le champ reste inchangé, la valeur de ”value” si le champ a changé.

        if ( !$prop_value )
        {
            return NULL;
        }

        $entity = $this->em->getRepository($this->class)->findOneBy(array($this->property => $prop_value));
        if ( $entity != NULL ) return $entity;

        $entity = $this->em->getRepository($this->class)->findOneBy(array($this->value => $prop_value));
        return $entity;
    }

}