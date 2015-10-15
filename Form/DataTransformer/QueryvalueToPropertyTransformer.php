<?php

namespace SGN\FormsBundle\Form\DataTransformer;

use Symfony\Component\Form\DataTransformerInterface;
use Doctrine\ORM\EntityManager;
use Symfony\Component\PropertyAccess\PropertyAccess;
use Symfony\Component\Form\Exception\UnexpectedTypeException;
use Symfony\Component\Form\Exception\TransformationFailedException;

class QueryvalueToPropertyTransformer implements DataTransformerInterface
{
    protected $em;
    protected $query;
    protected $property;
    protected $value;

    public function __construct(EntityManager $em, $query, $property, $value)
    {
        $this->em       = $em;
        $this->query    = $query;
        $this->property = $property;
        $this->value    = $value;
    }

    public function transform($val_value)
    {
        if (!$val_value)
        {
            return NULL;
        }

        $result = $this->em
                       ->createQuery($this->query." AND e.".$this->value." = :val_value")
                       ->setParameter('val_value', $val_value)
                       ->getOneOrNullResult();

        return $result[$this->property];
    }

    public function reverseTransform($prop_value)
    {
        // $prop_value est la valeur de “property” si le champ reste inchangé, la valeur de ”value” si le champ a changé.

        if (!$prop_value)
        {
            return NULL;
        }

        $result = $this->em
                       ->createQuery($this->query." AND e.".$this->property." = :prop_value")
                       ->setParameter('prop_value', $prop_value)
                       ->getOneOrNullResult();

        return $result[$this->value];
    }

}
