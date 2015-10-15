<?php

namespace SGN\FormsBundle\Form\DataTransformer;


use Symfony\Component\Form\DataTransformerInterface;
use Doctrine\ORM\EntityManager;
use Symfony\Component\PropertyAccess\PropertyAccess;
use Symfony\Component\Form\Exception\UnexpectedTypeException;
use Symfony\Component\Form\Exception\TransformationFailedException;


class EntityToQuerypropertyTransformer implements DataTransformerInterface
{
    protected $em;
    protected $class;
    protected $query;
    protected $property;
    protected $unitOfWork;
    protected $value;

    public function __construct(EntityManager $em, $class, $query, $property, $value)
    {
        $this->em         = $em;
        $this->unitOfWork = $this->em->getUnitOfWork();
        $this->class      = $class;
        $this->query      = $query;
        $this->property   = $property;
        $this->value      = $value;

    }

    public function transform($entity)
    {
        if (NULL === $entity)
        {
            return NULL;
        }

        if (!$this->unitOfWork->isInIdentityMap($entity))
        {
            throw new TransformationFailedException('Entities passed to the choice field must be managed');
        }
        if ($this->property) 
        {
            $propertyAccessor = PropertyAccess::getPropertyAccessor();
            $val_value = $propertyAccessor->getValue($entity, $this->value);

            $result = $this->em
                           ->createQuery($this->query." AND e.".$this->value." = :val_value")
                           ->setParameter('val_value', $val_value)
                           ->getOneOrNullResult();
            
            $property = strpos($this->property, ".") !== FALSE ? explode(".", $this->property)[1] : $this->property;

            return $result[$property];
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

        $prop_query = strpos($this->property, ".") !== FALSE ? $this->property : "e.".$this->property;

        $result = $this->em
                       ->createQuery($this->query." AND ".$prop_query." = :prop_value")
                       ->setParameter('prop_value', $prop_value)
                      ->getOneOrNullResult();

        $entity = $this->em->getRepository($this->class)->findOneBy(array($this->value => $result[$this->value]));

        if ( $entity == NULL ) $entity = $this->em->getRepository($this->class)->findOneBy(array($this->value => $prop_value));

        return $entity;
    }

}