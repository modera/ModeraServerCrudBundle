<?php

namespace Modera\ServerCrudBundle\Persistence;

/**
 * Implementations are responsible for persisting and querying data.
 *
 * @author    Sergei Lissovski <sergei.lissovski@modera.org>
 * @copyright 2013 Modera Foundation
 */
interface PersistenceHandlerInterface
{
    /**
     * Must returns field names which can be used to uniquely identify a record.
     *
     * @return string[]
     */
    public function resolveEntityPrimaryKeyFields(string $entityClass): array;

    public function save(object $entity): OperationResult;

    public function update(object $entity): OperationResult;

    /**
     * @param object[] $entities
     */
    public function updateBatch(array $entities): OperationResult;

    /**
     * @param array<string, mixed> $params
     *
     * @return object[]
     */
    public function query(string $entityClass, array $params): array;

    /**
     * @param object[] $entities
     */
    public function remove(array $entities): OperationResult;

    /**
     * @param array<string, mixed> $params
     */
    public function getCount(string $entityClass, array $params): int;
}
