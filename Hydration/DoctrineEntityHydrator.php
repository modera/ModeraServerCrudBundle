<?php

namespace Modera\ServerCrudBundle\Hydration;

use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\PropertyAccess\PropertyAccess;
use Symfony\Component\PropertyAccess\PropertyAccessorInterface;

/**
 * This hydrator relies on existence of service container with id "doctrine.orm.entity_manager".
 *
 * @author    Sergei Lissovski <sergei.lissovski@modera.org>
 * @copyright 2014 Modera Foundation
 */
class DoctrineEntityHydrator
{
    private ?PropertyAccessorInterface $accessor = null;

    /**
     * @var string[]
     */
    private array $excludedFields = [];

    /**
     * @var array<string, string>
     */
    private array $associativeFieldMappings = [];

    /**
     * @param string[] $excludedFields
     */
    public static function create(array $excludedFields = []): self
    {
        $me = new self();
        $me->excludeFields($excludedFields);

        return $me;
    }

    /**
     * @param string[] $fields
     */
    public function excludeFields(array $fields): self
    {
        $this->excludedFields = $fields;

        return $this;
    }

    public function mapRelation(string $relationFieldName, string $expression): self
    {
        $this->associativeFieldMappings[$relationFieldName] = $expression;

        return $this;
    }

    /**
     * @return array<string, mixed>
     */
    public function __invoke(object $entity, ContainerInterface $container): array
    {
        /** @var EntityManagerInterface $em */
        $em = $container->get('doctrine.orm.entity_manager');

        if (!$this->accessor) {
            $this->accessor = PropertyAccess::createPropertyAccessor();
        }

        $meta = $em->getClassMetadata(\get_class($entity));

        $result = [];
        foreach ($meta->getFieldNames() as $fieldName) {
            $result[$fieldName] = $this->accessor->getValue($entity, $fieldName);
        }

        foreach ($meta->getAssociationNames() as $fieldName) {
            if (isset($this->associativeFieldMappings[$fieldName])) {
                $expression = $this->associativeFieldMappings[$fieldName];

                $result[$fieldName] = $this->accessor->getValue($entity, $expression);
            } elseif (\method_exists($entity, '__toString')) {
                $result[$fieldName] = $entity->__toString();
            }
        }

        $finalResult = [];

        foreach ($result as $fieldName => $fieldValue) {
            if (\in_array($fieldName, $this->excludedFields)) {
                continue;
            }

            $finalResult[$fieldName] = $fieldValue;
        }

        return $finalResult;
    }
}
