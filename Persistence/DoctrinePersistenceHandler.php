<?php

namespace Modera\ServerCrudBundle\Persistence;

use Doctrine\ORM\EntityManager;
use Doctrine\ORM\Mapping\ClassMetadataInfo;
use Doctrine\ORM\Tools\Pagination\Paginator;
use Sli\ExtJsIntegrationBundle\QueryBuilder\ExtjsQueryBuilder;

/**
 * @deprecated Use DoctrineRegistryPersistenceHandler instead
 *
 * Implementations of PersistenceHandlerInterface which eventually will use Doctrine's EntityManager to communicate
 * with database
 *
 * @author    Sergei Lissovski <sergei.lissovski@modera.org>
 * @copyright 2013 Modera Foundation
 */
class DoctrinePersistenceHandler implements PersistenceHandlerInterface
{
    private $em;
    private $queryBuilder;
    private $usePaginator;

    /**
     * @param EntityManager     $em
     * @param ExtjsQueryBuilder $queryBuilder
     * @param bool $usePaginator
     */
    public function __construct(EntityManager $em, ExtjsQueryBuilder $queryBuilder, $usePaginator = true)
    {
        $this->em = $em;
        $this->queryBuilder = $queryBuilder;
        $this->usePaginator = $usePaginator;
    }

    /**
     * @param string $entityClass
     * @param array  $query
     * @return Paginator
     */
    private function createPaginator($entityClass, array $query)
    {
        $qb = $this->queryBuilder->buildQueryBuilder($entityClass, $query);

        return new Paginator($qb->getQuery());
    }

    private function resolveEntityId($entity)
    {
        // TODO improve
        return $entity->getId();
    }

    /**
     * {@inheritdoc}
     */
    public function resolveEntityPrimaryKeyFields($entityClass)
    {
        $result = array();

        /* @var ClassMetadataInfo $meta */
        $meta = $this->em->getClassMetadata($entityClass);

        foreach ($meta->getFieldNames() as $fieldName) {
            $fieldMapping = $meta->getFieldMapping($fieldName);

            if (isset($fieldMapping['id']) && $fieldMapping['id']) {
                $result[] = $fieldName;
            }
        }

        return $result;
    }

    /**
     * {@inheritdoc}
     */
    public function save($entity)
    {
        $this->em->persist($entity);
        $this->em->flush();

        $result = new OperationResult();
        $result->reportEntity(
            get_class($entity), $this->resolveEntityId($entity), OperationResult::TYPE_ENTITY_CREATED
        );

        return $result;
    }

    /**
     * {@inheritdoc}
     */
    public function update($entity)
    {
        $this->em->persist($entity);
        $this->em->flush();

        $result = new OperationResult();
        $result->reportEntity(
            get_class($entity), $this->resolveEntityId($entity), OperationResult::TYPE_ENTITY_UPDATED
        );

        return $result;
    }

    /**
     * @param object[] $entities
     *
     * @return OperationResult
     */
    public function updateBatch(array $entities)
    {
        $result = new OperationResult();

        foreach ($entities as $entity) {
            $this->em->persist($entity);

            $result->reportEntity(
                get_class($entity), $this->resolveEntityId($entity), OperationResult::TYPE_ENTITY_UPDATED
            );
        }

        $this->em->flush();

        return $result;
    }

    /**
     * {@inheritdoc}
     */
    public function query($entityClass, array $query)
    {
        if ($this->usePaginator) {
            return $this->createPaginator($entityClass, $query)->getIterator()->getArrayCopy();
        }

        return $this->queryBuilder->buildQuery($entityClass, $query)->getResult();
    }

    /**
     * {@inheritdoc}
     */
    public function getCount($entityClass, array $query)
    {
        if ($this->usePaginator) {
            return $this->createPaginator($entityClass, $query)->count();
        }

        $qb = $this->queryBuilder->buildQueryBuilder($entityClass, $query);

        return $this->queryBuilder->buildCountQueryBuilder($qb)->getQuery()->getSingleScalarResult();
    }

    /**
     * {@inheritdoc}
     */
    public function remove(array $entities)
    {
        $result = new OperationResult();

        foreach ($entities as $entity) {
            $this->em->remove($entity);

            $result->reportEntity(
                get_class($entity), $this->resolveEntityId($entity), OperationResult::TYPE_ENTITY_REMOVED
            );
        }

        $this->em->flush();

        return $result;
    }

    public static function clazz()
    {
        return get_called_class();
    }
}
