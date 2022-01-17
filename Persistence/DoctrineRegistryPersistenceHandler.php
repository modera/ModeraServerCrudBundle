<?php

namespace Modera\ServerCrudBundle\Persistence;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Mapping\ClassMetadataInfo;
use Doctrine\ORM\Tools\Pagination\Paginator;
use Doctrine\Persistence\ManagerRegistry;
use Sli\ExtJsIntegrationBundle\QueryBuilder\ExtjsQueryBuilder;

/**
 * This implementation relies of ManagerRegistry so it can support many EntityManagers for entities.
 *
 * @author    Sergei Lissovski <sergei.lissovski@modera.org>
 * @copyright 2016 Modera Foundation
 */
class DoctrineRegistryPersistenceHandler implements PersistenceHandlerInterface
{
    /**
     * @var ManagerRegistry
     */
    private $doctrineRegistry;

    /**
     * @var ExtjsQueryBuilder
     */
    private $queryBuilder;

    /**
     * @var bool
     */
    private $usePaginator;

    /**
     * @param ManagerRegistry $doctrineRegistry
     * @param ExtjsQueryBuilder $queryBuilder
     * @param bool $usePaginator
     */
    public function __construct(ManagerRegistry $doctrineRegistry, ExtjsQueryBuilder $queryBuilder, $usePaginator = true)
    {
        $this->doctrineRegistry = $doctrineRegistry;
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

    /**
     * @param object $entity
     *
     * @return mixed
     */
    private function resolveEntityId($entity)
    {
        // TODO improve, resolve PK using entity's metadata - composite, non-surrogate PKs

        $entityClass = get_class($entity);

        /* @var ClassMetadataInfo $meta */
        $meta = $this->getEntityManagerForClass($entityClass)->getClassMetadata($entityClass);
        $identifier = $meta->getSingleIdentifierFieldName();
        $method = 'get' . ucfirst($identifier);

        if (!in_array($method, get_class_methods($entityClass))) {
            throw new \RuntimeException(sprintf(
                'Class %s must have method "%s()" (it is used to resolve PK).', $entityClass, $method
            ));
        }

        return $entity->{$method}();
    }

    /**
     * @param string|object $entityOrClass
     *
     * @return EntityManagerInterface
     */
    private function getEntityManagerForClass($entityOrClass)
    {
        $entityClass = is_object($entityOrClass) ? get_class($entityOrClass) : $entityOrClass;

        $em = $this->doctrineRegistry->getManagerForClass($entityClass);
        if (!$em) {
            throw new \RuntimeException(sprintf(
                'Unable to resolve EntityManager for class "%s". Are you sure that the entity has been properly mapped ?',
                $entityClass
            ));
        }

        if (!$em instanceof EntityManagerInterface) {
            // ExtjsQueryBuilder expects instances of EntityManagers, but the registry theoretically can also
            // return implementations of ObjectManager instead
            throw new \RuntimeException(sprintf(
                'Only implementations of %s are supported as managers, but class "%s" has been returned for entity "%s".',
                EntityManagerInterface::class, get_class($em), $entityClass
            ));
        }

        return $em;
    }

    /**
     * {@inheritdoc}
     */
    public function resolveEntityPrimaryKeyFields($entityClass)
    {
        $result = array();

        /* @var ClassMetadataInfo $meta */
        $meta = $this->getEntityManagerForClass($entityClass)->getClassMetadata($entityClass);

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
        $em = $this->getEntityManagerForClass($entity);

        $em->persist($entity);
        $em->flush();

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
        $em = $this->getEntityManagerForClass($entity);

        $em->persist($entity);
        $em->flush();

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

        /* @var EntityManagerInterface[] $managersToFlush */
        $managersToFlush = array();

        // theoretically entities which are managed by different EMs can be given
        foreach ($entities as $entity) {
            $em = $this->getEntityManagerForClass($entity);

            // so here we are grouping EMs to later flush them all at once
            $managersToFlush[spl_object_hash($em)] = $em;

            $em->persist($entity);

            $result->reportEntity(
                get_class($entity), $this->resolveEntityId($entity), OperationResult::TYPE_ENTITY_UPDATED
            );
        }

        foreach ($managersToFlush as $em) {
            $em->flush();
        }

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

        /* @var EntityManagerInterface[] $managersToFlush */
        $managersToFlush = [];

        // theoretically entities which are managed by different EMs can be given
        foreach ($entities as $entity) {
            $em = $this->getEntityManagerForClass($entity);
            $em->remove($entity);

            // so here we are grouping EMs to later flush them all at once
            $managersToFlush[spl_object_hash($em)] = $em;

            $result->reportEntity(
                get_class($entity), $this->resolveEntityId($entity), OperationResult::TYPE_ENTITY_REMOVED
            );
        }

        foreach ($managersToFlush as $em) {
            $em->flush();
        }

        return $result;
    }
}
