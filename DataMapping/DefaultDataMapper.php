<?php

namespace Modera\ServerCrudBundle\DataMapping;

use Doctrine\ORM\EntityManagerInterface;

/**
 * @author    Sergei Lissovski <sergei.lissovski@modera.org>
 * @copyright 2013 Modera Foundation
 */
class DefaultDataMapper implements DataMapperInterface
{
    private EntityDataMapperService $mapper;
    private EntityManagerInterface $em;

    public function __construct(EntityDataMapperService $mapper, EntityManagerInterface $em)
    {
        $this->mapper = $mapper;
        $this->em = $em;
    }

    /**
     * @return string[]
     */
    protected function getAllowedFields(string $entityClass)
    {
        /** @var class-string $entityClass */
        $metadata = $this->em->getClassMetadata($entityClass);

        $fields = $metadata->getFieldNames();
        foreach ($metadata->getAssociationMappings() as $mapping) {
            $fields[] = $mapping['fieldName'];
        }

        return $fields;
    }

    public function mapData(array $params, object $entity): void
    {
        $allowedFields = $this->getAllowedFields(\get_class($entity));

        $this->mapper->mapEntity($entity, $params, $allowedFields);
    }
}
