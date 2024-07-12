<?php

namespace Modera\ServerCrudBundle\QueryBuilder\ResolvingAssociatedModelSortingField;

use Doctrine\Common\Annotations\AnnotationReader;
use Doctrine\ORM\Mapping\ClassMetadataInfo;
use Doctrine\Persistence\ManagerRegistry;
use Modera\ServerCrudBundle\Util\Toolkit;

/**
 * @author Sergei Vizel <sergei.vizel@modera.org>
 * @copyright 2024 Modera Foundation
 */
class AnnotationSortingFieldResolver implements SortingFieldResolverInterface
{
    private ManagerRegistry $doctrineRegistry;

    private AnnotationReader $annotationReader;

    private string $defaultPropertyName;

    public function __construct(
        ManagerRegistry $doctrineRegistry,
        string $defaultPropertyName = 'id',
        ?AnnotationReader $annotationReader = null
    ) {
        $this->doctrineRegistry = $doctrineRegistry;

        $this->defaultPropertyName = $defaultPropertyName;

        $this->annotationReader = $annotationReader ?? new AnnotationReader();
    }

    private function getDefaultPropertyName(string $entityFqcn): string
    {
        $names = [];
        foreach (Toolkit::getObjectProperties($entityFqcn) as $refProperty) {
            $names[] = $refProperty->getName();
        }
        if (!\in_array($this->defaultPropertyName, $names)) {
            throw new \RuntimeException("$entityFqcn::{$this->defaultPropertyName} doesn't exist.");
        }

        return $this->defaultPropertyName;
    }

    public function resolve(string $entityFqcn, string $fieldName): ?string
    {
        /** @var class-string $className */
        $className = $entityFqcn;

        $em = $this->doctrineRegistry->getManagerForClass($className);
        if (!$em) {
            throw new \RuntimeException(\sprintf('Manager for class "%s" not found', $entityFqcn));
        }

        /** @var ?ClassMetadataInfo $metadata */
        $metadata = $em->getClassMetadata($className);
        if (!$metadata) {
            throw new \RuntimeException("Unable to load metadata for class '$entityFqcn'.");
        }

        $fieldMapping = $metadata->getAssociationMapping($fieldName);

        $objectProperty = Toolkit::getObjectProperty($entityFqcn, $fieldName);
        if ($objectProperty) {
            /** @var ?QueryOrder $annotation */
            $annotation = $this->annotationReader->getPropertyAnnotation($objectProperty, QueryOrder::class);
            if ($annotation) { // property annotation found
                /** @var string $name */
                $name = $annotation->value;

                return $name;
            }
        }

        /** @var ?QueryOrder $annotation */
        $annotation = $this->annotationReader->getClassAnnotation(new \ReflectionClass($fieldMapping['targetEntity']), QueryOrder::class);
        if ($annotation) { // class annotation found
            /** @var string $name */
            $name = $annotation->value;

            return $name;
        }

        return $this->getDefaultPropertyName($fieldMapping['targetEntity']);
    }
}
