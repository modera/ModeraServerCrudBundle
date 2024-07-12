<?php

namespace Modera\ServerCrudBundle\Persistence;

/**
 * @author    Sergei Lissovski <sergei.lissovski@modera.org>
 * @copyright 2016 Modera Foundation
 */
class DelegatePersistenceHandler implements PersistenceHandlerInterface
{
    protected PersistenceHandlerInterface $delegate;

    public function __construct(PersistenceHandlerInterface $delegate)
    {
        $this->delegate = $delegate;
    }

    public function resolveEntityPrimaryKeyFields(string $entityClass): array
    {
        return $this->delegate->resolveEntityPrimaryKeyFields($entityClass);
    }

    public function save(object $entity): OperationResult
    {
        return $this->delegate->save($entity);
    }

    public function update(object $entity): OperationResult
    {
        return $this->delegate->update($entity);
    }

    public function updateBatch(array $entities): OperationResult
    {
        return $this->delegate->updateBatch($entities);
    }

    public function query(string $entityClass, array $params): array
    {
        return $this->delegate->query($entityClass, $params);
    }

    public function remove(array $entities): OperationResult
    {
        return $this->delegate->remove($entities);
    }

    public function getCount(string $entityClass, array $params): int
    {
        return $this->delegate->getCount($entityClass, $params);
    }
}
